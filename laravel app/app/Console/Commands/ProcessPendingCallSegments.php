<?php

namespace App\Console\Commands;

use App\Models\AudioAsset;
use App\Models\CallMessages;
use App\Models\CallSegment;
use App\Models\ErrorLogs;
use App\Models\SttJob;
use App\Models\Tenant;
use App\Services\SpeechToTextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ProcessPendingCallSegments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-call-segments {--limit=20 : Maximum number of pending segments to process in this run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending call segments via STT and update their transcripts.';

    public function handle(SpeechToTextService $speechToTextService): int
    {
        $limit = (int) $this->option('limit');
        $segments = CallSegment::with(['audioAsset', 'callSession'])
            ->where('stt_status', 'pending')
            ->orderBy('id')
            ->limit($limit > 0 ? $limit : 20)
            ->get();

        if ($segments->isEmpty()) {
            $this->info('No pending call segments found.');
            return self::SUCCESS;
        }

        foreach ($segments as $segment) {
            $this->processSegment($segment, $speechToTextService);
        }

        return self::SUCCESS;
    }

    protected function processSegment(CallSegment $segment, SpeechToTextService $speechToTextService): void
    {
        $segment->update(['stt_status' => 'processing']);

        try {
            $tenant = $this->resolveTenant($segment);
            $audioBinary = $this->resolveAudioBinary($segment);

            $result = $speechToTextService->transcribeWithOpenAi(
                $tenant,
                $audioBinary,
                $this->guessFilename($segment->audioAsset)
            );

            $sttJob = SttJob::create([
                'tenant_id' => $segment->tenant_id,
                'provider' => $result['provider'],
                'model' => $result['model'],
                'input_audio_asset_id' => $segment->audio_asset_id,
                'mode' => 'batch',
                'status' => 'completed',
                'text' => $result['text'],
                'raw_response' => $result['raw_response'],
                'started_at' => now()->subMilliseconds($result['latency_ms']),
                'completed_at' => now(),
            ]);

            CallMessages::create([
                'call_session_id' => $segment->call_session_id,
                'role' => $segment->role,
                'text' => $result['text'],
                'audio_asset_id' => $segment->audio_asset_id,
                'stt_job_id' => $sttJob->id,
                'started_at' => now()->subMilliseconds($result['latency_ms']),
                'completed_at' => now(),
                'latency_ms' => $result['latency_ms'],
                'meta' => [
                    'segment_id' => $segment->id,
                    'stt_provider' => $result['provider'],
                    'stt_model' => $result['model'],
                ],
            ]);

            $meta = $this->appendMeta($segment->meta, [
                'transcript' => $result['text'],
                'stt_provider' => $result['provider'],
                'stt_model' => $result['model'],
                'stt_latency_ms' => $result['latency_ms'],
                'stt_job_id' => $sttJob->id,
            ]);

            $segment->update([
                'stt_status' => 'done',
                'meta' => $meta,
            ]);

            $this->info("Processed segment #{$segment->id} ({$segment->role})");
        } catch (\Throwable $e) {
            $this->logError($segment->tenant_id, 'STT', 'error', $e->getMessage(), [
                'segment_id' => $segment->id,
            ]);

            $meta = $this->appendMeta($segment->meta, [
                'stt_error' => $e->getMessage(),
            ]);

            $segment->update([
                'stt_status' => 'failed',
                'meta' => $meta,
            ]);

            $this->error("Failed segment #{$segment->id}: {$e->getMessage()}");
        }
    }

    protected function resolveTenant(CallSegment $segment): Tenant
    {
        $tenantId = $segment->tenant_id ?? $segment->callSession?->tenant_id;

        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        if (!$tenant) {
            throw new \RuntimeException('Tenant not found for call segment ' . $segment->id);
        }

        return $tenant;
    }

    protected function resolveAudioBinary(CallSegment $segment): string
    {
        $meta = is_array($segment->meta) ? $segment->meta : [];

        if (!empty($meta['audio_b64'])) {
            $decoded = base64_decode((string) $meta['audio_b64'], true);
            if ($decoded !== false && strlen($decoded) > 0) {
                return $decoded;
            }
        }

        $asset = $segment->audioAsset;

        if (!$asset) {
            throw new \RuntimeException('Audio asset missing for call segment ' . $segment->id);
        }

        $disk = $asset->storage_disk ?: 'public';

        if (!Storage::disk($disk)->exists($asset->path)) {
            throw new \RuntimeException('Audio file not found at ' . $asset->path);
        }

        return Storage::disk($disk)->get($asset->path);
    }

    protected function guessFilename(?AudioAsset $asset): string
    {
        if (!$asset) {
            return 'audio.wav';
        }

        return basename($asset->path) ?: 'audio.wav';
    }

    protected function appendMeta($currentMeta, array $newData): array
    {
        $meta = is_array($currentMeta) ? $currentMeta : [];

        return array_merge($meta, $newData);
    }

    protected function logError(?int $tenantId, string $scope, string $severity, string $message, array $context = []): void
    {
        ErrorLogs::create([
            'tenant_id' => $tenantId,
            'scope' => $scope,
            'severity' => $severity,
            'message' => $message,
            'context' => $context,
            'occurred_at' => now(),
        ]);
    }
}
