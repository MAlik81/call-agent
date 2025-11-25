// server.js — WS proxy with per-call dedupe, VAD, WAV ingest, and structured logs
require('dotenv').config();
const express = require('express');
const http = require('http');
const url = require('url');
const WebSocket = require('ws');
const axios = require('axios');

// ---------- logger ----------
const LOG_LEVELS = { error: 0, warn: 1, info: 2, debug: 3 };
const LOG_LEVEL = process.env.LOG_LEVEL ? (LOG_LEVELS[process.env.LOG_LEVEL] ?? 2) : 2; // default=info
const LOG_FRAME_EVERY = parseInt(process.env.LOG_FRAME_EVERY || '50', 10);
let _frameCounter = 0;

function stamp() {
  const d = new Date();
  const t = d.toISOString();
  const ms = String(d.getMilliseconds()).padStart(3, '0');
  return `${t.slice(0, 19)}.${ms}Z`;
}
function log(level, msg, meta = {}) {
  if ((LOG_LEVELS[level] ?? 99) > LOG_LEVEL) return;
  const line = `[${stamp()}] ${level.toUpperCase()} ${msg}`;
  if (meta && Object.keys(meta).length) {
    (level === 'error' ? console.error : console.log)(line, meta);
  } else {
    (level === 'error' ? console.error : console.log)(line);
  }
}

// ---------- env ----------
const {
  PORT = 3000,
  LARAVEL_API_BASE,
  OPENAI_API_KEY,
} = process.env;

if (!LARAVEL_API_BASE) {
  log('error', 'Missing env: LARAVEL_API_BASE');
  process.exit(1);
}

// ---------- express/http/ws ----------
const app = express();
const server = http.createServer(app);
const wss = new WebSocket.Server({ noServer: true });

// ---------- global per-call state ----------
/**
 * calls.set(callSid, {
 *   tenantId,
 *   ws,
 *   bootstrap,
 *   bootstrapFetched,
 *   realtime,
 *   segmentQueue,
 *   segmentWorkerActive,
 *   segmentIndexes,
 * })
 */
const calls = new Map();

// ---------- VAD tuning ----------
const RMS_ON            = 0.018;   // speech starts above this
const REALTIME_SAMPLE_RATE = 16000;
const USER_SEG_SILENCE_MS = 800;   // end user segment after this much silence
const USER_SEG_MAX_MS = 12000;     // hard stop user segments
const ASSISTANT_SEG_IDLE_MS = 1200; // end assistant segment if idle
const ASSISTANT_SEG_MAX_MS = 15000; // hard stop assistant segments

// ---------- audio utils: μ-law -> PCM16, WAV, RMS ----------
function decodeMulawToPCM16(muBuf) {
  const pcm = Buffer.alloc(muBuf.length * 2);
  for (let i = 0; i < muBuf.length; i++) {
    let u = (~muBuf[i]) & 0xff;
    const sign = u & 0x80;
    const exponent = (u & 0x70) >> 4;
    const mantissa = u & 0x0f;
    let t = ((mantissa << 3) + 0x84) << exponent; // bias 0x84
    let sample = sign ? (0x84 - t) : (t - 0x84);
    if (sample > 32767) sample = 32767;
    if (sample < -32768) sample = -32768;
    pcm.writeInt16LE(sample, i * 2);
  }
  return pcm;
}

function rmsPcm16(pcmBuf) {
  if (!pcmBuf || pcmBuf.length < 2) return 0;
  let sum = 0;
  const samples = pcmBuf.length / 2;
  for (let i = 0; i < pcmBuf.length; i += 2) {
    const s = pcmBuf.readInt16LE(i);
    sum += s * s;
  }
  return Math.sqrt(sum / samples) / 32768;
}

function resamplePcm16(pcmBuf, fromRate, toRate) {
  if (!pcmBuf || fromRate === toRate) return pcmBuf;

  const fromSamples = pcmBuf.length / 2;
  const toSamples = Math.max(1, Math.round(fromSamples * toRate / fromRate));
  const out = Buffer.alloc(toSamples * 2);
  const ratio = fromSamples / toSamples;

  for (let i = 0; i < toSamples; i++) {
    const pos = i * ratio;
    const base = Math.floor(pos);
    const frac = pos - base;
    const s1 = pcmBuf.readInt16LE(Math.min(base, fromSamples - 1) * 2);
    const s2 = pcmBuf.readInt16LE(Math.min(base + 1, fromSamples - 1) * 2);
    const sample = Math.round(s1 + (s2 - s1) * frac);
    out.writeInt16LE(Math.max(-32768, Math.min(32767, sample)), i * 2);
  }

  return out;
}

function encodePCM16ToMulaw(pcmBuf) {
  const mu = Buffer.alloc(pcmBuf.length / 2);
  for (let i = 0; i < pcmBuf.length; i += 2) {
    let sample = pcmBuf.readInt16LE(i);
    const sign = sample < 0 ? 0x80 : 0;
    if (sample < 0) sample = -sample;
    if (sample > 32635) sample = 32635;

    sample += 0x84; // bias
    const exponent = Math.floor(Math.log(sample) / Math.log(2)) - 7;
    const mantissa = (sample >> (exponent + 3)) & 0x0f;
    const uval = ~(sign | (exponent << 4) | mantissa) & 0xff;
    mu[i / 2] = uval;
  }
  return mu;
}

// ---------- routes ----------
app.get('/', (_req, res) => res.send('<h1>AI Call Proxy — WS up</h1>'));
app.get('/ws-status', (_req, res) => {
  log('info', '[STATUS] ws-status check', { LARAVEL_API_BASE });
  const wsRunning = !!wss && typeof wss.handleUpgrade === 'function';
  const activeCalls = Array.from(calls.values()).filter(c => c.ws !== null).length;
  res.json({ ws_server_running: wsRunning, ws_active_calls: activeCalls, LARAVEL_API_BASE });
});

// ---------- upgrade -> ws ----------
server.on('upgrade', (request, socket, head) => {
  const { pathname, query } = url.parse(request.url, true);
  log('info', '[UPGRADE] request', { pathname, query });

  const isMediaPath = pathname === '/media-stream' || pathname === '/twilio-media';

  if (isMediaPath) {
    wss.handleUpgrade(request, socket, head, (ws) => {
      ws.params = query;
      handleMediaStream(ws);
    });
  } else {
    log('warn', '[UPGRADE] unknown path, closing', { pathname });
    socket.destroy();
  }
});

// ---------- core WS handler ----------
async function handleMediaStream(ws) {
  let streamSid = null;
  let callSid = ws.params?.call_sid || null;
  let callId = Number.parseInt(ws.params?.call_id, 10);
  callId = Number.isFinite(callId) ? callId : null;
  let tenantId = ws.params?.tenant_id || 'unknown-tenant';
  let tenantUuid = ws.params?.tenant_uuid || null;
  let toNumber = ws.params?.to_number || ws.params?.to || ws.params?.Called || null;

  // lifecycle
  let connOpenedAt = Date.now();
  let callEnded = false;
  let errorCount = 0;
  let rtErrorCount = 0;

  // live metrics
  let bytesIn = 0;
  let chunksIn = 0;

  // per-connection flags
  let connActive = true;

  // realtime audio bridging
  let userPcm16Buffers = [];
  let assistantPcm16Buffers = [];
  let userSegmentStartedAt = 0;
  let assistantSegmentStartedAt = 0;
  let userSegLastVoiceAt = 0;
  let assistantLastChunkAt = 0;
  let assistantIdleTimer = null;

  // per-call shared state
  let callState = null;
  let callKey = null;

  function getCallKey() {
    if (callSid) return callSid;
    if (callId) return `call-${callId}`;
    return null;
  }

  function ensureCallState() {
    const newKey = getCallKey();
    if (!newKey) return null;

    if (callState && callKey && callKey !== newKey) {
      calls.delete(callKey);
      calls.set(newKey, callState);
      callKey = newKey;
    }

    if (!callState) {
      callState = calls.get(newKey);
      if (!callState) {
        callState = {
          callId: callId || null,
          callSid: callSid || null,
          tenantId: tenantId || 'unknown-tenant',
          tenantUuid: tenantUuid || null,
          toNumber: toNumber || null,
          ws: null,
          bootstrap: null,
          bootstrapFetched: false,
          realtime: null,
          segmentQueue: [],
          segmentWorkerActive: false,
          segmentIndexes: { user: 0, assistant: 0 },
        };
        calls.set(newKey, callState);
      } else {
        callState.segmentQueue = callState.segmentQueue || [];
        callState.segmentIndexes = callState.segmentIndexes || { user: 0, assistant: 0 };
      }
      callKey = newKey;
    }

    callState.callSid = callSid || callState.callSid;
    callState.callId = callId || callState.callId;
    callState.tenantId = tenantId || callState.tenantId || 'unknown-tenant';
    callState.tenantUuid = tenantUuid || callState.tenantUuid || null;
    callState.toNumber = toNumber || callState.toNumber || null;

    return callState;
  }

  function appendAudioToRealtime(pcm16) {
    const rt = callState?.realtime;
    if (!rt?.ready || rt.ws?.readyState !== WebSocket.OPEN) return;
    try {
      rt.ws.send(JSON.stringify({ type: 'input_audio_buffer.append', audio: pcm16.toString('base64') }));
      rt.inputSamplesBuffered = (rt.inputSamplesBuffered || 0) + (pcm16.length / 2);
    } catch (err) {
      log('error', '[REALTIME] append failed', { callSid, err: err?.message });
    }
  }

  function sendAudioToTwilio(muBuf) {
    const tws = callState?.ws || ws;
    if (!tws || tws.readyState !== WebSocket.OPEN || !streamSid) return;
    const payload = { event: 'media', streamSid, media: { payload: muBuf.toString('base64') } };
    try { tws.send(JSON.stringify(payload)); }
    catch (err) { log('error', '[TWILIO] send failed', { callSid, err: err?.message }); }
  }

  function resetAssistantIdleTimer() {
    if (assistantIdleTimer) clearTimeout(assistantIdleTimer);
    if (!assistantPcm16Buffers.length) return;
    assistantIdleTimer = setTimeout(() => finalizeAssistantSegment('silence'), ASSISTANT_SEG_IDLE_MS);
  }

  function finalizeAssistantSegment(reason = 'complete') {
    if (assistantIdleTimer) {
      clearTimeout(assistantIdleTimer);
      assistantIdleTimer = null;
    }
    if (!assistantPcm16Buffers.length) return;
    const pcmBuf = Buffer.concat(assistantPcm16Buffers);
    const now = Date.now();
    const startTs = assistantSegmentStartedAt || now;
    const ms = now - startTs;
    const lastDeltaAgo = assistantLastChunkAt ? (Date.now() - assistantLastChunkAt) : 0;
    const segmentIndex = nextSegmentIndex('assistant');
    const audioB64 = pcmBuf.toString('base64');
    const samples = pcmBuf.length / 2;
    enqueueSegmentJob({
      role: 'assistant',
      segmentIndex,
      format: 'pcm16',
      sampleRate: REALTIME_SAMPLE_RATE,
      audioB64,
      startedAt: startTs,
      endedAt: now,
      durationMs: ms,
      streamSid,
      reason,
      samples,
    });
    assistantPcm16Buffers = [];
    assistantSegmentStartedAt = 0;
    assistantLastChunkAt = 0;
    log('info', '[SEGMENT] assistant finalized', { callSid, reason, ms, lastDeltaAgo, samples, segmentIndex });
  }

  function finalizeUserSegment(reason = 'silence') {
    if (!userPcm16Buffers.length) return;
    const pcmBuf = Buffer.concat(userPcm16Buffers);
    const now = Date.now();
    const startTs = userSegmentStartedAt || now;
    const ms = now - startTs;
    const segmentIndex = nextSegmentIndex('user');
    const audioB64 = pcmBuf.toString('base64');
    const samples = pcmBuf.length / 2;
    enqueueSegmentJob({
      role: 'user',
      segmentIndex,
      format: 'pcm16',
      sampleRate: REALTIME_SAMPLE_RATE,
      audioB64,
      startedAt: startTs,
      endedAt: now,
      durationMs: ms,
      streamSid,
      reason,
      samples,
    });
    userPcm16Buffers = [];
    userSegmentStartedAt = 0;
    userSegLastVoiceAt = 0;

    const rt = callState?.realtime;
    if (rt?.ready && rt.ws?.readyState === WebSocket.OPEN) {
      if (rt.inputSamplesBuffered && rt.inputSamplesBuffered > 0) {
        try { rt.ws.send(JSON.stringify({ type: 'input_audio_buffer.commit' })); }
        catch (err) { log('error', '[REALTIME] commit failed', { callSid, err: err?.message }); }
        rt.inputSamplesBuffered = 0;
      } else {
        log('warn', '[REALTIME] skip commit: empty audio buffer', { callSid });
      }

      try { rt.ws.send(JSON.stringify({ type: 'response.create' })); }
      catch (err) { log('error', '[REALTIME] response.create failed', { callSid, err: err?.message }); }
    }

    log('info', '[SEGMENT] user finalized', { callSid, reason, ms, samples, segmentIndex });
  }

  async function fetchBootstrapConfig() {
    const state = ensureCallState();
    if (!state || state.bootstrapFetched) return state?.bootstrap || null;

    state.bootstrapFetched = true;

    const payload = {};
    if (state.callId) payload.call_id = state.callId;
    if (state.toNumber) payload.to_number = state.toNumber;
    if (state.tenantId) payload.tenant_id = state.tenantId;
    if (state.tenantUuid) payload.tenant_uuid = state.tenantUuid;

    if (!payload.call_id && !payload.to_number) {
      log('warn', '[BOOTSTRAP] skipped: missing call_id and to_number', { callSid });
      return null;
    }

    try {
      const resp = await axios.post(
        `${LARAVEL_API_BASE}/api/voice/bootstrap`,
        payload,
        { timeout: 10000 },
      );
      state.bootstrap = resp.data;
      log('info', '[BOOTSTRAP] fetched', { callSid, callId: state.callId, tenantId: state.tenantId });
    } catch (err) {
      const errMeta = err?.response?.data || err.message;
      log('error', '[BOOTSTRAP] failed', { callSid, err: errMeta });
      state.bootstrap = state.bootstrap || { config: { realtime_enabled: true } };
      log('warn', '[BOOTSTRAP] continuing with realtime bridge only', { callSid, err: errMeta });
    }

    return state.bootstrap;
  }

  function buildRealtimeInstructions(config = {}) {
    const sections = [];
    if (config.realtime_system_prompt) sections.push(config.realtime_system_prompt);
    else if (config.prompt) sections.push(config.prompt);

    if (Array.isArray(config.rules) && config.rules.length) {
      const rules = config.rules.map((r, i) => `${i + 1}. ${r}`).join('\n');
      sections.push(`Call rules:\n${rules}`);
    }

    return sections.filter(Boolean).join('\n\n');
  }

  function attachRealtimeKeepAlive(state) {
    if (!state?.realtime?.ws) return;
    if (state.realtime.keepAlive) clearInterval(state.realtime.keepAlive);

    state.realtime.keepAlive = setInterval(() => {
      try { state.realtime.ws.ping(); } catch { clearInterval(state.realtime.keepAlive); }
    }, 10000);
  }

  function closeRealtime(state) {
    if (!state?.realtime) return;
    if (state.realtime.keepAlive) {
      clearInterval(state.realtime.keepAlive);
      state.realtime.keepAlive = null;
    }
    if (state.realtime.ws && state.realtime.ws.readyState === WebSocket.OPEN) {
      try { state.realtime.ws.close(); } catch {}
    }
    state.realtime.ws = null;
    state.realtime.ready = false;
  }

  function endCall(reason = 'unknown') {
    if (callEnded) return;
    callEnded = true;
    connActive = false;

    finalizeUserSegment(reason);
    finalizeAssistantSegment(reason);
    closeRealtime(callState);

    const key = getCallKey();
    if (key && calls.get(key)?.ws === ws) {
      calls.get(key).ws = null;
    }

    if (ws && ws.readyState === WebSocket.OPEN) {
      try { ws.close(); } catch {}
    }

    const durationMs = Date.now() - connOpenedAt;
    log('info', '[CALL] ended', { callSid, reason, durationMs, errors: errorCount, realtimeErrors: rtErrorCount, chunksIn, bytesIn });
  }

  function nextSegmentIndex(role = 'user') {
    if (!callState) return 0;
    callState.segmentIndexes = callState.segmentIndexes || { user: 0, assistant: 0 };
    callState.segmentIndexes[role] = (callState.segmentIndexes[role] || 0) + 1;
    return callState.segmentIndexes[role];
  }

  function enqueueSegmentJob(job) {
    if (!callState) return;
    callState.segmentQueue = callState.segmentQueue || [];
    callState.segmentQueue.push(job);
    setImmediate(drainSegmentQueue);
  }

  async function drainSegmentQueue() {
    if (!callState || callState.segmentWorkerActive) return;
    callState.segmentWorkerActive = true;
    callState.segmentQueue = callState.segmentQueue || [];

    while (callState.segmentQueue.length) {
      const job = callState.segmentQueue.shift();
      try {
        await sendSegmentJob(job);
      } catch (err) {
        log('error', '[SEGMENT] job failed', { callSid, err: err?.message });
      }
    }

    callState.segmentWorkerActive = false;
  }

  async function sendSegmentJob(job = {}) {
    const sessionId = job.sessionId || callState?.callId || callId || callSid;
    if (!sessionId) {
      log('warn', '[SEGMENT] skipped: missing session id', { callSid, role: job.role });
      return;
    }

    const format = (typeof job.format === 'string' && job.format.trim()) ? job.format.trim() : 'pcm16';
    const sampleRateCandidate = Number(job.sampleRate);
    const sampleRate = Number.isFinite(sampleRateCandidate) ? sampleRateCandidate : REALTIME_SAMPLE_RATE;
    const audioB64 = job.audioB64;

    if (!audioB64) {
      log('warn', '[SEGMENT] skipped: missing audio payload', { callSid, role: job.role, idx: job.segmentIndex });
      return;
    }

    const url = `${LARAVEL_API_BASE}/api/call-sessions/${encodeURIComponent(sessionId)}/segments`;
    const payload = {
      role: job.role,
      segment_index: job.segmentIndex,
      call_id: callState?.callId || null,
      call_sid: callSid || null,
      tenant_id: callState?.tenantId || tenantId || null,
      format,
      sample_rate: sampleRate,
      audio_b64: audioB64,
      meta: {
        started_at: job.startedAt ? new Date(job.startedAt).toISOString() : null,
        ended_at: job.endedAt ? new Date(job.endedAt).toISOString() : null,
        duration_ms: job.durationMs || null,
        stream_sid: job.streamSid || streamSid || null,
        reason: job.reason || null,
        samples: job.samples || null,
      },
    };

    const headers = { 'Content-Type': 'application/json' };

    const isDuplicateError = (err) => {
      const status = err?.response?.status;
      const msg = (err?.response?.data?.message || err?.response?.data || err?.message || '').toString();
      if (status === 409) return true;
      return msg.includes('Duplicate entry') || msg.includes('call_segments_call_session_id_segment_index_unique');
    };

    for (let attempt = 1; attempt <= 3; attempt++) {
      try {
        await axios.post(url, payload, { headers, timeout: 10000 });
        log('info', '[SEGMENT] posted', { callSid, role: job.role, idx: job.segmentIndex, attempt, format, sampleRate, audioBytes: audioB64.length });
        return;
      } catch (err) {
        const errData = err?.response?.data || err.message;
        log('warn', '[SEGMENT] post failed', { callSid, role: job.role, idx: job.segmentIndex, attempt, format, sampleRate, audioBytes: audioB64.length, err: errData });
        if (isDuplicateError(err)) {
          log('warn', '[SEGMENT] duplicate index, skipping retries', { callSid, role: job.role, idx: job.segmentIndex });
          return;
        }
        if (attempt < 3) await new Promise((res) => setTimeout(res, attempt * 500));
      }
    }
  }

  async function ensureRealtimeSession() {
    const state = ensureCallState();
    // log state
    log('debug', '[REALTIME] ensure session', { callSid, stateBootstrap: !!state?.bootstrap, realtimeReady: !!state?.realtime?.ready });
    if (!state?.bootstrap?.config?.realtime_enabled) return null;

    const apiKey = state.bootstrap?.openai_api_key || OPENAI_API_KEY;
    if (!apiKey) {
      log('warn', '[REALTIME] skipped: missing OpenAI API key', { callSid });
      return null;
    }

    if (state.realtime?.ready && state.realtime.ws?.readyState === WebSocket.OPEN) return state.realtime;

    const config = state.bootstrap.config || {};
    const model = config.realtime_model || 'gpt-4o-realtime-preview';
    const rtUrl = `wss://api.openai.com/v1/realtime?model=${encodeURIComponent(model)}`;

    const rtWs = new WebSocket(rtUrl, {
      headers: { Authorization: `Bearer ${apiKey}` },
    });

    state.realtime = { ws: rtWs, ready: false, keepAlive: null, inputSamplesBuffered: 0 };

    rtWs.on('open', () => {
      const instructions = buildRealtimeInstructions(config);
      const sessionUpdate = {
        type: 'session.update',
        session: {
          model,
          voice: config.realtime_voice || undefined,
          language: config.realtime_language || undefined,
          input_audio_format: 'pcm16',
          input_audio_sample_rate: REALTIME_SAMPLE_RATE,
          output_audio_format: 'pcm16',
          output_audio_sample_rate: REALTIME_SAMPLE_RATE,
          input_audio_transcription: { enabled: true },
          modalities: ['text', 'audio'],
          instructions,
        },
      };

      try { rtWs.send(JSON.stringify(sessionUpdate)); }
      catch (e) { log('error', '[REALTIME] failed to send session.update', { callSid, err: e?.message }); }

      state.realtime.ready = true;
      attachRealtimeKeepAlive(state);
      log('info', '[REALTIME] session opened', { callSid, model });
    });

    rtWs.on('message', (msg) => {
      let evt;
      try { evt = JSON.parse(msg.toString()); }
      catch (err) { return log('warn', '[REALTIME] parse error', { callSid, err: err?.message }); }

      if (LOG_LEVEL >= LOG_LEVELS.debug) {
        log('debug', '[REALTIME] message', { callSid, type: evt?.type, bytes: msg?.length || 0 });
      }

      if (evt?.type === 'response.audio.delta' || evt?.type === 'response.output_audio.delta' || evt?.type === 'response.output_audio.buffer.append') {
        const base64 = evt.delta || evt.audio || evt.output_audio || evt.output_audio_delta || evt.data || evt.output_audio?.data;
        if (!base64) return log('warn', '[REALTIME] missing audio delta payload', { callSid, type: evt?.type });

        const pcm16 = Buffer.from(base64, 'base64');
        assistantPcm16Buffers.push(pcm16);
        const now = Date.now();
        assistantLastChunkAt = now;
        if (!assistantSegmentStartedAt) assistantSegmentStartedAt = now;

        const pcm8 = resamplePcm16(pcm16, REALTIME_SAMPLE_RATE, 8000);
        const mu = encodePCM16ToMulaw(pcm8);
        sendAudioToTwilio(mu);
        resetAssistantIdleTimer();

        if (assistantSegmentStartedAt && (now - assistantSegmentStartedAt) >= ASSISTANT_SEG_MAX_MS) {
          finalizeAssistantSegment('assistant-max');
        }
        return;
      }

      if (evt?.type === 'response.completed' || evt?.type === 'response.output_audio.stopped' || evt?.type === 'response.audio.stopped') {
        finalizeAssistantSegment('realtime-complete');
        return;
      }

      if (evt?.type === 'error') {
        rtErrorCount++;
        log('error', '[REALTIME] event error', { callSid, err: evt?.error || evt?.message || evt });
      }
    });

    rtWs.on('close', () => {
      if (!callEnded) {
        log('info', '[REALTIME] session closed', { callSid });
        endCall('realtime-close');
      }
    });

    rtWs.on('error', (err) => {
      rtErrorCount++;
      log('error', '[REALTIME] socket error', { callSid, err: err?.message });
      endCall('realtime-error');
    });

    return state.realtime;
  }

  ws.on('message', async (raw) => {
    let data;
    try { data = JSON.parse(raw.toString()); }
    catch (e) { return log('error', '[WS] parse error', { err: e.message }); }

    if (data.event === 'start') {
      streamSid = data.start.streamSid;
      callSid   = data.start.callSid || ws.params?.call_sid || callSid;

      const cp = data.start.customParameters;
      if (Array.isArray(cp)) {
        for (const item of cp) {
          if (item?.name === 'tenant_id') tenantId = item.value;
          if (item?.name === 'tenant_uuid') tenantUuid = item.value;
          if (item?.name === 'call_id') callId = Number.parseInt(item.value, 10) || callId;
          if (item?.name === 'to_number') toNumber = item.value || toNumber;
        }
      } else if (cp && typeof cp === 'object') {
        tenantId = cp.tenant_id || tenantId;
        tenantUuid = cp.tenant_uuid || tenantUuid;
        callId = Number.parseInt(cp.call_id, 10) || callId;
        toNumber = cp.to_number || toNumber;
      }

      ensureCallState();
      if (callState) {
        if (callState.ws && callState.ws !== ws) {
          try { callState.ws.close(); } catch {}
        }
        callState.ws = ws;
      }

      log('info', '[WS] start', { streamSid, callSid, tenantId, callId, toNumber });
      await fetchBootstrapConfig();
      await ensureRealtimeSession();
      return;
    }

    if (data.event === 'media') {
      if (!connActive || !callState) return;

      // μ-law frame
      const mu = Buffer.from(data.media.payload, 'base64');
      const pcm8 = decodeMulawToPCM16(mu);
      const pcm16 = resamplePcm16(pcm8, 8000, REALTIME_SAMPLE_RATE);
      chunksIn++;
      bytesIn += mu.length;
      if (LOG_LEVEL >= LOG_LEVELS.debug && (++_frameCounter % LOG_FRAME_EVERY) === 0) {
        log('debug', '[WS] media frame', { callSid, chunk: chunksIn, muBytes: mu.length });
      }

      // realtime streaming + segment buffer for user
      appendAudioToRealtime(pcm16);

      const segEnergy = rmsPcm16(pcm16);
      const now = Date.now();

      let bufferThisFrame = false;
      if (segEnergy >= RMS_ON) {
        if (!userSegmentStartedAt) userSegmentStartedAt = now;
        userSegLastVoiceAt = now;
        bufferThisFrame = true;
      } else if (userSegmentStartedAt) {
        bufferThisFrame = true;
      }

      if (bufferThisFrame) userPcm16Buffers.push(pcm16);

      const segDuration = userSegmentStartedAt ? (now - userSegmentStartedAt) : 0;
      const segSilence = userSegLastVoiceAt ? (now - userSegLastVoiceAt) : 0;
      if (userPcm16Buffers.length && userSegLastVoiceAt && segSilence >= USER_SEG_SILENCE_MS) {
        finalizeUserSegment('silence');
      } else if (userPcm16Buffers.length && segDuration >= USER_SEG_MAX_MS) {
        finalizeUserSegment('timeout');
      }
      return;
    }

    if (data.event === 'stop') {
      log('info', '[WS] stop received', { callSid });
      endCall('twilio-stop');
      return;
    }
  });

  ws.on('close', () => {
    endCall('socket-close');
  });

  ws.on('error', (err) => {
    errorCount++;
    log('error', '[WS] socket error', { callSid, err: err?.message });
  });
}

server.listen(PORT, () => log('info', 'WS proxy listening', { port: PORT }));
