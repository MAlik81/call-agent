<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TtsRenders extends Model
{
    use HasFactory;

    // Table name
    protected $table = 'tts_renders';

    // Mass assignable fields
    protected $fillable = [
        'tenant_id',
        'provider',
        'voice_id',
        'model',
        'input_chars',
        'audio_asset_id',
        'latency_ms',
        'cost_estimate_cents',
        'raw_request',
        'raw_response',
        'status',
        'started_at',
        'completed_at',
    ];

    // Cast fields to appropriate types
    protected $casts = [
        'raw_request' => 'array',
        'raw_response' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'input_chars' => 'integer',
        'latency_ms' => 'integer',
        'cost_estimate_cents' => 'integer',
    ];

    /**
     * Relationship: TTS belongs to a tenant
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function messages()
    {
        return $this->hasMany(CallMessages::class);
    }

    /**
     * Relationship: TTS belongs to an audio asset
     */

    public function audioAsset()
    {
        return $this->belongsTo(AudioAsset::class, 'audio_asset_id');
    }
}
