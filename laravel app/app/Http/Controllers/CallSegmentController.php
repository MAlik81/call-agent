<?php

namespace App\Http\Controllers;

use App\Models\AudioAsset;
use App\Models\CallSegment;
use App\Models\CallSession;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CallSegmentController extends Controller
{
    public function store(Request $request, string $call)
    {
        return $this->persistSegment($request, $call);
    }

    public function storeFromProxy(Request $request)
    {
        return $this->persistSegment($request, null);
    }

    private function persistSegment(Request $request, ?string $call)
    {
        $data = $request->validate([
            'call_id' => 'nullable|integer',
            'call_sid' => 'nullable|string',
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            'segment_index' => 'required|integer|min:0',
            'role' => 'required|in:user,assistant',
            'format' => 'sometimes|string|max:32',
            'sample_rate' => 'sometimes|integer|min:1',
            'audio' => 'sometimes|string',
            'audio_b64' => 'sometimes|string',
            'stream_sid' => 'sometimes|nullable|string',
            'start_ms' => 'sometimes|integer',
            'end_ms' => 'sometimes|integer',
            'duration_ms' => 'sometimes|integer',
            'reason' => 'sometimes|nullable|string',
            'meta' => 'sometimes|array',
        ]);

        $callSession = null;

        if ($call) {
            $callSession = CallSession::where('id', $call)
                ->orWhere('call_sid', $call)
                ->first();
        }

        if (!$callSession && array_key_exists('call_id', $data) && $data['call_id']) {
            $callSession = CallSession::where('id', $data['call_id'])->first();
        }

        if (!$callSession && array_key_exists('call_sid', $data) && $data['call_sid']) {
            $callSession = CallSession::where('call_sid', $data['call_sid'])->first();
        }

        if (!$callSession) {
            return response()->json(['message' => 'Call session not found'], Response::HTTP_NOT_FOUND);
        }

        $tenantId = $data['tenant_id'] ?? $callSession->tenant_id;

        if (!$tenantId) {
            return response()->json(['message' => 'Tenant is required'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found'], Response::HTTP_NOT_FOUND);
        }

        if ($callSession->tenant_id && $callSession->tenant_id !== $tenantId) {
            return response()->json(['message' => 'Unauthorized for this call session'], Response::HTTP_FORBIDDEN);
        }

        if (!$callSession->tenant_id) {
            $callSession->tenant_id = $tenantId;
            $callSession->save();
        }

        $audioPayload = $data['audio'] ?? $data['audio_b64'] ?? null;

        $raw = $audioPayload ? base64_decode($audioPayload, true) : false;
        if ($raw === false || strlen($raw) === 0) {
            return response()->json(['message' => 'Invalid audio payload'], Response::HTTP_BAD_REQUEST);
        }

        $format = $data['format'] ?? 'audio/pcmu';
        $sampleRate = $data['sample_rate'] ?? 8000;

        $callIdentifier = $callSession->call_sid ?: (string) $callSession->id;
        $safeCallId = preg_replace('/[^A-Za-z0-9_-]/', '', $callIdentifier);
        $segmentsDir = "calls/{$safeCallId}/segments";
        Storage::disk('public')->makeDirectory($segmentsDir);

        $extension = $format;
        if (str_contains($extension, '/')) {
            $parts = explode('/', $extension);
            $extension = end($parts) ?: $extension;
        }
        $extension = preg_replace('/[^A-Za-z0-9]/', '', $extension) ?: 'bin';

        $fileName = sprintf('%s_%s.%s', $data['segment_index'], Str::random(6), $extension);
        $path = $segmentsDir . '/' . $fileName;

        Storage::disk('public')->put($path, $raw);

        $meta = $data['meta'] ?? [];
        $meta = array_merge($meta, array_filter([
            'stream_sid' => $data['stream_sid'] ?? null,
            'start_ms' => $data['start_ms'] ?? null,
            'end_ms' => $data['end_ms'] ?? null,
            'duration_ms' => $data['duration_ms'] ?? null,
            'reason' => $data['reason'] ?? null,
        ], static fn ($value) => $value !== null));

        $audioAsset = AudioAsset::create([
            'tenant_id' => $tenantId,
            'kind' => 'call_segment',
            'storage_disk' => 'public',
            'path' => $path,
            'mime' => 'audio/' . strtolower($extension),
            'sample_rate' => $sampleRate,
            'size_bytes' => strlen($raw),
            'checksum' => hash('sha256', $raw),
        ]);

        $segment = CallSegment::create([
            'tenant_id' => $tenantId,
            'call_session_id' => $callSession->id,
            'segment_index' => $data['segment_index'],
            'role' => $data['role'],
            'format' => $format,
            'sample_rate' => $sampleRate,
            'audio_asset_id' => $audioAsset->id,
            'stt_status' => 'pending',
            'meta' => empty($meta) ? null : $meta,
        ]);

        return response()->json(['data' => $segment], Response::HTTP_CREATED);
    }
}
