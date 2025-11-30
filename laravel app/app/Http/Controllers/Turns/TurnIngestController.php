<?php

namespace App\Http\Controllers\Turns;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTurnAudio;
use App\Models\ErrorLogs;
use App\Models\SystemSetting;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;


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

        $data = $request->validate([
            'tenant_id' => 'required|string',
            'call_sid' => 'required|string',
            'encoding' => 'required|string',
            'audio_b64' => 'required|string',
            'from_number' => 'nullable|string',
            'to_number' => 'nullable|string',
            'meta' => 'array',
        ]);

        $tenant = Tenant::find($data['tenant_id']);
        if (!$tenant) {
            $logError($data['tenant_id'], 'Tenant', 'error', 'Tenant not found', ['call_sid' => $data['call_sid'] ?? null]);
            return response()->json(['ok' => false, 'step' => 'tenant', 'code' => 'tenant_not_found'], 404);
        }

        $raw = base64_decode($data['audio_b64'], true);
        if ($raw === false || strlen($raw) < 10) {
            $logError($tenant->id, 'Audio', 'error', 'Invalid audio base64', ['call_sid' => $data['call_sid']]);
            return response()->json(['ok' => false, 'step' => 'audio', 'code' => 'bad_audio'], 400);
        }

        ProcessTurnAudio::dispatch(
            $tenant->id,
            $data['call_sid'],
            $data['encoding'],
            $raw,
            $data['from_number'] ?? null,
            $data['to_number'] ?? null,
            $data['meta'] ?? []
        );

        return response()->json([
            'ok' => true,
            'queued' => true,
            'call_sid' => $data['call_sid'],
        ], 202);
    }
}
