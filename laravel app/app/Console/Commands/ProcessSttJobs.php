<?php

namespace App\Console\Commands;

use App\Models\ErrorLogs;
use App\Models\SttJob;
use App\Services\SpeechToTextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessSttJobs extends Command
{
    protected $signature = 'stt:process-pending';

    protected $description = 'Process pending speech-to-text jobs and update their transcripts.';

    public function handle(SpeechToTextService $sttService): int
    {
        $jobs = SttJob::whereIn('status', ['pending', 'queued'])
            ->orderBy('id')
            ->limit(20)
            ->get();

        if ($jobs->isEmpty()) {
            $this->info('No pending STT jobs found.');
            return self::SUCCESS;
        }

        foreach ($jobs as $job) {
            DB::beginTransaction();

            try {
                $job->status = 'processing';
                $job->started_at = $job->started_at ?? now();
                $job->save();

                $asset = $job->audioAsset;
                if (!$asset) {
                    throw new \RuntimeException('Missing audio asset for job ' . $job->id);
                }

                $audioBinary = Storage::disk($asset->storage_disk)->get($asset->path);
                $tenant = $job->tenant;

                if (!$tenant) {
                    throw new \RuntimeException('Missing tenant for job ' . $job->id);
                }

                $result = $sttService->transcribeWithOpenAi(
                    $tenant,
                    $audioBinary,
                    basename($asset->path)
                );

                $job->fill([
                    'provider' => $result['provider'] ?? 'whisper',
                    'model' => $result['model'] ?? $tenant->openAiWhisperModel(),
                    'text' => $result['text'] ?? null,
                    'language' => $result['language'] ?? null,
                    'raw_response' => $result['raw_response'] ?? null,
                    'completed_at' => now(),
                    'status' => 'completed',
                ]);

                $job->save();
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();

                $job->status = 'failed';
                $job->error_message = $e->getMessage();
                $job->save();

                ErrorLogs::create([
                    'tenant_id' => $job->tenant_id,
                    'scope' => 'stt',
                    'severity' => 'error',
                    'message' => 'STT job failed',
                    'context' => [
                        'job_id' => $job->id,
                        'exception' => $e->getMessage(),
                    ],
                    'occurred_at' => now(),
                ]);

                Log::error('STT job failed', [
                    'job_id' => $job->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
