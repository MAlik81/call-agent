<?php

namespace App\Services;

use App\DTOs\IntentResult;
use App\Models\CallSession;
use App\Models\LlmRun;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IntentEngine
{
    private const DEFAULT_SYSTEM_PROMPT = 'You are a concise, friendly IVR assistant.';

    public function analyze(CallSession $session, string $transcript): IntentResult
    {
        $session->loadMissing('tenant', 'messages');
        $tenant = $session->tenant ?? Tenant::find($session->tenant_id);

        if (!$tenant) {
            return new IntentResult('Sorry, I cannot find your account right now.');
        }

        $apiKey = $tenant->openAiKey();
        $chatModel = $tenant->openAiChatModel();
        $systemPrompt = $tenant->openAiSetting?->instructions ?? self::DEFAULT_SYSTEM_PROMPT;

        $history = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($session->messages()->orderBy('id')->get() as $message) {
            if (empty($message->text)) {
                continue;
            }

            $history[] = [
                'role' => $message->role === 'assistant' ? 'assistant' : 'user',
                'content' => $message->text,
            ];
        }

        $history[] = ['role' => 'user', 'content' => $transcript];

        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'book_appointment',
                    'description' => 'Collects all required details to book an appointment.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'customer_name' => ['type' => 'string'],
                            'customer_phone' => ['type' => 'string'],
                            'service' => ['type' => 'string'],
                            'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                            'time' => ['type' => 'string', 'description' => 'HH:MM'],
                            'timezone' => ['type' => 'string', 'description' => 'IANA timezone'],
                            'extra_notes' => ['type' => 'string'],
                        ],
                        'required' => ['customer_name', 'customer_phone', 'service', 'date', 'time', 'timezone'],
                    ],
                ],
            ],
        ];

        $payload = [
            'model' => $chatModel,
            'messages' => $history,
            'tools' => $tools,
            'tool_choice' => 'auto',
            'temperature' => 0.6,
        ];

        $replyText = 'Sorry, I could not process your request.';
        $intentName = null;
        $intentStatus = null;
        $slots = [];
        $llmRun = null;

        if (!$apiKey) {
            return new IntentResult($replyText);
        }

        try {
            $response = Http::timeout(30)
                ->withToken($apiKey)
                ->withOptions(['verify' => false])
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            $json = $response->json();
            $message = data_get($json, 'choices.0.message', []);
            $replyText = trim(data_get($message, 'content', $replyText));

            $toolCalls = data_get($message, 'tool_calls', []);
            if (is_array($toolCalls) && count($toolCalls) > 0) {
                foreach ($toolCalls as $call) {
                    if (data_get($call, 'function.name') !== 'book_appointment') {
                        continue;
                    }

                    $arguments = data_get($call, 'function.arguments');
                    $decoded = [];
                    if (is_string($arguments)) {
                        $decoded = json_decode($arguments, true) ?: [];
                    } elseif (is_array($arguments)) {
                        $decoded = $arguments;
                    }

                    $requiredKeys = ['customer_name', 'customer_phone', 'service', 'date', 'time', 'timezone'];
                    $hasAllSlots = !in_array(null, array_map(fn ($key) => $decoded[$key] ?? null, $requiredKeys), true);

                    if ($hasAllSlots) {
                        $intentName = 'BOOK_APPOINTMENT';
                        $intentStatus = 'CONFIRMED';
                        $slots = $decoded;
                    } else {
                        $intentName = 'BOOK_APPOINTMENT';
                        $intentStatus = 'PENDING';
                        $slots = $decoded;
                    }
                }
            }

            $llmRun = LlmRun::create([
                'tenant_id' => $tenant->id,
                'model' => $chatModel,
                'status' => $response->ok() ? 'completed' : 'failed',
                'input_tokens' => data_get($json, 'usage.prompt_tokens', 0),
                'output_tokens' => data_get($json, 'usage.completion_tokens', 0),
                'tool_calls' => $toolCalls,
                'temperature' => 0.6,
                'system_prompt_snapshot' => $systemPrompt,
                'raw_request' => $payload,
                'raw_response' => $json,
                'latency_ms' => 0,
                'started_at' => now(),
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('IntentEngine analyze failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }

        return new IntentResult($replyText, $intentName, $intentStatus, $slots, $llmRun?->id);
    }
}
