<?php

namespace App\Models;

use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Appointment;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'uuid',
        'is_name_auto_generated',
        'created_by',
        'domain',
    ];

    /** -------------------- Relationships -------------------- */

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(TenantUser::class)
            ->withPivot('id')
            ->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function stripeData(): HasOne
    {
        return $this->hasOne(UserStripeData::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

      public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
      public function googleCalendarApis()
    {
        return $this->hasOne(GoogleCalendarApi::class, 'tenant_id');
    }

    /**
     */
    public function errorLogs()
    {
        return $this->hasMany(ErrorLogs::class, 'tenant_id');
    }


    public function openAiSetting(): HasOne
    {
        return $this->hasOne(OpenAiSetting::class, 'tenant_id');
    }

    public function elevenLabs(): HasOne
    {
        return $this->hasOne(ElevenLabs::class, 'tenant_id');
    }

    public function twilioSettings(): HasMany
    {
        return $this->hasMany(TwilioSetting::class);
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(PhoneNumbers::class, 'tenant_id');
    }

    public function callSessions(): HasMany
    {
        return $this->hasMany(CallSession::class);
    }

    public function sttJobs()
    {
        return $this->hasMany(SttJob::class, 'tenant_id', 'id');
    }

    public function ttsRenders()
    {
        return $this->hasMany(TtsRenders::class, 'tenant_id', 'id');
    }


    public function llmRuns()
    {
        return $this->hasMany(LlmRun::class, 'tenant_id');
    }



    public function openAiSettings(): HasOne
    {
        return $this->hasOne(OpenAiSetting::class, 'tenant_id');
    }

    /** -------------------- Business Logic -------------------- */

    public function subscriptionProductMetadata()
    {
        /** @var SubscriptionService $subscriptionService */
        $subscriptionService = app(SubscriptionService::class);

        return $subscriptionService->getTenantSubscriptionProductMetadata($this);
    }

    public function audioAssets()
    {
        return $this->hasMany(AudioAsset::class, 'tenant_id');
    }


    /** -------------------- OpenAI Helpers -------------------- */

    /**
     * Get decrypted OpenAI API key.
     */
    public function openAiKey(): ?string
    {
        if (!$this->openAiSetting || !$this->openAiSetting->api_key_encrypted) {
            return null;
        }

        try {
            return decrypt($this->openAiSetting->api_key_encrypted);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            \Log::error('OpenAI key decrypt failed for tenant ' . $this->id);
            return null;
        }
    }


    /**
     * Get the default chat model for this tenant.
     */
    public function openAiChatModel(): string
    {
        return $this->openAiSetting?->default_model ?? 'gpt-4o-mini';
    }

    /**
     * Get the default STT (speech-to-text) model for this tenant.
     */
    public function openAiWhisperModel(): string
    {
        return $this->openAiSetting?->stt_model ?? 'gpt-4o-mini-transcribe';
    }

    /** -------------------- ElevenLabs Helpers -------------------- */

    public function elevenApiKey(): ?string
    {
        return $this->elevenLabs?->api_key;
    }

    public function elevenVoiceId(): ?string
    {
        return $this->elevenLabs?->voice_id;
    }

    /** -------------------- Twilio Helpers -------------------- */

    public function twilioSid(): ?string
    {
        return $this->twilioSettings()->latest()->value('account_sid');
    }

    public function twilioToken(): ?string
    {
        return $this->twilioSettings()->latest()->value('auth_token');
    }
}
