<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AudioAsset extends Model
{
    use HasFactory;

    protected $table = 'audio_assets';

    // Mass assignable fields
    protected $fillable = [
        'tenant_id',
        'kind',
        'speaker',
        'storage_disk',
        'path',
        'mime',
        'sample_rate',
        'duration_ms',
        'size_bytes',
        'checksum',
        'expires_at',
    ];

    // Casts for proper data handling
    protected $casts = [
        'sample_rate' => 'integer',
        'duration_ms' => 'integer',
        'size_bytes'  => 'integer',
        'expires_at'  => 'datetime',
    ];

    /**
     * Relationship: Audio belongs to a tenant
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
     * Relationship: Audio has many TTS renders
     */

    public function sttJobs()
    {
        return $this->hasMany(SttJob::class, 'input_audio_asset_id', 'id');
    }
    public function ttsRenders()
    {
        return $this->hasMany(TtsRenders::class, 'audio_asset_id');
    }
}
