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
        $data = $request->validate([
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            'segment_index' => 'required|integer|min:0',
            'role' => 'required|in:user,assistant',
            'format' => 'required|string|max:32',
            'sample_rate' => 'required|integer|min:1',
            'audio_b64' => 'required|string',
            'meta' => 'sometimes|array',
        ]);

        $callSession = CallSession::where('id', $call)
            ->orWhere('call_sid', $call)
            ->first();

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

        $raw = base64_decode($data['audio_b64'], true);
        if ($raw === false || strlen($raw) === 0) {
            return response()->json(['message' => 'Invalid audio payload'], Response::HTTP_BAD_REQUEST);
        }

        $callIdentifier = $callSession->call_sid ?: (string) $callSession->id;
        $safeCallId = preg_replace('/[^A-Za-z0-9_-]/', '', $callIdentifier);
        $segmentsDir = "calls/{$safeCallId}/segments";
        Storage::disk('public')->makeDirectory($segmentsDir);

        $extension = preg_replace('/[^A-Za-z0-9]/', '', $data['format']) ?: 'bin';
        $fileName = sprintf('%s_%s.%s', $data['segment_index'], Str::random(6), $extension);
        $path = $segmentsDir . '/' . $fileName;

        Storage::disk('public')->put($path, $raw);

        $audioAsset = AudioAsset::create([
            'tenant_id' => $tenantId,
            'kind' => 'upload_chunk',
            'storage_disk' => 'public',
            'path' => $path,
            'mime' => 'audio/' . strtolower($extension),
            'sample_rate' => $data['sample_rate'],
            'size_bytes' => strlen($raw),
            'checksum' => hash('sha256', $raw),
        ]);

        $segment = CallSegment::create([
            'tenant_id' => $tenantId,
            'call_session_id' => $callSession->id,
            'segment_index' => $data['segment_index'],
            'role' => $data['role'],
            'format' => $data['format'],
            'sample_rate' => $data['sample_rate'],
            'audio_asset_id' => $audioAsset->id,
            'stt_status' => 'pending',
            'meta' => $data['meta'] ?? null,
        ]);

        return response()->json(['data' => $segment], Response::HTTP_CREATED);
    }
}
