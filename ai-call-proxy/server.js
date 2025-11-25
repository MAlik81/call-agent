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
  APP_SHARED_TOKEN,
  OPENAI_API_KEY,
} = process.env;

if (!LARAVEL_API_BASE || !APP_SHARED_TOKEN) {
  log('error', 'Missing env: LARAVEL_API_BASE and/or APP_SHARED_TOKEN');
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
 *   playing: false,
 *   lastPlayAt: 0,
 *   lastStopAt: 0,
 *   ingestInFlight: false,
 *   ingestQueue: [],
 *   ingestWorkerActive: false,
 * })
 */
const calls = new Map();

// ---------- VAD tuning ----------
const RMS_ON            = 0.018;   // speech starts above this
const RMS_OFF           = 0.010;   // speech ends below this
const SPEECH_ON_FRAMES  = 4;       // ~80ms (4x ~20ms)
const SPEECH_OFF_FRAMES = 10;      // ~200ms
const MIN_CHUNK_MS      = 450;     // min voiced duration before accept
const SILENCE_SECS      = 0.25;    // silence to finalize
const CHUNK_COOLDOWN_MS = 250;     // min gap between ingests
const PLAY_LOCK_MS      = 2600;    // debounce play; "playing window"
const STOP_COOLDOWN_MS  = 500;     // throttle stop spam
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

function pcm16ToWav(pcmBuf, sampleRate = 8000, channels = 1) {
  const blockAlign = channels * 2;
  const byteRate = sampleRate * blockAlign;
  const dataSize = pcmBuf.length;
  const header = Buffer.alloc(44);

  header.write('RIFF', 0);
  header.writeUInt32LE(36 + dataSize, 4);
  header.write('WAVE', 8);
  header.write('fmt ', 12);
  header.writeUInt32LE(16, 16);  // PCM subchunk size
  header.writeUInt16LE(1, 20);   // PCM format
  header.writeUInt16LE(channels, 22);
  header.writeUInt32LE(sampleRate, 24);
  header.writeUInt32LE(byteRate, 28);
  header.writeUInt16LE(blockAlign, 32);
  header.writeUInt16LE(16, 34);  // bits per sample
  header.write('data', 36);
  header.writeUInt32LE(dataSize, 40);

  return Buffer.concat([header, pcmBuf]);
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

  // live metrics
  let bytesIn = 0;
  let chunksIn = 0;
  let turns = 0;

  // per-connection VAD
  let connActive = true;
  let state = 'IDLE';
  let speechOnCount = 0;
  let speechOffCount = 0;
  let speechStartedAt = 0;
  let lastSpeechAt = 0;
  let hadSpeechSinceFlush = false;

  // realtime audio bridging
  let userPcm16Buffers = [];
  let assistantPcm16Buffers = [];
  let userSegmentStartedAt = 0;
  let assistantSegmentStartedAt = 0;
  let userSegLastVoiceAt = 0;
  let assistantLastChunkAt = 0;
  let assistantIdleTimer = null;

  // buffers
  const preRollMu = [];
  const MAX_PREROLL = 10; // ~200ms (μ-law 8kHz 20ms frames)
  let captureMu = [];

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
          playing: false,
          lastPlayAt: 0,
          lastStopAt: 0,
          ingestInFlight: false,
          ingestQueue: [],
          ingestWorkerActive: false,
          lastIngestAt: 0,
          bootstrap: null,
          bootstrapFetched: false,
          realtime: null,
          segmentQueue: [],
          segmentWorkerActive: false,
          segmentIndexes: { user: 0, assistant: 0 },
        };
        calls.set(newKey, callState);
      } else {
        callState.ingestQueue = callState.ingestQueue || [];
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
      try { rt.ws.send(JSON.stringify({ type: 'input_audio_buffer.commit' })); }
      catch (err) { log('error', '[REALTIME] commit failed', { callSid, err: err?.message }); }

      try { rt.ws.send(JSON.stringify({ type: 'response.create', response: { modalities: ['text', 'audio'] } })); }
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

    if (!payload.call_id && !payload.to_number) {
      log('warn', '[BOOTSTRAP] skipped: missing call_id and to_number', { callSid });
      return null;
    }

    try {
      const resp = await axios.post(
        `${LARAVEL_API_BASE}/api/voice/bootstrap`,
        payload,
        { headers: { Authorization: `Bearer ${APP_SHARED_TOKEN}` }, timeout: 10000 },
      );
      state.bootstrap = resp.data;
      log('info', '[BOOTSTRAP] fetched', { callSid, callId: state.callId, tenantId: state.tenantId });
    } catch (err) {
      log('error', '[BOOTSTRAP] failed', { callSid, err: err?.response?.data || err.message });
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

    const url = `${LARAVEL_API_BASE}/api/call-sessions/${encodeURIComponent(sessionId)}/segments`;
    const payload = {
      role: job.role,
      segment_index: job.segmentIndex,
      call_id: callState?.callId || null,
      call_sid: callSid || null,
      tenant_id: callState?.tenantId || tenantId || null,
      audio_b64: job.audioB64,
      metadata: {
        started_at: job.startedAt ? new Date(job.startedAt).toISOString() : null,
        ended_at: job.endedAt ? new Date(job.endedAt).toISOString() : null,
        duration_ms: job.durationMs || null,
        stream_sid: job.streamSid || streamSid || null,
        reason: job.reason || null,
        samples: job.samples || null,
      },
    };

    const headers = { 'Content-Type': 'application/json', Authorization: `Bearer ${APP_SHARED_TOKEN}` };

    for (let attempt = 1; attempt <= 3; attempt++) {
      try {
        await axios.post(url, payload, { headers, timeout: 10000 });
        log('info', '[SEGMENT] posted', { callSid, role: job.role, idx: job.segmentIndex, attempt });
        return;
      } catch (err) {
        const errData = err?.response?.data || err.message;
        log('warn', '[SEGMENT] post failed', { callSid, role: job.role, idx: job.segmentIndex, attempt, err: errData });
        if (attempt < 3) await new Promise((res) => setTimeout(res, attempt * 500));
      }
    }
  }

  async function ensureRealtimeSession() {
    const state = ensureCallState();
    if (!state?.bootstrap?.config?.realtime_enabled) return null;

    if (!OPENAI_API_KEY) {
      log('warn', '[REALTIME] skipped: missing OPENAI_API_KEY', { callSid });
      return null;
    }

    if (state.realtime?.ready && state.realtime.ws?.readyState === WebSocket.OPEN) return state.realtime;

    const config = state.bootstrap.config || {};
    const model = config.realtime_model || 'gpt-4o-realtime-preview';
    const rtUrl = `wss://api.openai.com/v1/realtime?model=${encodeURIComponent(model)}`;

    const rtWs = new WebSocket(rtUrl, {
      headers: { Authorization: `Bearer ${OPENAI_API_KEY}` },
    });

    state.realtime = { ws: rtWs, ready: false, keepAlive: null };

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
        log('error', '[REALTIME] event error', { callSid, err: evt?.error || evt?.message || evt });
      }
    });

    rtWs.on('close', () => {
      finalizeAssistantSegment('realtime-close');
      log('info', '[REALTIME] session closed', { callSid });
      closeRealtime(state);
    });

    rtWs.on('error', (err) => {
      log('error', '[REALTIME] socket error', { callSid, err: err?.message });
    });

    return state.realtime;
  }

  async function drainIngestQueue() {
    if (!callState || callState.ingestWorkerActive) return;
    callState.ingestWorkerActive = true;
    callState.ingestQueue = callState.ingestQueue || [];
    while (callState.ingestQueue.length) {
      const job = callState.ingestQueue.shift();
      try {
        await sendIngestJob(job);
      } catch (err) {
        log('error', '[INGEST] queue job failed', { callSid, err: err?.message });
      }
    }
    callState.ingestWorkerActive = false;
  }

  function enqueueIngestJob(job) {
    if (!callState) return;
    callState.ingestQueue = callState.ingestQueue || [];
    callState.ingestQueue.push(job);
    callState.lastIngestAt = Date.now();
    drainIngestQueue();
  }

  async function sendIngestJob(job = {}) {
    if (!callState || !job.wavBuf) return;
    const jobStreamSid = job.streamSid || streamSid;
    const turnNumber = job.turnNumber || turns;
    const wavBuf = job.wavBuf;

    const t0 = Date.now();
    callState.ingestInFlight = true;
    log('info', '[INGEST] sending', { callSid, turn: turnNumber, wavBytes: wavBuf.length });

    try {
      const resp = await axios.post(
        `${LARAVEL_API_BASE}/api/turns/ingest`,
        {
          tenant_id: callState.tenantId,
          call_sid: callSid,
          encoding: 'audio/wav;rate=8000',
          audio_b64: wavBuf.toString('base64'),
          meta: { streamSid: jobStreamSid },
        },
        { headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${APP_SHARED_TOKEN}` }, timeout: 30000 }
      );
      const { ok, audio_url } = resp.data || {};
      const dt = Date.now() - t0;
      log('info', '[INGEST] response', { callSid, turn: turnNumber, ok, audio_url, ms: dt });

      const playWindowOk = (Date.now() - callState.lastPlayAt) > PLAY_LOCK_MS;
      if (ok && audio_url && !callState.playing && playWindowOk) {
        try {
          await axios.post(
            `${LARAVEL_API_BASE}/api/twilio/calls/play`,
            { tenant_id: callState.tenantId, call_sid: callSid, audio_url },
            { headers: { Authorization: `Bearer ${APP_SHARED_TOKEN}` }, timeout: 8000 }
          );
          callState.playing = true;
          callState.lastPlayAt = Date.now();
          log('info', '[PLAY] requested', { callSid, audio_url });
          setTimeout(() => { callState.playing = false; }, PLAY_LOCK_MS);
        } catch (e) {
          log('warn', '[PLAY] request failed (ignored)', { callSid, err: e?.response?.data || e.message });
        }
      }
    } catch (e) {
      log('error', '[INGEST] error', { callSid, err: e?.response?.data || e.message });
    } finally {
      callState.ingestInFlight = false;
    }
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
          if (item?.name === 'call_id') callId = Number.parseInt(item.value, 10) || callId;
          if (item?.name === 'to_number') toNumber = item.value || toNumber;
        }
      } else if (cp && typeof cp === 'object') {
        tenantId = cp.tenant_id || tenantId;
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

      // pre-roll
      preRollMu.push(mu);
      if (preRollMu.length > MAX_PREROLL) preRollMu.shift();

      // energy
      const energy = rmsPcm16(pcm8);
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

      // hysteresis counters
      if (energy >= RMS_ON) {
        speechOnCount++;
        speechOffCount = 0;
      } else if (energy <= RMS_OFF) {
        speechOffCount++;
        if (speechOnCount > 0) speechOnCount--;
      } else {
        if (speechOnCount > 0) speechOnCount--;
      }

      // barge-in
      if (callState.playing && speechOnCount >= SPEECH_ON_FRAMES && (now - callState.lastStopAt) > STOP_COOLDOWN_MS) {
        try {
          await axios.post(
            `${LARAVEL_API_BASE}/api/twilio/calls/stop`,
            { tenant_id: callState.tenantId, call_sid: callSid },
            { headers: { Authorization: `Bearer ${APP_SHARED_TOKEN}` }, timeout: 5000 }
          );
          callState.lastStopAt = now;
          callState.playing = false;

          state = 'SPEAKING';
          hadSpeechSinceFlush = true;
          speechStartedAt = lastSpeechAt = now;
          captureMu = preRollMu.slice();
          log('info', '[WS] barge-in: requested stop', { callSid });
        } catch (_) { /* ignore */ }
        return;
      }

      // VAD state machine
      if (state === 'IDLE') {
        if (speechOnCount >= SPEECH_ON_FRAMES && !callState.playing) {
          state = 'SPEAKING';
          hadSpeechSinceFlush = true;
          speechStartedAt = lastSpeechAt = now;
          captureMu = preRollMu.slice();
          log('info', '[VAD] speaking start', { callSid });
        }
      } else if (state === 'SPEAKING') {
        captureMu.push(mu);
        if (energy >= RMS_ON) lastSpeechAt = now;

        const speakingMs = now - speechStartedAt;
        const silentForS = (now - lastSpeechAt) / 1000;

        if (hadSpeechSinceFlush &&
            speakingMs >= MIN_CHUNK_MS &&
            speechOffCount >= SPEECH_OFF_FRAMES &&
            silentForS >= SILENCE_SECS &&
            (now - callState.lastIngestAt) >= CHUNK_COOLDOWN_MS) {

          log('info', '[VAD] speaking end', { callSid, speakingMs, capturedFrames: captureMu.length });

          // build WAV from μ-law frames
          const pcmFrames = captureMu.map(decodeMulawToPCM16);
          const pcmBuf = Buffer.concat(pcmFrames);
          const wavBuf = pcm16ToWav(pcmBuf, 8000, 1);

          // reset for next turn
          captureMu = [];
          hadSpeechSinceFlush = false;
          state = 'IDLE';
          speechOnCount = 0;
          speechOffCount = 0;
          speechStartedAt = 0;
          lastSpeechAt = 0;

          turns++;
          enqueueIngestJob({ wavBuf, streamSid, turnNumber: turns });
        }
      }
      return;
    }

    if (data.event === 'stop') {
      connActive = false;
      finalizeUserSegment('twilio-stop');
      finalizeAssistantSegment('twilio-stop');
      try { ws.close(); } catch {}
      log('info', '[WS] stop received', { callSid });
      return;
    }
  });

  ws.on('close', () => {
    connActive = false;
    finalizeUserSegment('socket-close');
    finalizeAssistantSegment('socket-close');
    const key = getCallKey();
    if (key && calls.get(key)?.ws === ws) {
      calls.get(key).ws = null;
    }
    closeRealtime(callState);
    log('info', '[WS] closed', { callSid, chunksIn, bytesIn, turns });
  });

  ws.on('error', (err) => {
    log('error', '[WS] socket error', { callSid, err: err?.message });
  });
}

server.listen(PORT, () => log('info', 'WS proxy listening', { port: PORT }));
