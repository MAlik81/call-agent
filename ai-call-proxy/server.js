import Fastify from 'fastify';
import WebSocket from 'ws';
import dotenv from 'dotenv';
import fastifyFormBody from '@fastify/formbody';
import fastifyWs from '@fastify/websocket';
import axios from 'axios';

// Load environment variables from .env file
dotenv.config();

// ---- ENV / constants ------------------------------------------------
const {
  OPENAI_API_KEY: DEFAULT_OPENAI_API_KEY, // global fallback
  LARAVEL_API_BASE = 'https://mimivirtualagent.com',
} = process.env;

if (!DEFAULT_OPENAI_API_KEY && !LARAVEL_API_BASE) {
  console.error('Missing OPENAI_API_KEY and/or LARAVEL_API_BASE env vars.');
}

// Defaults if Laravel doesn’t override
const DEFAULT_SYSTEM_MESSAGE =
  'You are a helpful and bubbly AI assistant who loves to chat about anything the user is interested in.';
const DEFAULT_VOICE = 'alloy';
const DEFAULT_MODEL = 'gpt-realtime';
const TEMPERATURE = 0.8;
const INITIAL_GREETING_TEXT =
  process.env.INITIAL_GREETING_TEXT ||
  'Hello there! I am an AI voice assistant powered by Twilio and the OpenAI Realtime API. You can ask me for facts, jokes, or anything you can imagine. How can I help you?';
const ENABLE_INITIAL_GREETING =
  (process.env.ENABLE_INITIAL_GREETING || 'false').toLowerCase() === 'true';
const PORT = 3000;

// Events we log from Realtime
const LOG_EVENT_TYPES = [
  'error',
  'response.content.done',
  'rate_limits.updated',
  'response.done',
  'input_audio_buffer.committed',
  'input_audio_buffer.speech_stopped',
  'input_audio_buffer.speech_started',
  'session.created',
  'session.updated',
];

// Optional extra timing logs (leave false in prod)
const SHOW_TIMING_MATH = false;

// ---- Laravel helpers -------------------------------------------------

async function fetchBootstrap({ callSid, tenantId, callId }) {
  const url = `${LARAVEL_API_BASE}/api/voice/bootstrap`;
  const payload = {
    call_sid: callSid || null,
    tenant_id: tenantId || null,
    call_id: callId || null,
  };

  try {
    const { data } = await axios.post(url, payload, { timeout: 8000 });
    console.log('[BOOTSTRAP] fetched', {
      callSid,
      callId,
      tenantId,
      model: data?.model,
      voice: data?.realtime_voice,
    });

    return {
      apiKey: data?.openai_api_key || DEFAULT_OPENAI_API_KEY,
      model: data?.model || DEFAULT_MODEL,
      voice: data?.realtime_voice || DEFAULT_VOICE,
      systemMessage:
        data?.realtime_system_prompt ||
        data?.prompt ||
        DEFAULT_SYSTEM_MESSAGE,
      raw: data,
    };
  } catch (err) {
    console.error('[BOOTSTRAP] failed, falling back to defaults', {
      callSid,
      err: err?.response?.data || err.message,
    });
    return {
      apiKey: DEFAULT_OPENAI_API_KEY,
      model: DEFAULT_MODEL,
      voice: DEFAULT_VOICE,
      systemMessage: DEFAULT_SYSTEM_MESSAGE,
      raw: null,
    };
  }
}

async function postSegmentToLaravel(segment) {
  const url = `${LARAVEL_API_BASE}/api/call-segments`;

  // You can add auth headers if needed (e.g., internal token)
  try {
    await axios.post(url, segment, {
      timeout: 8000,
      headers: { 'Content-Type': 'application/json' },
    });
      console.log('[SEGMENT] posted', {
        role: segment.role,
        idx: segment.segment_index,
        dur: segment.duration_ms,
      });
  } catch (err) {
    console.error('[SEGMENT] post failed', {
      role: segment.role,
      idx: segment.segment_index,
      err: err?.response?.data || err.message,
    });
  }
}

// ---- Fastify & WS setup ----------------------------------------------

const fastify = Fastify();
fastify.register(fastifyFormBody);
fastify.register(fastifyWs);

// Simple health check
fastify.get('/', async (_request, reply) => {
  reply.send({ message: 'Twilio Media Stream Server is running!' });
});

// NO TwiML route here – your Laravel app returns the <Response> with <Connect><Stream>

// WebSocket route for Twilio <Stream>
fastify.register(async (fastifyInstance) => {
  fastifyInstance.get(
    '/media-stream',
    { websocket: true },
    (connection, req) => {
      console.log('🔗 New Twilio media stream connected.');

      // Per-connection state
      let streamSid = null;
      let latestMediaTimestamp = 0;

      // call / tenant identifiers
      let callSid = null;
      let callId = null;
      let tenantId = null;

      // OpenAI Realtime WS (created lazily after bootstrap)
      let openAiWs = null;
      let openAiReady = false;

      // derived config from Laravel
      let realtimeModel = DEFAULT_MODEL;
      let realtimeVoice = DEFAULT_VOICE;
      let systemMessage = DEFAULT_SYSTEM_MESSAGE;

      // Twilio barge-in tracking (from original sample)
      let lastAssistantItem = null;
      let markQueue = [];
      let responseStartTimestampTwilio = null;

      // Simple segment indexes
      let userSegmentIndex = 0;
      let assistantSegmentIndex = 0;

      // Buffers for logging audio back to Laravel (audio/pcmu)
      let currentUserBuffers = []; // Buffers of raw PCMU from Twilio
      let userSpeechActive = false;
      let userSpeechStartMs = null;

      let currentAssistantBuffers = []; // Buffers of raw PCMU from OpenAI
      let assistantResponseStartMs = null;

      // Helper: send a mark to Twilio to detect playback completion
      const sendMark = () => {
        if (!streamSid) return;
        const markEvent = {
          event: 'mark',
          streamSid,
          mark: { name: 'responsePart' },
        };
        connection.send(JSON.stringify(markEvent));
        markQueue.push('responsePart');
      };

      // Handle interruption when the caller starts speaking
      const handleSpeechStartedEvent = () => {
        // Original Twilio barge-in logic
        if (markQueue.length > 0 && responseStartTimestampTwilio != null) {
          const elapsedTime = latestMediaTimestamp - responseStartTimestampTwilio;
          if (SHOW_TIMING_MATH) {
            console.log(
              `Calculating elapsed time for truncation: ${latestMediaTimestamp} - ${responseStartTimestampTwilio} = ${elapsedTime}ms`,
            );
          }

          if (lastAssistantItem && openAiWs && openAiReady) {
            const truncateEvent = {
              type: 'conversation.item.truncate',
              item_id: lastAssistantItem,
              content_index: 0,
              audio_end_ms: elapsedTime,
            };
            if (SHOW_TIMING_MATH) {
              console.log(
                'Sending truncation event:',
                JSON.stringify(truncateEvent),
              );
            }
            openAiWs.send(JSON.stringify(truncateEvent));
          }

          connection.send(
            JSON.stringify({
              event: 'clear',
              streamSid,
            }),
          );

          // Reset
          markQueue = [];
          lastAssistantItem = null;
          responseStartTimestampTwilio = null;
        }

        // Start a new user segment for logging
        userSpeechActive = true;
        currentUserBuffers = [];
        userSpeechStartMs = latestMediaTimestamp;
      };

      // Flush user audio segment to Laravel
      const flushUserSegment = (reason = 'speech_stopped') => {
        if (!userSpeechActive || currentUserBuffers.length === 0) return;

        const buf = Buffer.concat(currentUserBuffers);
        const audioB64 = buf.toString('base64');
        const startMs = userSpeechStartMs ?? latestMediaTimestamp;
        const endMs = latestMediaTimestamp;
        const durationMs = Math.max(0, endMs - startMs);

        userSegmentIndex += 1;

        postSegmentToLaravel({
          call_id: callId,
          call_sid: callSid,
          tenant_id: tenantId,
          stream_sid: streamSid,
          role: 'user',
          segment_index: userSegmentIndex,
          format: 'audio/pcmu',
          audio_b64: audioB64,
          start_ms: startMs,
          end_ms: endMs,
          duration_ms: durationMs,
          reason,
        });

        currentUserBuffers = [];
        userSpeechActive = false;
        userSpeechStartMs = null;

        console.log(
          `📝 Posted user segment idx=${userSegmentIndex}, dur=${durationMs}ms, reason=${reason}`,
        );
      };

      // Flush assistant segment to Laravel
      const flushAssistantSegment = (reason = 'response_done') => {
        if (currentAssistantBuffers.length === 0) return;

        const buf = Buffer.concat(currentAssistantBuffers);
        const audioB64 = buf.toString('base64');
        const startMs = assistantResponseStartMs ?? latestMediaTimestamp;
        const endMs = latestMediaTimestamp;
        const durationMs = Math.max(0, endMs - startMs);

        assistantSegmentIndex += 1;

        postSegmentToLaravel({
          call_id: callId,
          call_sid: callSid,
          tenant_id: tenantId,
          stream_sid: streamSid,
          role: 'assistant',
          segment_index: assistantSegmentIndex,
          format: 'audio/pcmu',
          audio_b64: audioB64,
          start_ms: startMs,
          end_ms: endMs,
          duration_ms: durationMs,
          reason,
        });

        currentAssistantBuffers = [];
        assistantResponseStartMs = null;

        console.log(
          `📝 Posted assistant segment idx=${assistantSegmentIndex}, dur=${durationMs}ms, reason=${reason}`,
        );
      };

      // Optional: have the AI greet first
      const sendInitialConversationItem = () => {
        if (!openAiWs || openAiWs.readyState !== WebSocket.OPEN) return;

        const initialConversationItem = {
          type: 'conversation.item.create',
          item: {
            type: 'message',
            role: 'user',
            content: [
              {
                type: 'input_text',
                text: INITIAL_GREETING_TEXT,
              },
            ],
          },
        };

        openAiWs.send(JSON.stringify(initialConversationItem));
        openAiWs.send(JSON.stringify({ type: 'response.create' }));
      };

      // Create OpenAI Realtime WS *after* we have bootstrap config
      async function initOpenAiRealtime() {
        if (!realtimeModel || !systemMessage) {
          console.error(
            '[REALTIME] init called without model/systemMessage, using defaults.',
          );
        }

        // We expect that fetchBootstrap has set an API key on the instance via closure
        const apiKey = (await fetchBootstrap({
          callSid,
          tenantId,
          callId,
        }))?.apiKey;

        if (!apiKey) {
          console.error(
            '[REALTIME] No OpenAI API key from Laravel nor env, aborting.',
          );
          return;
        }

        const url = `wss://api.openai.com/v1/realtime?model=${encodeURIComponent(
          realtimeModel,
        )}&temperature=${TEMPERATURE}`;

        openAiWs = new WebSocket(url, {
          headers: { Authorization: `Bearer ${apiKey}` },
        });

        openAiWs.on('open', () => {
          console.log('✅ Connected to the OpenAI Realtime API', {
            model: realtimeModel,
            voice: realtimeVoice,
          });

          const sessionUpdate = {
            type: 'session.update',
            session: {
              type: 'realtime',
              model: realtimeModel,
              output_modalities: ['audio'],
              audio: {
                input: {
                  format: { type: 'audio/pcmu' }, // Twilio PCMU passthrough
                  turn_detection: { type: 'server_vad' },
                },
                output: {
                  format: { type: 'audio/pcmu' },
                  voice: realtimeVoice,
                },
              },
              instructions: systemMessage,
            },
          };

          console.log(
            '[REALTIME] Sending session.update:',
            JSON.stringify(sessionUpdate),
          );
          openAiWs.send(JSON.stringify(sessionUpdate));

          if (ENABLE_INITIAL_GREETING) {
            sendInitialConversationItem();
          }

          openAiReady = true;
        });

        openAiWs.on('message', (raw) => {
          let response;
          try {
            response = JSON.parse(raw.toString());
          } catch (e) {
            console.error('Error parsing OpenAI message:', e);
            return;
          }

          if (LOG_EVENT_TYPES.includes(response.type)) {
            console.log(`Received event: ${response.type}`, response);
          }

          // Realtime VAD events: map to our user segment boundaries
          if (response.type === 'input_audio_buffer.speech_started') {
            handleSpeechStartedEvent();
            return;
          }

          if (
            response.type === 'input_audio_buffer.speech_stopped' ||
            response.type === 'input_audio_buffer.committed'
          ) {
            flushUserSegment(response.type);
            return;
          }

          // Assistant audio chunks
          if (
            response.type === 'response.output_audio.delta' &&
            response.delta
          ) {
            const audioDelta = {
              event: 'media',
              streamSid,
              media: { payload: response.delta },
            };

            // Send to Twilio
            connection.send(JSON.stringify(audioDelta));

            // Start assistant timing when first audio arrives
            if (!assistantResponseStartMs) {
              assistantResponseStartMs = latestMediaTimestamp;
              if (SHOW_TIMING_MATH) {
                console.log(
                  `[ASSIST] start at timestamp=${assistantResponseStartMs}ms`,
                );
              }
            }

            // Buffer raw PCMU bytes for logging
            const pcmuBuf = Buffer.from(response.delta, 'base64');
            currentAssistantBuffers.push(pcmuBuf);

            // Track which assistant item this belongs to
            if (response.item_id) {
              lastAssistantItem = response.item_id;
            }

            // Send mark so Twilio can tell when playback completes
            sendMark();
            return;
          }

          // Assistant finished
          if (response.type === 'response.done') {
            flushAssistantSegment('response_done');
            return;
          }

          // Error
          if (response.type === 'error') {
            console.error('❌ OpenAI API error:', response);
          }
        });

        openAiWs.on('close', () => {
          console.log('Disconnected from the OpenAI Realtime API');
          openAiReady = false;
        });

        openAiWs.on('error', (err) => {
          console.error('Error in the OpenAI WebSocket:', err);
          openAiReady = false;
        });
      }

      // Handle messages from Twilio stream
      connection.on('message', async (message) => {
        let data;
        try {
          data = JSON.parse(message.toString());
        } catch (e) {
          console.error('Error parsing Twilio message:', e, 'Message:', message);
          return;
        }

        switch (data.event) {
          case 'start': {
            streamSid = data.start.streamSid;
            callSid = data.start.callSid;
            console.log('🚀 Stream started', { callSid, streamSid });

            // Custom params from TwiML <Parameter> (Laravel should set these)
            const params = data.start.customParameters || {};
            callId =
              params.call_id ||
              params.callId ||
              params.CallId ||
              params.CallID ||
              null;
            tenantId =
              params.tenant_id ||
              params.tenantId ||
              params.TenantId ||
              null;

            console.log('Custom params', { callId, tenantId });

            // Reset timing on new stream
            responseStartTimestampTwilio = null;
            latestMediaTimestamp = 0;

            // Fetch per-tenant config from Laravel and then init OpenAI
            const bootstrap = await fetchBootstrap({ callSid, tenantId, callId });
            if (bootstrap) {
              realtimeModel = bootstrap.model || DEFAULT_MODEL;
              realtimeVoice = bootstrap.voice || DEFAULT_VOICE;
              systemMessage = bootstrap.systemMessage || DEFAULT_SYSTEM_MESSAGE;
            }

            await initOpenAiRealtime();
            break;
          }

          case 'media': {
            latestMediaTimestamp = data.media.timestamp;
            if (SHOW_TIMING_MATH) {
              console.log(
                `Received media frame, timestamp=${latestMediaTimestamp}ms`,
              );
            }

            if (!openAiWs || !openAiReady) {
              // We haven't finished OpenAI handshake yet; drop frame
              return;
            }

            // Append Twilio PCMU base64 directly to Realtime
            const audioAppend = {
              type: 'input_audio_buffer.append',
              audio: data.media.payload,
            };
            openAiWs.send(JSON.stringify(audioAppend));

            // Buffer raw PCMU for potential user segment logging
            if (userSpeechActive) {
              const buf = Buffer.from(data.media.payload, 'base64');
              currentUserBuffers.push(buf);
            }

            break;
          }

          case 'mark': {
            if (markQueue.length > 0) {
              markQueue.shift();
            }
            break;
          }

          case 'stop': {
            console.log('📴 Twilio stream stopped. Closing connections.');

            // Flush any ongoing user segment
            flushUserSegment('call_end');
            flushAssistantSegment('call_end');

            if (openAiWs && openAiWs.readyState === WebSocket.OPEN) {
              openAiWs.close();
            }
            break;
          }

          case 'connected': {
            console.log('Twilio WS connected event received.');
            break;
          }

          default:
            console.log('Received non-media event:', data.event);
            break;
        }
      });

      // On client disconnect
      connection.on('close', () => {
        console.log('🔌 Twilio WebSocket disconnected.');
        if (openAiWs && openAiWs.readyState === WebSocket.OPEN) {
          openAiWs.close();
        }
      });

      connection.on('error', (err) => {
        console.error('Twilio WS error:', err);
      });
    },
  );
});

// Start server
fastify.listen({ port: PORT, host: '0.0.0.0' }, (err) => {
  if (err) {
    console.error(err);
    process.exit(1);
  }
  console.log(`📡 Media Stream server listening on port ${PORT}`);
});
