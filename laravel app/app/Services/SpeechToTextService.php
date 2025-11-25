<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;

class SpeechToTextService
{
    public function transcribeWithOpenAi(Tenant $tenant, string $audioBinary, string $filename = 'audio.wav'): array
    {
        $apiKey = $tenant->openAiKey();

        if (!$apiKey) {
            throw new \RuntimeException('Missing OpenAI API key for tenant ' . $tenant->id);
        }

        $model = $tenant->openAiWhisperModel();

        $start = microtime(true);
        $response = Http::withToken($apiKey)
            ->withOptions(['verify' => false])
            ->attach('file', $audioBinary, $filename)
            ->asMultipart()
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => $model,
            ]);

        if (!$response->ok()) {
            throw new \RuntimeException('STT API failed: ' . $response->body());
        }

        $latency = (int) round((microtime(true) - $start) * 1000);
        $transcript = trim($response->json('text') ?? '');

        if ($transcript === '') {
            $transcript = '(no speech detected)';
        }

        return [
            'provider' => 'whisper',
            'model' => $model,
            'latency_ms' => $latency,
            'text' => $transcript,
            'raw_response' => $response->json(),
        ];
    }
}
