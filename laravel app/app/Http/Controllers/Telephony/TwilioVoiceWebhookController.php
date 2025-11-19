<?php

namespace App\Http\Controllers\Telephony;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TwilioSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\Security\RequestValidator;

class TwilioVoiceWebhookController extends Controller
{
    private function proxyHost(): string
    {
        $hostSetting = \App\Models\SystemSetting::where('key', 'PROXY_HOST')->first();

        if (!$hostSetting || empty($hostSetting->value)) {
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
    private function mapTenantIdByNumber(?string $to): ?string
    {
        if (!$to) {
            return null; // no number, no tenant
        }

        $setting = TwilioSetting::whereJsonContains('phone_numbers', $to)->first();

        if ($setting && $setting->tenant) {
            return $setting->tenant->id; // return tenant ID from Tenant model
        }

        $defaultTenant = Tenant::first(); // pick first tenant as default
        return $defaultTenant ? $defaultTenant->id : null;
    }



    private function isValidFromTwilio(Request $request): bool
    {
        $to = $request->input('To') ?? $request->input('Called');
        if (!$to)
            return false;

        $setting = TwilioSetting::whereJsonContains('phone_numbers', $to)->first();

        if (!$setting || !$setting->auth_token_encrypted) {
            return false; // cannot validate without DB token
        }

        $validator = new RequestValidator($setting->auth_token_encrypted);

        $twilioSig = $request->header('X-Twilio-Signature');
        $url = $request->fullUrl();
        $params = $request->post();

        return $validator->validate($twilioSig, $url, $params);
    }



   public function incoming(Request $request)
{
    try {
        if (!$this->isValidFromTwilio($request)) {
            Log::warning('TWILIO_INVALID_SIGNATURE', ['ip' => $request->ip()]);
            return response('Unauthorized', 401);
        }

        // Typical Twilio params
        $callSid = $request->input('CallSid');
        $to = $request->input('To') ?? $request->input('Called');
        $from = $request->input('From') ?? $request->input('Caller');

        // Try mapping tenant
        $tenantId = $this->mapTenantIdByNumber($to);

        if (!$tenantId) {
            Log::error('TWILIO_TENANT_NOT_FOUND', compact('callSid', 'to', 'from'));
            return response('Tenant not found for this number', 404)
                ->header('Content-Type', 'text/plain');
        }

        // Log call data into storage/logs/laravel.log (or your file)
        Log::info('TWILIO_INCOMING', compact('callSid', 'to', 'from', 'tenantId'));

        // Minimal TwiML: attach WS stream
        $wss = 'wss://socket.theurl.co/media-stream';

        $tenantXml = htmlspecialchars($tenantId, ENT_QUOTES);
        $callXml = htmlspecialchars($callSid ?? '', ENT_QUOTES);

        $twiml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Response>
  <Say voice="alice">Conneting</Say>
  <Start>
    <Stream url="{$wss}">
      <Parameter name="tenant_id" value="{$tenantXml}"/>
      <Parameter name="call_sid"  value="{$callXml}"/>
    </Stream>
  </Start>
  <Pause length="600"/>
</Response>
XML;

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
