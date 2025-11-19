<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ElevenLabs extends Model
{
    use HasFactory;

    protected $table = 'elevenlabs_settings';

    protected $fillable = [
        'tenant_id',
        'elevenlabs_api_key_encrypted',
        'elevenlabs_voice_id',
        'stt_provider',
        'stt_model',
        'tts_model',
        'language',
    ];

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }


}
