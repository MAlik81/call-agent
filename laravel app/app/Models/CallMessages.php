<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallMessages extends Model
{
    protected $fillable = [
        'call_session_id',
        'role',
        'text',
        'audio_asset_id',
        'stt_job_id',
        'llm_run_id',
        'tts_render_id',
        'started_at',
        'completed_at',
        'latency_ms',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
            'latency_ms' => 'integer',

    ];

    // Relationships
    public function callSession()
    {
        return $this->belongsTo(CallSession::class);
    }


    public function audioAsset()
{
    return $this->belongsTo(AudioAsset::class, 'audio_asset_id');
}


public function llmRun()
{
    return $this->belongsTo(LlmRun::class);
}

    public function sttJob()
    {
        return $this->belongsTo(SttJob::class);
    }





    public function ttsRender()
    {
        return $this->belongsTo(TtsRenders::class);
    }
}
