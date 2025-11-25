<?php

namespace App\Models;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpenAiSetting extends Model
{
    use HasFactory;

    protected $table = 'open_ai_settings';

    protected $fillable = [
        'tenant_id',
        'allow_override',
        'api_key_encrypted',
        'default_model',
        'stt_model',
        'instructions',
        'realtime_enabled',
        'realtime_model',
        'realtime_system_prompt',
        'realtime_voice',
        'realtime_language',
        'extra',
    ];

    protected $casts = [
        'allow_override' => 'boolean',
        'realtime_enabled' => 'boolean',
        'extra' => 'array',
    ];

    /**
     * Relationship: belongs to Tenant
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Accessor: get decrypted API key (read-only).
     * Usage: $openAiSetting->api_key
     */
    public function getApiKeyAttribute(): ?string
    {
        if (empty($this->api_key_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->api_key_encrypted);
        } catch (\Throwable $e) {
            \Log::error('OpenAiSetting: API key decryption failed', [
                'setting_id' => $this->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Mutator: automatically encrypt api_key when setting it
     * Usage: $openAiSetting->api_key = 'sk-xxxx';
     */
    public function setApiKeyAttribute(?string $value): void
    {
        $this->attributes['api_key_encrypted'] = $value
            ? Crypt::encryptString($value)
            : null;
    }
}
