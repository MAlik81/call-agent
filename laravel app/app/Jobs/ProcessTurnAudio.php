<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\AudioAsset;
use App\Models\CallSession;
use App\Models\ElevenLabs;
use App\Models\ErrorLogs;
use App\Models\LlmRun;
use App\Models\OpenAiSetting;
use App\Models\SttJob;
use App\Models\TempAppointment;
use App\Models\Tenant;
use App\Models\TtsRenders;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessTurnAudio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $tenantId,
        private readonly string $callSid,
        private readonly string $encoding,
        private readonly string $rawWav,
        private readonly ?string $fromNumber = null,
        private readonly ?string $toNumber = null,
        private readonly array $meta = []
    ) {
    }

    public function handle(): void
    {
        $logError = function (?string $tenantId, string $scope, string $severity, string $message, array $context = []) {
            try {
                ErrorLogs::create([
                    'tenant_id' => $tenantId,
                    'scope' => $scope,
                    'severity' => $severity,
                    'message' => $message,
                    'context' => $context,
                    'occurred_at' => now(),
                ]);
            } catch (Throwable $e) {
                Log::error('Failed to persist error log', [
                    'tenant_id' => $tenantId,
                    'scope' => $scope,
                    'original_message' => $message,
                    'error' => $e->getMessage(),
                ]);
            }
        };

        $tenant = Tenant::find($this->tenantId);
        if (!$tenant) {
            $logError($this->tenantId, 'Tenant', 'error', 'Tenant not found in ProcessTurnAudio', ['call_sid' => $this->callSid]);
            return;
        }

        $callSid = preg_replace('/[^A-Za-z0-9]/', '', $this->callSid);
        $stamp = now()->format('Ymd_Hisv') . '_' . Str::random(6);
        $userFileRel = "calls/{$callSid}/incoming/{$stamp}.wav";
        $assistantDirRel = "calls/{$callSid}/outgoing";

        Storage::disk('public')->makeDirectory("calls/{$callSid}/incoming");
        Storage::disk('public')->makeDirectory($assistantDirRel);

        $baseUrl = config('app.url');
        $assistantDirFullUrl = "{$baseUrl}/storage/{$assistantDirRel}";

        if (strlen($this->rawWav) < 10) {
            $logError($tenant->id, 'Audio', 'error', 'Invalid audio payload length', ['call_sid' => $callSid]);
            return;
        }

        $saved = Storage::disk('public')->put($userFileRel, $this->rawWav);
        if (!$saved) {
            $logError($tenant->id, 'Storage', 'error', 'Failed to save incoming audio', ['path' => $userFileRel]);
            return;
        }

        $userAudioAsset = AudioAsset::create([
            'tenant_id' => $tenant->id,
            'kind' => 'upload_chunk',
            'storage_disk' => 'public',
            'path' => $userFileRel,
            'mime' => 'audio/wav',
            'size_bytes' => strlen($this->rawWav),
            'checksum' => hash('sha256', $this->rawWav),
        ]);

        $call = CallSession::firstOrNew(['call_sid' => $callSid]);
        $call->tenant_id = $tenant->id;
        $call->from_number = $this->fromNumber ?? ($this->meta['caller_number'] ?? null);
        $call->to_number = $this->toNumber ?? ($this->meta['to_number'] ?? null);
        $call->status = 'active';
        $call->direction = $call->direction ?? 'inbound';
        if (!$call->exists) {
            $call->started_at = now();
        }
        $callMeta = is_array($call->meta) ? $call->meta : [];
        $call->meta = array_merge($callMeta, $this->meta ?? [], ['wav_path' => $userFileRel]);
        $call->save();

        $openAiSetting = OpenAiSetting::where('tenant_id', $tenant->id)->first();
        $openaiKey = $openAiSetting?->api_key_encrypted;
        if (!$openaiKey) {
            $logError($tenant->id, 'OpenAI', 'error', 'Missing OpenAI API key');
            return;
        }

        $whisperModel = $openAiSetting?->stt_model ?? 'gpt-4o-mini-transcribe';
        $chatModel = $openAiSetting?->default_model ?? 'gpt-4o-mini';
        $systemPrompt = $openAiSetting?->instructions ?? 'You are a concise, friendly IVR assistant.';

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

        DB::transaction(function () use (
            $userFileRel,
            $assistantDirFullUrl,
            $assistantDirRel,
            $whisperModel,
            $chatModel,
            $systemPrompt,
            $structuredResponseInstruction,
            $tenant,
            $call,
            $userAudioAsset,
            $logError,
            $openaiKey
        ) {
            $transcript = '(no speech detected)';
            try {
                $sttStart = microtime(true);
                $sttResp = Http::withToken($openaiKey)
                    ->withOptions(['verify' => false])
                    ->attach('file', $this->rawWav, 'audio.wav')
                    ->asMultipart()
                    ->post('https://api.openai.com/v1/audio/transcriptions', ['model' => $whisperModel]);

                if (!$sttResp->ok()) {
                    throw new \Exception('STT API failed: ' . $sttResp->body());
                }

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
                    'meta' => ['wav_path' => $userFileRel, 'encoding' => $this->encoding, 'stt_model' => $whisperModel],
                ]);
            } catch (Throwable $e) {
                $logError($tenant->id, 'STT', 'fatal', $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                throw $e;
            }

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
                } catch (Throwable $e) {
                    Log::error('Failed to save LlmRun', [
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } catch (Throwable $e) {
                $logError($tenant->id, 'LLM', 'fatal', $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            }

            $appointmentIntent = is_array($appointmentData) && (
                !empty($appointmentData['customer_name']) ||
                !empty($appointmentData['appointment_date']) ||
                !empty($appointmentData['appointment_time'])
            );

            if ($appointmentIntent) {
                $temp = TempAppointment::firstOrNew([
                    'call_sid' => $call->call_sid,
                ]);

                if (empty($temp->customer_name)) {
                    if (!empty($appointmentData['customer_name'])) {
                        $temp->customer_name = $appointmentData['customer_name'];
                        $temp->save();
                    } else {
                        $replyText = 'Can I have your name for the appointment?';
                        return;
                    }
                }

                if (empty($temp->appointment_date)) {
                    if (!empty($appointmentData['appointment_date'])) {
                        $temp->appointment_date = $appointmentData['appointment_date'];
                        $temp->save();
                    } else {
                        $replyText = 'On which date would you like the appointment?';
                        return;
                    }
                }

                if (empty($temp->appointment_time)) {
                    if (!empty($appointmentData['appointment_time'])) {
                        $temp->appointment_time = $appointmentData['appointment_time'];
                        $temp->save();
                    } else {
                        $replyText = 'What time works best for you?';
                        return;
                    }
                }

                if ($temp->customer_name && $temp->appointment_date && $temp->appointment_time) {
                    try {
                        $conflict = Appointment::whereDate('start_at', $temp->appointment_date)
                            ->whereTime('start_at', $temp->appointment_time)
                            ->where('status', 'confirmed')
                            ->exists();

                        if ($conflict) {
                            $bookedSlots = Appointment::whereDate('start_at', $temp->appointment_date)
                                ->where('status', 'confirmed')
                                ->orderBy('start_at')
                                ->get(['client_name', 'start_at']);

                            $slotsList = $bookedSlots->map(function ($a) {
                                return $a->start_at->format('H:i') . " ({$a->client_name})";
                            })->implode(', ');

                            $replyText = "❌ Sorry {$temp->customer_name}, {$temp->appointment_date} at {$temp->appointment_time} is already booked.\n📅 Booked slots for that day: {$slotsList}.\nPlease choose another time.";

                            $temp->status = 'pending';
                            $temp->save();

                            return;
                        }

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

                            Appointment::create([
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
                                    'google_event_id' => $createdEvent->id,
                                ],
                            ]);

                            $temp->delete();

                            $replyText = "✅ Thank you {$temp->customer_name}, your appointment is booked for {$temp->appointment_date} at {$temp->appointment_time}.";
                        } else {
                            $replyText = "❌ Sorry {$temp->customer_name}, that time is already booked in the calendar. Please choose another time.";
                            $temp->status = 'pending';
                            $temp->save();
                        }
                    } catch (Throwable $e) {
                        $logError($tenant->id, 'Appointment', 'fatal', $e->getMessage(), [
                            'trace' => $e->getTraceAsString(),
                        ]);
                        $replyText = '⚠️ Sorry, we could not process your appointment request.';
                    }
                }
            }

            $elevenLabs = ElevenLabs::where('tenant_id', $tenant->id)->first();
            $mp3Rel = null;
            $ttsRender = null;
            $ttsLatency = 0;

            if ($elevenLabs?->elevenlabs_api_key_encrypted && $elevenLabs?->elevenlabs_voice_id) {
                try {
                    $ttsStart = microtime(true);
                    $ttsReq = [
                        'text' => $replyText,
                        'model_id' => 'eleven_multilingual_v2',
                        'voice_settings' => ['stability' => 0.4, 'similarity_boost' => 0.7],
                    ];
                    $ttsResp = Http::timeout(30)
                        ->withHeaders([
                            'xi-api-key' => $elevenLabs->elevenlabs_api_key_encrypted,
                            'Accept' => 'audio/mp3',
                            'Content-Type' => 'application/json',
                        ])
                        ->withOptions(['verify' => false])
                        ->post("https://api.elevenlabs.io/v1/text-to-speech/{$elevenLabs->elevenlabs_voice_id}", $ttsReq);

                    $ttsLatency = (int) round((microtime(true) - $ttsStart) * 1000);

                    if ($ttsResp->ok() && strlen($ttsResp->body()) > 10) {
                        $fname = now()->format('Ymd_Hisv') . '_' . Str::random(6) . '.mp3';
                        $mp3Rel = "{$assistantDirFullUrl}/{$fname}";

                        file_put_contents(Storage::disk('public')->path($assistantDirRel . '/' . $fname), $ttsResp->body());

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
                } catch (Throwable $e) {
                    $logError($tenant->id, 'TTS', 'fatal', $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                }
            }

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

            $call->markCompleted('normal_flow');
        });
    }

    private function unwrapJsonContent(?string $content): string
    {
        $trimmed = trim($content ?? '');
        if ($trimmed === '') {
            return '';
        }

        if (Str::startsWith($trimmed, '```')) {
            $trimmed = preg_replace('/^```[a-zA-Z]*\\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/```$/', '', $trimmed) ?? $trimmed;
        }

        return trim($trimmed);
    }
}
