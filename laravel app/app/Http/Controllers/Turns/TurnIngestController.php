<?php

namespace App\Http\Controllers\Turns;

use App\Http\Controllers\Controller;
use App\Models\AudioAsset;
use App\Models\CallSession;
use App\Models\ErrorLogs;
use App\Models\SttJob;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\OpenAiSetting;
use App\Services\IntentEngine;
// ElevenLabs
use App\Models\ElevenLabs;
use App\Models\TtsRenders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;



use Illuminate\Support\Facades\DB;
use Storage;


class TurnIngestController extends Controller
{
    /** Minimal bearer check for WS -> Laravel */
    private function assertBearer(Request $request): void
    {
        $shared = config('app.shared_token', env('APP_SHARED_TOKEN'));
        if ($request->bearerToken() !== $shared) {
            abort(401, 'Unauthorized');
        }
    }

    private function unwrapJsonContent(?string $content): string
    {
        $trimmed = trim($content ?? '');
        if ($trimmed === '') {
            return '';
        }

        if (Str::startsWith($trimmed, '```')) {
            $trimmed = preg_replace('/^```[a-zA-Z]*\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/```$/', '', $trimmed) ?? $trimmed;
        }

        return trim($trimmed);
    }

    public function health()
    {
        return response()->json(['ok' => true, 'ts' => now()->toIso8601String()]);
    }

   public function wsHealth()
{
    $hostSetting = SystemSetting::where('key', 'proxy_host')->first();
    $portSetting = SystemSetting::where('key', 'proxy_port')->first();

    $host = $hostSetting?->value;
    $port = $portSetting?->value ?? 443;

    $status = 'Offline';
    $activeCalls = 0;

    if ($host) {
        try {
            // Normalize to ensure clean URL
            $normalizedHost = str_replace(['wss://', 'https://', 'http://'], '', $host);
            $url = "https://{$normalizedHost}/ws-status";

            $response = Http::timeout(5)->withoutVerifying()->get($url);

            if ($response->ok()) {
                $data = $response->json();
                $status = ($data['ws_server_running'] ?? false) ? 'Server Running' : 'Offline';
                $activeCalls = $data['ws_active_calls'] ?? 0;
            }
        } catch (\Exception $e) {
            \Log::error("WebSocket Health Check Failed: " . $e->getMessage());
        }
    }

    return response()->json([
        'status' => $status,
        'active_calls' => $activeCalls,
    ]);
}



    // $this->assertBearer($request);



    public function ingest(Request $request)
    {
        // $this->assertBearer($request);

        $logError = function (?string $tenantId, string $scope, string $severity, string $message, array $context = []) {
            ErrorLogs::create([
                'tenant_id' => $tenantId,
                'scope' => $scope,
                'severity' => $severity,
                'message' => $message,
                'context' => $context,
                'occurred_at' => now(),
            ]);
        };

        Log::info('INGEST: request received', [
            'content_type' => $request->header('Content-Type'),
            'route' => $request->path(),
        ]);

        // 0) Parse JSON safely
        $incoming = $request->all();
        if (empty($incoming)) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $logError(null, 'Request', 'error', 'Bad JSON received', ['raw' => $raw]);
                return response()->json(['ok' => false, 'step' => 'receive', 'code' => 'bad_json'], 400);
            }
            $request->merge($decoded);
        }

        // 1) Validate
        $data = $request->validate([
            'tenant_id' => 'required|string',
            'call_sid' => 'required|string',
            'encoding' => 'required|string',
            'audio_b64' => 'required|string',
            'from_number' => 'nullable|string',
            'to_number' => 'nullable|string',
            'meta' => 'array',
            'speaker' => 'nullable|in:user,bot',
        ]);

        $speaker = $data['speaker'] ?? 'user';

        // 2) Tenant lookup
        $tenant = Tenant::find($data['tenant_id']);
        if (!$tenant) {
            $logError($data['tenant_id'], 'Tenant', 'error', 'Tenant not found', ['call_sid' => $data['call_sid'] ?? null]);
            return response()->json(['ok' => false, 'step' => 'tenant', 'code' => 'tenant_not_found'], 404);
        }

        // 3) Prepare file paths
        $callSid = preg_replace('/[^A-Za-z0-9]/', '', $data['call_sid']);
        $stamp = now()->format('Ymd_Hisv') . '_' . Str::random(6);
        $userFileRel = "calls/{$callSid}/incoming/{$stamp}.wav";
        $assistantDirRel = "calls/{$callSid}/outgoing";
        Storage::disk('public')->makeDirectory("calls/{$callSid}/incoming");
        Storage::disk('public')->makeDirectory($assistantDirRel);

        // Get the full URL for the outgoing directory
        $baseUrl = config('app.url'); // Assuming 'app.url' is set in your config
        $assistantDirFullUrl = "{$baseUrl}/storage/{$assistantDirRel}";

        // 4) Decode + store incoming audio
        $raw = base64_decode($data['audio_b64'], true);
        if ($raw === false || strlen($raw) < 10) {
            $logError($tenant->id, 'Audio', 'error', 'Invalid audio base64', ['call_sid' => $callSid]);
            return response()->json(['ok' => false, 'step' => 'audio', 'code' => 'bad_audio'], 400);
        }
        $saved = Storage::disk('public')->put($userFileRel, $raw);
        if (!$saved) {
            $logError($tenant->id, 'Storage', 'error', 'Failed to save incoming audio', ['path' => $userFileRel]);
            return response()->json(['ok' => false, 'step' => 'storage', 'code' => 'save_failed'], 500);
        }
        $userAudioAsset = AudioAsset::create([
            'tenant_id' => $tenant->id,
            'kind' => 'upload_chunk',
            'speaker' => $speaker,
            'storage_disk' => 'public',
            'path' => $userFileRel,
            'mime' => 'audio/wav',
            'size_bytes' => strlen($raw),
            'checksum' => hash('sha256', $raw),
        ]);

        // 5) Upsert CallSession
        $call = CallSession::firstOrNew(['call_sid' => $callSid]);
        $call->tenant_id = $tenant->id;
        $call->from_number = $data['from_number'] ?? ($data['meta']['caller_number'] ?? null);
        $call->to_number = $data['to_number'] ?? ($data['meta']['to_number'] ?? null);
        $call->status = 'active';
        $call->direction = $call->direction ?? 'inbound';
        if (!$call->exists)
            $call->started_at = now();
        $call->meta = array_merge(is_array($call->meta) ? $call->meta : [], $data['meta'] ?? [], ['wav_path' => $userFileRel]);
        $call->save();

        // 6) Load OpenAI settings
        $openAiSetting = OpenAiSetting::where('tenant_id', $tenant->id)->first();
        $openaiKey = $openAiSetting->api_key_encrypted;
        if (!$openaiKey) {
            $logError($tenant->id, 'OpenAI', 'error', 'Missing OpenAI API key');
            return response()->json(['ok' => false, 'error' => 'missing_openai_key'], 500);
        }
        $whisperModel = $openAiSetting?->stt_model ?? 'gpt-4o-mini-transcribe';
        $chatModel = $openAiSetting?->default_model ?? 'gpt-4o-mini';
        $systemPrompt = $openAiSetting?->instructions ?? "You are a concise, friendly IVR assistant.";
        return DB::transaction(function () use ($userFileRel, $data, $call, $openaiKey, $whisperModel, $chatModel, $systemPrompt, $tenant, $assistantDirFullUrl, $assistantDirRel, $userAudioAsset, $logError, $speaker) {
            $userAbsPath = Storage::disk('public')->path($userFileRel);

            // 7) STT
            try {
                $sttStart = microtime(true);
                $sttResp = Http::withToken($openaiKey)
                    ->withOptions(['verify' => false])
                    ->attach('file', file_get_contents($userAbsPath), 'audio.wav')
                    ->asMultipart()
                    ->post('https://api.openai.com/v1/audio/transcriptions', ['model' => $whisperModel]);

                if (!$sttResp->ok())
                    throw new \Exception('STT API failed: ' . $sttResp->body());
                $sttLatency = (int) round((microtime(true) - $sttStart) * 1000);
                $transcript = trim($sttResp->json('text') ?? '(no speech detected)');

                $sttJob = SttJob::create([
                    'tenant_id' => $tenant->id,
                    'model' => $whisperModel,
                    'input_audio_asset_id' => $userAudioAsset->id,
                    'mode' => 'batch',
                    'status' => 'completed',
                    'text' => $transcript,
                    'raw_response' => $sttResp->json(),
                    'started_at' => now()->subMilliseconds($sttLatency),
                    'completed_at' => now(),
                ]);

                $call->messages()->create([
                    'role' => 'user',
                    'tenant_id' => $tenant->id,

                    'text' => $transcript,
                    'latency_ms' => $sttLatency,
                    'stt_job_id' => $sttJob->id,
                    'audio_asset_id' => $userAudioAsset->id,
                    'started_at' => now()->subMilliseconds($sttLatency),
                    'completed_at' => now(),
                    'meta' => ['wav_path' => $userFileRel, 'encoding' => $data['encoding'], 'stt_model' => $whisperModel],
                ]);
            } catch (\Throwable $e) {
                $logError($tenant->id, 'STT', 'fatal', $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                return response()->json(['ok' => false, 'step' => 'stt', 'code' => 'exception'], 500);
            }

            // 8) LLM Reply via Intent Engine
            $intentEngine = app(IntentEngine::class);
            $intentResult = $intentEngine->analyze($call, $transcript);
            $replyText = $intentResult->replyText;

            // 9) TTS
            $elevenLabs = ElevenLabs::where('tenant_id', $tenant->id)->first();
            $mp3Rel = null;
            $ttsRender = null;
            $ttsLatency = 0;
            $ttsBase64 = null;
            if ($elevenLabs?->elevenlabs_api_key_encrypted && $elevenLabs?->elevenlabs_voice_id) {
                try {
                    $ttsStart = microtime(true);
                    $ttsReq = ['text' => $replyText, 'model_id' => 'eleven_multilingual_v2', 'voice_settings' => ['stability' => 0.4, 'similarity_boost' => 0.7]];
                    $ttsResp = Http::timeout(30)
                        ->withHeaders(['xi-api-key' => $elevenLabs->elevenlabs_api_key_encrypted, 'Accept' => 'audio/mp3', 'Content-Type' => 'application/json'])
                        ->withOptions(['verify' => false])
                        ->post("https://api.elevenlabs.io/v1/text-to-speech/{$elevenLabs->elevenlabs_voice_id}", $ttsReq);

                    $ttsLatency = (int) round((microtime(true) - $ttsStart) * 1000);

                    if ($ttsResp->ok() && strlen($ttsResp->body()) > 10) {
                        $fname = now()->format('Ymd_Hisv') . '_' . Str::random(6) . '.mp3';
                        $mp3Rel = "{$assistantDirFullUrl}/{$fname}";
                        $ttsBase64 = base64_encode($ttsResp->body());

                        file_put_contents(Storage::disk('public')->path($assistantDirRel."/".$fname), $ttsResp->body());

                        $aiAudioAsset = AudioAsset::create([
                            'tenant_id' => $tenant->id,
                            'kind' => 'tts_output',
                            'speaker' => 'bot',
                            'storage_disk' => 'public',
                            'path' => $mp3Rel,
                            'mime' => 'audio/mp3',
                            'size_bytes' => strlen($ttsResp->body()),
                            'checksum' => hash('sha256', $ttsResp->body()),
                        ]);

                        $ttsRender = TtsRenders::create([
                            'tenant_id' => $tenant->id,
                            'provider' => 'elevenlabs',
                            'voice_id' => $elevenLabs->elevenlabs_voice_id,
                            'model' => 'eleven_multilingual_v2',
                            'input_chars' => mb_strlen($replyText),
                            'audio_asset_id' => $aiAudioAsset->id,
                            'latency_ms' => $ttsLatency,
                            'status' => 'completed',
                            'started_at' => now()->subMilliseconds($ttsLatency),
                            'completed_at' => now(),
                            'raw_request' => $ttsReq,
                            'raw_response' => ['status' => $ttsResp->status()],
                        ]);
                    }
                } catch (\Throwable $e) {
                    $logError($tenant->id, 'TTS', 'fatal', $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                }
            }

            // 11) Store assistant message
            $call->messages()->create([
                'role' => 'assistant',
                'tenant_id' => $tenant->id,

                'text' => $replyText,
                'llm_run_id' => $intentResult->llmRunId,
                'tts_render_id' => $ttsRender?->id,
                'audio_asset_id' => $ttsRender?->audio_asset_id ?? null,
                'latency_ms' => $ttsLatency,
                'started_at' => now()->subMilliseconds($ttsLatency),
                'completed_at' => now(),
                'meta' => ['tts_url' => $mp3Rel, 'latency' => ['tts_ms' => $ttsLatency], 'model' => $chatModel],
            ]);

            // 12) Mark call completed
            $call->markCompleted('normal_flow');

            return response()->json([
                'type' => 'bot_reply',
                'text' => $replyText,
                'audio_b64' => $ttsBase64,
                'intent' => [
                    'name' => $intentResult->intentName,
                    'status' => $intentResult->intentStatus,
                    'slots' => $intentResult->slots,
                ],
                'meta' => [
                    'call_session_id' => $call->id,
                    'call_sid' => $call->call_sid,
                ],
            ]);
        });
    }
}