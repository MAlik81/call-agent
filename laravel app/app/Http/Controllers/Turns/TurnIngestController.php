<?php

namespace App\Http\Controllers\Turns;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AudioAsset;
use App\Models\CallSession;
use App\Models\ErrorLogs;
use App\Models\LlmRun;
use App\Models\SttJob;
use App\Models\SystemSetting;
use App\Models\TempAppointment;
use App\Models\Tenant;
use App\Models\OpenAiSetting;
// ElevenLabs
use App\Models\ElevenLabs;
use App\Models\TtsRenders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Carbon\Carbon;



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
        ]);

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

        $structuredResponseInstruction = <<<PROMPT
Reply using JSON with the following shape:
{
  "reply": "<concise spoken response>",
  "appointment": {
    "customer_name": "<name or null>",
    "appointment_date": "<YYYY-MM-DD or null>",
    "appointment_time": "<HH:MM or null>"
  }
}
If there is no appointment intent, set "appointment" to null. Keep "reply" under 50 words and make it sound natural for text-to-speech.
PROMPT;

        return DB::transaction(function () use ($userFileRel, $data, $call, $openaiKey, $whisperModel, $chatModel, $systemPrompt, $tenant, $assistantDirFullUrl, $assistantDirRel, $userAudioAsset, $logError, $structuredResponseInstruction) {
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

            // 8) LLM Reply
            $llmRun = null;
            $replyText = $systemPrompt ?? 'Sorry, I could not process your request.';
            $appointmentData = null;

            try {
                $llmResp = Http::timeout(30)
                    ->withToken($openaiKey)
                    ->withOptions(['verify' => false])
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $chatModel,
                        'temperature' => 0.6,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'system', 'content' => $structuredResponseInstruction],
                            ['role' => 'user', 'content' => $transcript],
                        ],
                        'max_tokens' => 220,
                    ]);

                $llmJson = $llmResp->ok() ? $llmResp->json() : [];
                $rawContent = trim(data_get($llmJson, 'choices.0.message.content', ''));
                $cleanContent = $this->unwrapJsonContent($rawContent);
                $structured = json_decode($cleanContent, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($structured)) {
                    $replyText = trim($structured['reply'] ?? $replyText);
                    $appointmentData = $structured['appointment'] ?? null;
                } else {
                    $replyText = $rawContent !== '' ? $rawContent : $replyText;
                }

                // ✅ Save LlmRun outside transaction so it always persists

                try {
                    $llmRun = LlmRun::create([
                        'tenant_id' => $tenant->id,

                        'model' => $chatModel,
                        'status' => $llmResp->ok() ? 'completed' : 'failed',
                        'input_tokens' => data_get($llmJson, 'usage.prompt_tokens', 0),
                        'output_tokens' => data_get($llmJson, 'usage.completion_tokens', 0),
                        'tool_calls' => data_get($llmJson, 'choices.0.message.tool_calls', []),
                        'temperature' => 0.6,
                        'system_prompt_snapshot' => $systemPrompt,
                        'raw_request' => [
                            'messages' => [
                                ['role' => 'system', 'content' => $systemPrompt],
                                ['role' => 'system', 'content' => $structuredResponseInstruction],
                                ['role' => 'user', 'content' => $transcript],
                            ],
                            'model' => $chatModel,
                            'max_tokens' => 220,
                        ],
                        'raw_response' => $llmJson,
                        'latency_ms' => 0,
                        'started_at' => now(),
                        'completed_at' => now(),
                    ]);

                } catch (\Throwable $e) {
                    Log::error('Failed to save LlmRun', [
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            } catch (\Throwable $e) {
                $logError($tenant->id, 'LLM', 'fatal', $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            }

            // 9) Appointment Detection & Booking
            $appointmentIntent = is_array($appointmentData) && (
                !empty($appointmentData['customer_name']) ||
                !empty($appointmentData['appointment_date']) ||
                !empty($appointmentData['appointment_time'])
            );

            if ($appointmentIntent) {
                $temp = TempAppointment::firstOrNew([
                    'call_sid' => $call->call_sid,
                ]);

                // ✅ Step 1: Ask for Name
                if (empty($temp->customer_name)) {
                    if (!empty($appointmentData['customer_name'])) {
                        $temp->customer_name = $appointmentData['customer_name'];
                        $temp->save();
                    } else {
                        $replyText = "Can I have your name for the appointment?";
                        return;
                    }
                }

                // ✅ Step 2: Ask for Date
                if (empty($temp->appointment_date)) {
                    if (!empty($appointmentData['appointment_date'])) {
                        $temp->appointment_date = $appointmentData['appointment_date'];
                        $temp->save();
                    } else {
                        $replyText = "On which date would you like the appointment?";
                        return;
                    }
                }

                // ✅ Step 3: Ask for Time
                if (empty($temp->appointment_time)) {
                    if (!empty($appointmentData['appointment_time'])) {
                        $temp->appointment_time = $appointmentData['appointment_time'];
                        $temp->save();
                    } else {
                        $replyText = "What time works best for you?";
                        return;
                    }
                }
                // ✅ Step 4: All info gathered → Check Appointment & Google Calendar
                if ($temp->customer_name && $temp->appointment_date && $temp->appointment_time) {
                    try {
                        // 🔹 First check directly in Appointments (confirmed bookings only)
                        $conflict = Appointment::whereDate('start_at', $temp->appointment_date)
                            ->whereTime('start_at', $temp->appointment_time)
                            ->where('status', 'confirmed')
                            ->exists();

                        if ($conflict) {
                            // Fetch all confirmed appointments for that day
                            $bookedSlots = Appointment::whereDate('start_at', $temp->appointment_date)
                                ->where('status', 'confirmed')
                                ->orderBy('start_at')
                                ->get(['client_name', 'start_at']);

                            $slotsList = $bookedSlots->map(function ($a) {
                                return $a->start_at->format('H:i') . " ({$a->client_name})";
                            })->implode(', ');

                            $replyText = "❌ Sorry {$temp->customer_name}, {$temp->appointment_date} at {$temp->appointment_time} is already booked.
📅 Booked slots for that day: {$slotsList}.
Please choose another time.";

                            $temp->status = 'pending';
                            $temp->save();

                            return;
                        }

                        // 🔹 If slot free in Appointments, check in Google Calendar
                        $jsonPath = storage_path("app/{$tenant->google_calendar_json_path}");
                        if (!file_exists($jsonPath)) {
                            throw new \Exception("Google Calendar JSON missing for tenant {$tenant->id}");
                        }

                        $client = new \Google\Client();
                        $client->setAuthConfig($jsonPath);
                        $client->addScope(\Google\Service\Calendar::CALENDAR);

                        $service = new \Google\Service\Calendar($client);
                        $calendarId = 'primary';

                        $startDateTime = Carbon::parse("{$temp->appointment_date} {$temp->appointment_time}:00");
                        $endDateTime = $startDateTime->copy()->addHour();

                        $events = $service->events->listEvents($calendarId, [
                            'timeMin' => $startDateTime->toRfc3339String(),
                            'timeMax' => $endDateTime->toRfc3339String(),
                            'singleEvents' => true,
                        ]);

                        if (count($events->getItems()) === 0) {
                            // ✅ Slot free → Create event in Google Calendar
                            $event = new \Google\Service\Calendar\Event([
                                'summary' => "Appointment: {$temp->customer_name}",
                                'start' => [
                                    'dateTime' => $startDateTime->toRfc3339String(),
                                    'timeZone' => config('app.timezone'),
                                ],
                                'end' => [
                                    'dateTime' => $endDateTime->toRfc3339String(),
                                    'timeZone' => config('app.timezone'),
                                ],
                            ]);

                            $createdEvent = $service->events->insert($calendarId, $event);

                            $appointment = Appointment::create([
                                'tenant_id' => $tenant->id,
                                'call_session_id' => $call->id,
                                'service' => 'General Consultation',
                                'client_name' => $temp->customer_name,
                                'client_phone' => $call->from_number,
                                'client_email' => null,
                                'start_at' => $startDateTime,
                                'end_at' => $endDateTime,
                                'timezone' => config('app.timezone'),
                                'duration_minutes' => 60,
                                'status' => 'confirmed',
                                'meta' => [
                                    'source' => 'ivr_ai',
                                    'temp_appointment_id' => $temp->id,
                                    'google_event_id' => $createdEvent->id, // 🔹 store event id
                                ],
                            ]);

                            // ✅ Delete from TempAppointment once confirmed
                            $temp->delete();

                            $replyText = "✅ Thank you {$temp->customer_name}, your appointment is booked for {$temp->appointment_date} at {$temp->appointment_time}.";

                        } else {
                            // ❌ Conflict in Google Calendar
                            $replyText = "❌ Sorry {$temp->customer_name}, that time is already booked in the calendar. Please choose another time.";
                            $temp->status = 'pending';
                            $temp->save();
                        }
                    } catch (\Throwable $e) {
                        $logError($tenant->id, 'Appointment', 'fatal', $e->getMessage(), [
                            'trace' => $e->getTraceAsString(),
                        ]);
                        $replyText = "⚠️ Sorry, we could not process your appointment request.";
                    }
                }
            }
            // 10) TTS
            $elevenLabs = ElevenLabs::where('tenant_id', $tenant->id)->first();
            $mp3Rel = null;
            $ttsRender = null;
            $ttsLatency = 0;
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
                        
                        file_put_contents(Storage::disk('public')->path($assistantDirRel."/".$fname), $ttsResp->body());

                        $aiAudioAsset = AudioAsset::create([
                            'tenant_id' => $tenant->id,
                            'kind' => 'tts_output',
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
                'llm_run_id' => $llmRun?->id,
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
                'ok' => true,
                'asr' => $transcript,
                'reply' => $replyText,
                'wav_path' => $userFileRel,
                'audio_url' => $mp3Rel,
                'call_id' => $call->id,
                'messages' => $call->messages()->latest()->take(2)->get(),
            ]);
        });
    }
}