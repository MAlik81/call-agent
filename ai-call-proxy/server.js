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

// ---------- routes ----------
app.get('/', (_req, res) => res.send('<h1>AI Call Proxy — WS up</h1>'));
app.get('/ws-status', (_req, res) => {
  log('info', '[STATUS] ws-status check',LARAVEL_API_BASE);
  const wsRunning = !!wss && typeof wss.handleUpgrade === 'function';
  const activeCalls = Array.from(calls.values()).filter(c => c.ws !== null).length;
  res.json({ ws_server_running: wsRunning, ws_active_calls: activeCalls, LARAVEL_API_BASE:LARAVEL_API_BASE });
});

// ---------- upgrade -> ws ----------
server.on('upgrade', (request, socket, head) => {
  const { pathname, query } = url.parse(request.url, true);
  log('info', '[UPGRADE] request', { pathname, query });
  if (pathname === '/media-stream') {
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
  let callSid = null;
  let tenantId = 'unknown-tenant';

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

  // buffers
  const preRollMu = [];
  const MAX_PREROLL = 10; // ~200ms (μ-law 8kHz 20ms frames)
  let captureMu = [];

  // per-call shared state
  let callState = null;

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
      callSid   = data.start.callSid || ws.params?.call_sid || '(unknown)';

      const cp = data.start.customParameters;
      if (Array.isArray(cp)) {
        for (const item of cp) if (item?.name === 'tenant_id') tenantId = item.value;
      } else if (cp && typeof cp === 'object' && cp.tenant_id) {
        tenantId = cp.tenant_id;
      }

      // per-call dedupe
      callState = calls.get(callSid);
      if (!callState) {
        callState = {
          tenantId,
          ws: null,
          playing: false,
          lastPlayAt: 0,
          lastStopAt: 0,
          ingestInFlight: false,
          ingestQueue: [],
          ingestWorkerActive: false,
          lastIngestAt: 0,
        };
        calls.set(callSid, callState);
      } else {
        callState.tenantId = tenantId;
        if (callState.ws && callState.ws !== ws) {
          try { callState.ws.close(); } catch {}
        }
        callState.ingestQueue = callState.ingestQueue || [];
      }
      callState.ws = ws;

      log('info', '[WS] start', { streamSid, callSid, tenantId });
      return;
    }

    if (data.event === 'media') {
      if (!connActive || !callState) return;

      // μ-law frame
      const mu = Buffer.from(data.media.payload, 'base64');
      chunksIn++;
      bytesIn += mu.length;
      if (LOG_LEVEL >= LOG_LEVELS.debug && (++_frameCounter % LOG_FRAME_EVERY) === 0) {
        log('debug', '[WS] media frame', { callSid, chunk: chunksIn, muBytes: mu.length });
      }

      // pre-roll
      preRollMu.push(mu);
      if (preRollMu.length > MAX_PREROLL) preRollMu.shift();

      // energy
      const energy = rmsPcm16(decodeMulawToPCM16(mu));
      const now = Date.now();

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
      try { ws.close(); } catch {}
      log('info', '[WS] stop received', { callSid });
      return;
    }
  });

  ws.on('close', () => {
    connActive = false;
    if (callSid && calls.get(callSid)?.ws === ws) {
      calls.get(callSid).ws = null;
    }
    log('info', '[WS] closed', { callSid, chunksIn, bytesIn, turns });
  });

  ws.on('error', (err) => {
    log('error', '[WS] socket error', { callSid, err: err?.message });
  });
}

server.listen(PORT, () => log('info', 'WS proxy listening', { port: PORT }));
