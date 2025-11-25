<?php
namespace App\Http\Controllers\Telephony;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\TwilioSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\Security\RequestValidator;

class TwilioVoiceWebhookController extends Controller
{
    private function proxyHost(): string
    {
        $hostSetting = \App\Models\SystemSetting::where('key', 'PROXY_HOST')->first();

        if (! $hostSetting || empty($hostSetting->value)) {
            abort(500, 'Missing PROXY_HOST in system_settings');
        }

        $host = trim($hostSetting->value);

        if (empty($host)) {
            abort(500, 'Invalid PROXY_HOST value');
        }

        return $host; // e.g. 46df29e15fda.ngrok-free.app
    }

    /**
     * Lookup tenant by incoming number.
     * First try DB mapping via TwilioSetting, else fallback to env default.
     */
    private function normalizeNumber(?string $number): ?string
    {
        if (! $number) {
            return null;
        }

        $normalized = preg_replace('/[^0-9+]/', '', $number);

        if (! $normalized) {
            return null;
        }

        // Ensure leading + for E.164 numbers
        if ($normalized[0] !== '+') {
            $normalized = '+' . ltrim($normalized, '+');
        }

        return $normalized;
    }

    private function candidateNumbers(?string $number): array
    {
        $normalized = $this->normalizeNumber($number);

        if (! $normalized) {
            return [];
        }

        $candidates = [$normalized];

        // Some numbers may be stored without the leading +
        $candidates[] = ltrim($normalized, '+');

        return array_values(array_unique(array_filter($candidates)));
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

    private function isValidFromTwilio(Request $request): bool
    {
        $to = $request->input('To') ?? $request->input('Called');
        if (! $to) {
            return false;
        }

        $setting = $this->resolveTwilioSettingByNumber($to);

        if (! $setting || ! $setting->auth_token_encrypted) {
            return false; // cannot validate without DB token
        }

        $validator = new RequestValidator($setting->auth_token_encrypted);

        $twilioSig = $request->header('X-Twilio-Signature');
        $url       = $request->fullUrl();
        $params    = $request->post();

        return $validator->validate($twilioSig, $url, $params);
    }

    public function incoming(Request $request)
    {
        try {
            if (! $this->isValidFromTwilio($request)) {
                Log::warning('TWILIO_INVALID_SIGNATURE', ['ip' => $request->ip()]);
                return response('Unauthorized', 401);
            }

            // Typical Twilio params
            $callSid = $request->input('CallSid');
            $to      = $request->input('To') ?? $request->input('Called');
            $from    = $request->input('From') ?? $request->input('Caller');

            // Try mapping tenant
            $twilioSetting = $this->resolveTwilioSettingByNumber($to);
            $tenant        = $twilioSetting?->tenant;

            if (! $tenant) {
                Log::error('TWILIO_TENANT_NOT_FOUND', compact('callSid', 'to', 'from'));
                return response('Tenant not found for this number', 404)
                    ->header('Content-Type', 'text/plain');
            }

            $callSession              = CallSession::firstOrNew(['call_sid' => $callSid]);
            $callSession->tenant_id   = $tenant->id;
            $callSession->from_number = $from;
            $callSession->to_number   = $to;
            $callSession->status      = 'active';
            $callSession->direction   = 'inbound';
            if (! $callSession->exists) {
                $callSession->started_at = now();
            }
            $callSession->save();

            $callId = $callSession->id;

            // Log call data into storage/logs/laravel.log (or your file)
            Log::info('TWILIO_INCOMING', [
                'callSid'  => $callSid,
                'callId'   => $callId,
                'to'       => $to,
                'from'     => $from,
                'tenantId' => $tenant->id,
            ]);

            // Minimal TwiML: attach WS stream
            $wss = 'wss://' . $this->proxyHost() . '/media-stream';
            // Log call data into storage/logs/laravel.log (or your file)
            Log::info('TWILIO_INCOMING2', [
                'wss' => $wss,
            ]);
            $tenantXml     = htmlspecialchars($tenant->id, ENT_QUOTES);
            $tenantUuidXml = htmlspecialchars($tenant->uuid ?? '', ENT_QUOTES);
            $callXml       = htmlspecialchars($callSid ?? '', ENT_QUOTES);
            $callIdXml     = htmlspecialchars((string) $callId, ENT_QUOTES);
            $toNumberXml   = htmlspecialchars($to ?? '', ENT_QUOTES);
            $streamUrl     = htmlspecialchars($wss, ENT_QUOTES);
            Log::info('TWILIO_INCOMING3', [
                'streamUrl'     => $streamUrl,
                'tenantXml'     => $tenantXml,
                'tenantUuidXml' => $tenantUuidXml,
                'callIdXml'     => $callIdXml,
                'callXml'       => $callXml,
            ]);
            $twiml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Response>
  <Say voice="alice">You can start conversation now.</Say>
  <Start>
    <Stream url="{$streamUrl}">
      <Parameter name="tenant_id" value="{$tenantXml}"/>
      <Parameter name="tenant_uuid" value="{$tenantUuidXml}"/>
      <Parameter name="call_sid"  value="{$callXml}"/>
      <Parameter name="call_id"  value="{$callIdXml}"/>
      <Parameter name="to_number" value="{$toNumberXml}"/>
    </Stream>
  </Start>
  <Pause length="600"/>
</Response>
XML;
            Log::info('TWILIO_INCOMING_TWIML', ['twiml' => $twiml]);

            return response($twiml, 200)->header('Content-Type', 'text/xml');

        } catch (\Exception $e) {
            // Log to file with details
            Log::error('TWILIO_INCOMING_EXCEPTION', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response('Internal Server Error', 500)
                ->header('Content-Type', 'text/plain');
        }
    }

}
