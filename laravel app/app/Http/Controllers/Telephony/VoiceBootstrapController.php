<?php

namespace App\Http\Controllers\Telephony;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\PhoneNumbers;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\TwilioSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceBootstrapController extends Controller
{
    public function bootstrap(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'call_id' => ['nullable', 'integer'],
            'to_number' => ['nullable', 'string', 'required_without:call_id'],
        ]);

        [$callSession, $tenant] = $this->resolveTenant($validated['call_id'] ?? null, $validated['to_number'] ?? null);

        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        $openAiSetting = $tenant->openAiSetting;
        $elevenLabsSetting = $tenant->elevenLabs;

        $rules = [];
        $extra = $openAiSetting?->extra;
        if (is_array($extra) && array_key_exists('rules', $extra)) {
            $rules = is_array($extra['rules']) ? $extra['rules'] : [$extra['rules']];
        }

        $config = [
            'model' => $openAiSetting?->default_model ?? 'gpt-4o-mini',
            'prompt' => $openAiSetting?->instructions ?? '',
            'voice' => $elevenLabsSetting?->elevenlabs_voice_id,
            'language' => $elevenLabsSetting?->language,
            'realtime_enabled' => (bool) ($openAiSetting?->realtime_enabled ?? false),
            'realtime_model' => $openAiSetting?->realtime_model ?? 'gpt-4o-realtime-preview',
            'realtime_system_prompt' => $openAiSetting?->realtime_system_prompt,
            'realtime_voice' => $openAiSetting?->realtime_voice ?? $elevenLabsSetting?->elevenlabs_voice_id,
            'realtime_language' => $openAiSetting?->realtime_language ?? $elevenLabsSetting?->language,
            'rules' => $rules,
        ];

        return response()->json([
            'call_id' => $callSession?->id,
            'tenant_id' => $tenant->id,
            'ws_url' => $this->mediaStreamUrl($callSession, $tenant),
            'config' => $config,
        ]);
    }

    private function mediaStreamUrl(?CallSession $callSession, Tenant $tenant): ?string
    {
        $host = SystemSetting::where('key', 'proxy_host')->value('value') ?? env('PROXY_HOST');
        $port = SystemSetting::where('key', 'proxy_port')->value('value') ?? env('PROXY_PORT', 443);

        if (!$host) {
            return null;
        }

        $scheme = ((int) $port === 443) ? 'wss' : 'ws';
        $normalizedHost = trim(str_replace(['wss://', 'ws://', 'https://', 'http://'], '', $host), '/');

        $query = http_build_query([
            'call_id' => $callSession?->id,
            'tenant_id' => $tenant->id,
            'tenant_uuid' => $tenant->uuid,
            'call_sid' => $callSession?->call_sid,
        ]);

        return sprintf('%s://%s:%s/media-stream?%s', $scheme, $normalizedHost, $port, $query);
    }

    private function resolveTenant(?int $callId, ?string $toNumber): array
    {
        $callSession = null;
        $tenant = null;

        if ($callId) {
            $callSession = CallSession::with('tenant')->find($callId);
            $tenant = $callSession?->tenant;
        }

        if (!$tenant && $toNumber) {
            $twilioSetting = $this->resolveTwilioSettingByNumber($toNumber);
            $tenant = $twilioSetting?->tenant;

            if (!$tenant) {
                $phoneNumber = $this->resolvePhoneNumber($toNumber);
                $tenant = $phoneNumber?->tenant;
            }
        }

        return [$callSession, $tenant];
    }

    private function resolveTwilioSettingByNumber(?string $number): ?TwilioSetting
    {
        foreach ($this->candidateNumbers($number) as $candidate) {
            $setting = TwilioSetting::with('tenant')
                ->whereJsonContains('phone_numbers', $candidate)
                ->first();

            if ($setting) {
                return $setting;
            }
        }

        return null;
    }

    private function resolvePhoneNumber(?string $number): ?PhoneNumbers
    {
        foreach ($this->candidateNumbers($number) as $candidate) {
            $phoneNumber = PhoneNumbers::with('tenant')
                ->where('e164', $candidate)
                ->orWhere('e164', ltrim($candidate, '+'))
                ->first();

            if ($phoneNumber) {
                return $phoneNumber;
            }
        }

        return null;
    }

    private function candidateNumbers(?string $number): array
    {
        $normalized = $this->normalizeNumber($number);

        if (!$normalized) {
            return [];
        }

        $candidates = [$normalized, ltrim($normalized, '+')];

        return array_values(array_unique(array_filter($candidates)));
    }

    private function normalizeNumber(?string $number): ?string
    {
        if (!$number) {
            return null;
        }

        $normalized = preg_replace('/[^0-9+]/', '', $number);

        if (!$normalized) {
            return null;
        }

        if ($normalized[0] !== '+') {
            $normalized = '+' . ltrim($normalized, '+');
        }

        return $normalized;
    }
}
