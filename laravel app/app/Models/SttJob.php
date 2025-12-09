<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SttJob extends Model
{
    use HasFactory;

    // Table name (optional if it follows Laravel convention)
    protected $table = 'stt_jobs';

    // Mass assignable fields
    protected $fillable = [
        'tenant_id',
        'provider',
        'model',
        'input_audio_asset_id',
        'mode',
        'status',
        'text',
        'language',
        'confidence',
        'word_timing',
        'cost_estimate_cents',
        'raw_response',
        'started_at',
        'completed_at',
        'error_message',
    ];

    // Casts
    protected $casts = [
        'word_timing' => 'array',
        'raw_response' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'confidence' => 'decimal:3',
    ];


      protected static function boot()
    {
        parent::boot();

        static::creating(function ($job) {
            if (empty($job->provider)) {
                $job->provider = 'whisper';
            }
        });
    }

    // Relationships

    /**
     * Tenant relationship
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    /**
     * AudioAsset relationship
     */
    public function audioAsset()
    {
        return $this->belongsTo(AudioAsset::class, 'input_audio_asset_id', 'id');
    }

    // Scopes

    /**
     * Scope for completed jobs
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for queued jobs
     */
    public function scopeQueued($query)
    {
        return $query->where('status', 'queued');
    }
}
