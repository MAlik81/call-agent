<?php

namespace App\Http\Controllers\Telephony;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TwilioSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;
use App\Models\CallSession;


class TwilioCallControlController extends Controller
{
    /** Basic bearer check shared with your WS */
    private function assertBearer(Request $request): void
    {
        $shared = env('APP_SHARED_TOKEN');
        if ($request->bearerToken() !== $shared) {
            abort(401, 'Unauthorized');
        }
    }

    /** Resolve Twilio credentials per-tenant (fallback to env for default) */
  /** Resolve Twilio credentials per tenant */
    private function resolveTwilioClient(string $tenantId): Client
    {
        $tenant = Tenant::findOrFail($tenantId);

        $creds = $tenant->twilioSettings()->first();

        if (!$creds || !$creds->account_sid || !$creds->auth_token_encrypted) {
            abort(500, 'Twilio credentials not configured for this tenant.');
        }

        return new Client(
            $creds->account_sid,
            $creds->auth_token_encrypted
        );
    }


    /** Read proxy host (no scheme) */
    // private function proxyHost(): string
    // {
    //     $host = env('PROXY_HOST');
    //     if (!$host) {
    //         abort(500, 'Missing PROXY_HOST');
    //     }

    //     return $host; // e.g. 46df29e15fda.ngrok-free.app
    // }


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


    /** Build XML snippet for Twilio <Start><Stream> verbs */
    private function streamVerbs(string $tenantId, string $callSid, int $pauseSeconds = 60): string
    {
        $wss = 'wss://' . $this->proxyHost() . '/media-stream';

        return <<<XML
<Start>
  <Stream url="{$wss}">
    <Parameter name="tenant_id" value="{$tenantId}"/>
    <Parameter name="call_sid"  value="{$callSid}"/>
  </Stream>
</Start>
<Pause length="{$pauseSeconds}"/>
XML;
    }

    /** Play audio then re-attach streaming */

    public function play(Request $request)
    {
        // $this->assertBearer($request);

        $data = $request->validate([
            'tenant_id' => 'required|string',
            'call_sid' => 'required|string',
            'audio_url' => 'required|url',
        ]);

        try {
        $client = $this->resolveTwilioClient($data['tenant_id']);
        $verbs = $this->streamVerbs($data['tenant_id'], $data['call_sid'], 60);
        $audio = e($data['audio_url']);

        $twiml = <<<XML
<Response>
  <Play>{$audio}</Play>
  {$verbs}
</Response>
XML;

            $client->calls($data['call_sid'])->update(['twiml' => $twiml]);

            // 🔹 Store or update call session in DB
            CallSession::updateOrCreate(
                ['call_sid' => $data['call_sid']], // find by call_sid
                [
                    'tenant_id' => $data['tenant_id'],
                    'status' => 'active',
                    'direction' => 'inbound', // or detect dynamically
                    'started_at' => now(),
                    'meta' => ['last_action' => 'play', 'audio_url' => $audio],
                ]
            );

            Log::info('TWIML_PLAY', [
                'tenant_id' => $data['tenant_id'],
                'call_sid' => $data['call_sid'],
                'audio' => $audio,
            ]);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('TWILIO_PLAY_FAIL', [
                'error' => $e->getMessage(),
                'tenant_id' => $data['tenant_id'],
                'call_sid' => $data['call_sid'],
            ]);

            $msg = $e->getMessage();
            if (str_contains($msg, 'not found') || str_contains($msg, 'Resource not accessible')) {
                return response()->json(['ok' => true, 'note' => 'call not active']);
            }

            return response()->json(['ok' => false, 'error' => 'Twilio play failed'], 502);
        }
    }


    /** Stop current audio, re-attach streaming */
    public function stop(Request $request)
    {
        // $this->assertBearer($request);

        $data = $request->validate([
            'tenant_id' => 'required|string',
            'call_sid' => 'required|string',
        ]);

        $client = $this->resolveTwilioClient($data['tenant_id']);
        $verbs = $this->streamVerbs($data['tenant_id'], $data['call_sid'], 60);

        $twiml = <<<XML
<Response>
  {$verbs}
</Response>
XML;

        try {
            $client->calls($data['call_sid'])->update(['twiml' => $twiml]);

            // 🔹 Update DB session (mark still active but stopped playback)
            CallSession::updateOrCreate(
                ['call_sid' => $data['call_sid']],
                [
                    'tenant_id' => $data['tenant_id'],
                    'status' => 'active',
                    'meta' => ['last_action' => 'stop'],
                ]
            );

            Log::info('TWIML_STOP', [
                'tenant_id' => $data['tenant_id'],
                'call_sid' => $data['call_sid'],
            ]);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('TWILIO_STOP_FAIL', [
                'error' => $e->getMessage(),
                'tenant_id' => $data['tenant_id'],
                'call_sid' => $data['call_sid'],
            ]);

            $msg = $e->getMessage();
            if (str_contains($msg, 'not found') || str_contains($msg, 'Resource not accessible')) {
                return response()->json(['ok' => true, 'note' => 'call not active']);
            }

            return response()->json(['ok' => false, 'error' => 'Twilio stop failed'], 502);
        }
    }

}
