#!/usr/bin/env node

// Required modules
const WebSocket = require('ws');
const http = require('http');
const fetch = require('node-fetch');  // or built-in fetch in newer Node versions

// Configuration constants
const PORT = process.env.PORT || 3001;  // Port for the WebSocket server
const OPENAI_API_KEY = process.env.OPENAI_API_KEY || '<YOUR_OPENAI_API_KEY>';
const LARAVEL_ENDPOINT = process.env.LARAVEL_ENDPOINT || 'https://your-backend.example/api/call-segment';
const OPENAI_MODEL   = 'gpt-realtime';    // OpenAI realtime model
const OPENAI_VOICE   = 'alloy';           // Voice for assistant (OpenAI voice option)
const SILENCE_TIMEOUT_MS = 800;          // 800ms silence triggers end of user turn
const VAD_SILENCE_THRESHOLD = 1000;      // Energy threshold for voice detection (adjust as needed)

// Initialize an HTTP server (needed to attach WebSocket server)
const server = http.createServer();
const wss = new WebSocket.Server({ server });

// Helper: μ-law decode (8-bit unsigned μ-law to 16-bit PCM)
// Using ITU G.711 spec formula: t = (((ulaw & 0x0F) << 3) + 132) << ((ulaw & 0x70) >> 4); 
// output = (ulaw & 0x80) ? (132 - t) : (t - 132);
function muLawDecodeByte(uByte) {
    const u = (~uByte) & 0xFF;  // bitwise complement to get normal μ-law value
    const t = (((u & 0x0F) << 3) + 0x84) << ((u & 0x70) >> 4);  // 0x84 = 132 bias
    const sample = (u & 0x80) ? (0x84 - t) : (t - 0x84);
    // Return as 16-bit signed integer
    return sample < -32768 ? -32768 : (sample > 32767 ? 32767 : sample);
}

// Helper: μ-law encode (16-bit PCM to 8-bit μ-law)
function muLawEncodeSample(sample) {
    const ULAW_BIAS = 0x84;  // bias = 132
    let mask;
    // Apply bias and mask sign
    if (sample < 0) {
        sample = (ULAW_BIAS - sample - 1) | 0;  // |0 to ensure 32-bit math
        mask = 0x7F;
    } else {
        sample = (ULAW_BIAS + sample) | 0;
        mask = 0xFF;
    }
    // Determine segment (exponent) 
    let seg = 0;
    let value = sample;
    while (value > 0xFF && seg < 8) {  // 0xFF = 255
        value >>= 1;
        seg++;
    }
    // Construct u-law byte from sign, segment, and mantissa, then XOR with mask
    let uVal;
    if (seg >= 8) {
        // Value out of range, clamp to max (mask will handle sign)
        uVal = 0x7F ^ mask;
    } else {
        const mantissa = (sample >> (seg + 3)) & 0x0F;  // quantization bits
        uVal = ((seg << 4) | mantissa) ^ mask;
    }
    return uVal & 0xFF;
}

// Helper: Resample 8 kHz PCM to 16 kHz PCM (simple linear interpolation)
function upsampleTo16k(pcm8k) {
    const len8k = pcm8k.length;
    const pcm16k = new Int16Array(len8k * 2);
    for (let i = 0; i < len8k - 1; i++) {
        const s1 = pcm8k[i];
        const s2 = pcm8k[i + 1];
        pcm16k[2 * i]     = s1;
        // Insert interpolated sample between s1 and s2
        pcm16k[2 * i + 1] = ((s1 + s2) >> 1);
    }
    // Handle last sample: duplicate it
    if (len8k > 0) {
        const last = pcm8k[len8k - 1];
        pcm16k[2 * (len8k - 1)]     = last;
        pcm16k[2 * (len8k - 1) + 1] = last;
    }
    return pcm16k;
}

// Helper: Resample 16 kHz PCM to 8 kHz PCM (simple averaging downsampling)
function downsampleTo8k(pcm16k) {
    const len16k = pcm16k.length;
    const len8k = Math.floor(len16k / 2);
    const pcm8k = new Int16Array(len8k);
    for (let i = 0; i < len8k; i++) {
        // Average two samples to downsample
        const s1 = pcm16k[2 * i];
        const s2 = pcm16k[2 * i + 1];
        pcm8k[i] = ((s1 + s2) >> 1);
    }
    return pcm8k;
}

// Helper: Calculate simple volume level (mean absolute amplitude) for VAD
function calculateVolume(pcmSamples) {
    let sum = 0;
    for (let i = 0; i < pcmSamples.length; i++) {
        sum += Math.abs(pcmSamples[i]);
    }
    return (pcmSamples.length > 0) ? (sum / pcmSamples.length) : 0;
}

// Helper: Post a segment record to Laravel backend (async, no blocking of WS loop)
async function postSegmentToLaravel(segmentData) {
    try {
        const res = await fetch(LARAVEL_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(segmentData)
        });
        if (!res.ok) {
            console.error(`Failed to POST segment to Laravel (status ${res.status})`);
        }
    } catch (err) {
        console.error('Error posting segment to Laravel:', err);
    }
}

// WebSocket connection handler for Twilio Media Streams
wss.on('connection', (ws, req) => {
    // Optional: enforce a specific path if desired (e.g. "/media-stream")
    if (req.url && req.url !== '/media-stream') {
        console.warn('Rejected WS connection with invalid path:', req.url);
        ws.close();
        return;
    }
    console.log('🔗 New Twilio media stream connected.');
    let callSid = null;
    let streamSid = null;
    let tenantId = null;
    let callId = null;

    // VAD/turn tracking state
    let speaking = false;             // whether user is currently speaking (in a turn)
    let lastVoiceTs = 0;              // timestamp of last voice audio frame (ms relative to call start)
    let segmentStartTs = 0;           // timestamp when current user segment started
    let silenceTimer = null;         // timer to commit after silence

    // Buffers for audio
    let userPcm16kBuffer = [];       // chunks of user PCM16 audio for the current segment
    let assistantPcm16kBuffer = [];  // chunks of assistant PCM16 audio for the current segment
    let assistantSamplesSent = 0;    // count of 8kHz samples sent to Twilio for current assistant segment

    // OpenAI WebSocket setup
    const openAiWs = new WebSocket(`wss://api.openai.com/v1/realtime?model=${encodeURIComponent(OPENAI_MODEL)}`, {
        headers: { 'Authorization': `Bearer ${OPENAI_API_KEY}` }
    });

    // Configure OpenAI session once connected
    openAiWs.on('open', () => {
        console.log('✅ Connected to OpenAI Realtime API.');
        const sessionConfig = {
            type: 'session.update',
            session: {
                type: 'realtime',
                model: OPENAI_MODEL,
                output_modalities: ['audio'],
                audio: {
                    // Expect/produce raw PCM at 16 kHz
                    input:  { format: { type: 'audio/pcm', rate: 16000 }, 
                              turn_detection: { type: 'none' } },  // we'll handle VAD manually
                    output: { format: { type: 'audio/pcm', rate: 16000 }, voice: OPENAI_VOICE }
                },
                // You could add system instructions or tools here if needed
            }
        };
        openAiWs.send(JSON.stringify(sessionConfig));
    });

    // Handle messages from OpenAI (AI responses and events)
    openAiWs.on('message', (data) => {
        let msg;
        try {
            msg = JSON.parse(data);
        } catch (e) {
            console.error('⚠️ OpenAI message JSON parse error:', e);
            return;
        }
        switch (msg.type) {
            case 'session.updated':
                console.log('OpenAI session configured.');
                break;
            case 'input_audio_buffer.committed':
                // OpenAI acknowledged our commit of user audio
                console.log('🗸 User audio committed to conversation.');
                break;
            case 'conversation.item.created':
                // New conversation item created (could be user or assistant message)
                if (msg.item?.role === 'assistant') {
                    // Assistant message created (triggered by either auto or our response.create)
                    console.log('Assistant response started (conversation item created).');
                }
                break;
            case 'response.output_audio.delta':
                if (msg.delta) {
                    // Received a chunk of assistant audio (base64 PCM16 at 16 kHz)
                    const pcm16Chunk = Buffer.from(msg.delta, 'base64'); 
                    // Save this chunk to reconstruct full assistant PCM later
                    assistantPcm16kBuffer.push(pcm16Chunk);
                    // Downsample and encode to μ-law for Twilio
                    const pcm16Array = new Int16Array(pcm16Chunk.buffer, pcm16Chunk.byteOffset, pcm16Chunk.length / 2);
                    const pcm8Array = downsampleTo8k(pcm16Array);
                    // Encode each sample to μ-law byte
                    const outBuf = Buffer.alloc(pcm8Array.length);
                    for (let i = 0; i < pcm8Array.length; i++) {
                        outBuf[i] = muLawEncodeSample(pcm8Array[i]);
                    }
                    // Send to Twilio as a media message
                    const twilioMsg = {
                        event: 'media',
                        streamSid: streamSid,
                        media: { payload: outBuf.toString('base64') }
                    };
                    try {
                        ws.send(JSON.stringify(twilioMsg));
                        assistantSamplesSent += pcm8Array.length;  // count samples sent for duration
                    } catch (sendErr) {
                        console.error('Error sending media to Twilio:', sendErr);
                    }
                }
                break;
            case 'response.done':
                // Assistant has finished speaking
                console.log('🤖 Assistant response completed.');
                // Compute assistant segment timing and duration
                const startTime = segmentStartTs || lastVoiceTs;  // if we didn't set start (e.g., if user was silent), use lastVoiceTs
                // Duration in ms from sample count (at 8000 Hz)
                const assistantDurationMs = (assistantSamplesSent / 8) | 0;  // 8 samples per ms at 8kHz
                const endTime = startTime + assistantDurationMs;
                // Compile assistant audio base64 (16k PCM) for logging
                const assistantPcm16Full = Buffer.concat(assistantPcm16kBuffer);
                const assistantAudioB64 = assistantPcm16Full.toString('base64');
                // Post assistant segment to Laravel
                const segmentIdx = (msg.response?.id) ? msg.response.id : Date.now();  // use response id if available, else unique
                postSegmentToLaravel({
                    call_id: callId,
                    call_sid: callSid,
                    tenant_id: tenantId,
                    stream_sid: streamSid,
                    segment_index: segmentIdx,       // segment index (or use an incrementing counter per call)
                    role: 'assistant',
                    start_ms: startTime,
                    end_ms: endTime,
                    duration_ms: assistantDurationMs,
                    reason: 'assistant_completed',
                    audio: assistantAudioB64
                });
                // Reset assistant buffers and counter for next turn
                assistantPcm16kBuffer = [];
                assistantSamplesSent = 0;
                // After assistant finishes, we can accept user speech again (if call still ongoing)
                // (The `speaking` flag is already false since we ended the user turn on commit)
                break;
            case 'error':
                console.error('❌ OpenAI API error:', msg);
                break;
            default:
                // Other events (transcription, etc., which we did not request in this setup)
                // You might handle 'conversation.audio_transcription' if needed.
                // We ignore unneeded event types for brevity.
                // console.log('OpenAI event:', msg.type);
                break;
        }
    });

    openAiWs.on('close', () => {
        console.log('🔻 OpenAI WebSocket closed.');
    });
    openAiWs.on('error', (err) => {
        console.error('OpenAI WS error:', err);
    });

    // Handle incoming Twilio Media Stream messages
    ws.on('message', (data) => {
        let msg;
        try {
            msg = JSON.parse(data);
        } catch (e) {
            console.error('⚠️ Twilio message JSON parse error:', e);
            return;
        }
        switch (msg.event) {
            case 'start':
                // Stream started: capture metadata
                streamSid = msg.start.streamSid;
                callSid = msg.start.callSid;
                console.log(`🚀 Stream started: callSid=${callSid}, streamSid=${streamSid}`);
                // Twilio custom parameters (e.g., CallId, TenantId) if provided in <Stream>
                if (msg.start.customParameters) {
                    const params = msg.start.customParameters;
                    // Accept both lowercase or CamelCase keys depending on how TwiML provided them
                    if (params.callId || params.CallId) callId = params.callId || params.CallId;
                    if (params.tenantId || params.TenantId) tenantId = params.tenantId || params.TenantId;
                    console.log(`Custom params: callId=${callId}, tenantId=${tenantId}`);
                }
                break;
            case 'media':
                // Incoming audio frame from Twilio (μ-law, base64 payload)
                if (!openAiWs || openAiWs.readyState !== WebSocket.OPEN) {
                    // If OpenAI WS not ready, we cannot process audio (this should rarely happen after session open)
                    return;
                }
                const payload = msg.media.payload;  // base64 string of μ-law audio
                // Decode μ-law to PCM16
                const muBuf = Buffer.from(payload, 'base64');
                const pcm8k = new Int16Array(muBuf.length);
                for (let i = 0; i < muBuf.length; i++) {
                    pcm8k[i] = muLawDecodeByte(muBuf[i]);
                }
                // Upsample to 16 kHz PCM for OpenAI
                const pcm16k = upsampleTo16k(pcm8k);
                // VAD: compute volume of this frame
                const volume = calculateVolume(pcm8k);
                const timestamp = parseInt(msg.media.timestamp, 10);  // ms since stream start
                if (volume > VAD_SILENCE_THRESHOLD) {
                    // Voice detected in this frame
                    if (!speaking) {
                        // Start of a new user segment
                        speaking = true;
                        segmentStartTs = timestamp;
                        console.log(`🎤 User started speaking at ~${segmentStartTs} ms`);
                        userPcm16kBuffer = [];  // reset buffer for new segment
                    }
                    // Append audio to user buffer
                    userPcm16kBuffer.push(Buffer.from(pcm16k.buffer));
                    // Send audio to OpenAI (append to input buffer)
                    openAiWs.send(JSON.stringify({
                        type: 'input_audio_buffer.append',
                        audio: Buffer.from(pcm16k.buffer).toString('base64')
                    }));
                    // Update last voice timestamp and reset silence timer
                    lastVoiceTs = timestamp;
                    if (silenceTimer) {
                        clearTimeout(silenceTimer);
                        silenceTimer = null;
                    }
                    // Schedule a commit after SILENCE_TIMEOUT_MS of no speech
                    silenceTimer = setTimeout(() => {
                        // Timeout reached: no speech for SILENCE_TIMEOUT_MS
                        if (speaking) {
                            console.log(`⏳ No speech for ${SILENCE_TIMEOUT_MS}ms, committing user segment.`);
                            commitUserSegment(false);
                        }
                    }, SILENCE_TIMEOUT_MS);
                } else {
                    // Silence frame
                    if (speaking && !silenceTimer) {
                        // If user was speaking and this is the first silent frame after speech, 
                        // set a timer (this case is handled above by scheduling in voice branch).
                        silenceTimer = setTimeout(() => {
                            if (speaking) {
                                console.log(`⏳ Silence timeout, committing user segment.`);
                                commitUserSegment(false);
                            }
                        }, SILENCE_TIMEOUT_MS);
                    }
                    // If already waiting on a silenceTimer from previous voice frame, do nothing here.
                }
                break;
            case 'stop':
                // Stream stopped (call ended or <Stop> TwiML)
                console.log('📴 Twilio stream stopped. Closing connections.');
                // If user was in the middle of speaking, finalize that segment
                if (speaking) {
                    commitUserSegment(true);  // call ended mid-speech
                }
                // Close OpenAI connection
                if (openAiWs.readyState === WebSocket.OPEN) {
                    openAiWs.close();
                }
                break;
            case 'connected':
                // Twilio 'connected' event – protocol established (not usually needed to log)
                console.log('Twilio WS connected event received.');
                break;
            case 'mark':
            case 'dtmf':
                // Handle any other events as needed (mark messages, DTMF tones, etc.)
                console.log(`Received Twilio event: ${msg.event}`);
                break;
            default:
                console.log(`Received unknown event from Twilio: ${msg.event}`);
                break;
        }
    });

    ws.on('close', () => {
        console.log('🔌 Twilio WebSocket disconnected.');
        // Ensure cleanup
        if (openAiWs.readyState === WebSocket.OPEN) {
            openAiWs.close();
        }
        if (silenceTimer) clearTimeout(silenceTimer);
    });

    ws.on('error', (err) => {
        console.error('Twilio WS error:', err);
    });

    // Commit the current user segment and request OpenAI response
    function commitUserSegment(callEnded) {
        if (!speaking) return;
        speaking = false;
        // Clear any pending silence timer
        if (silenceTimer) {
            clearTimeout(silenceTimer);
            silenceTimer = null;
        }
        // Make sure we have audio to commit
        if (userPcm16kBuffer.length === 0) {
            console.warn('🟡 No audio in buffer to commit.');
            return;
        }
        // Send commit event to OpenAI (finalize user turn)
        try {
            openAiWs.send(JSON.stringify({ type: 'input_audio_buffer.commit' }));
        } catch (err) {
            console.error('Error sending commit to OpenAI:', err);
        }
        // If call is still active, request an assistant response
        if (!callEnded) {
            try {
                openAiWs.send(JSON.stringify({ 
                    type: 'response.create', 
                    response: { modalities: ['audio'] } 
                }));
            } catch (err) {
                console.error('Error sending response.create to OpenAI:', err);
            }
        }
        // Determine segment timing and duration
        const startTime = segmentStartTs;
        const endTime = lastVoiceTs + 20;  // add ~20ms to include last frame
        const durationMs = endTime - startTime;
        // Compile user audio buffer to base64
        const userPcm16Full = Buffer.concat(userPcm16kBuffer);
        const userAudioB64 = userPcm16Full.toString('base64');
        // Post user segment info to Laravel
        postSegmentToLaravel({
            call_id: callId,
            call_sid: callSid,
            tenant_id: tenantId,
            stream_sid: streamSid,
            segment_index: Date.now(),  // or maintain a counter per call
            role: 'user',
            start_ms: startTime,
            end_ms: endTime,
            duration_ms: durationMs,
            reason: callEnded ? 'call_ended' : 'silence_timeout',
            audio: userAudioB64
        });
        console.log(`📝 Posted user segment: start=${startTime}ms, end=${endTime}ms, dur=${durationMs}ms, reason=${callEnded ? 'call_ended' : 'silence_timeout'}.`);
        // Reset user buffer for next segment
        userPcm16kBuffer = [];
        segmentStartTs = 0;
        lastVoiceTs = 0;
        // Note: we leave `assistantPcm16kBuffer` as is; it will be reset after assistant responds.
        // If callEnded, we expect no assistant response and will clean up connection.
    }
});

// Start the server
server.on('listening', () => {
    console.log(`📡 WebSocket server is listening on port ${PORT}`);
});
server.listen(PORT);
