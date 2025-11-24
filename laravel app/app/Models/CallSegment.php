<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'call_session_id',
        'segment_index',
        'role',
        'format',
        'sample_rate',
        'audio_asset_id',
        'stt_status',
        'meta',
    ];

    protected $casts = [
        'segment_index' => 'integer',
        'sample_rate' => 'integer',
        'meta' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function callSession()
    {
        return $this->belongsTo(CallSession::class);
    }

    public function audioAsset()
    {
        return $this->belongsTo(AudioAsset::class);
    }
}
