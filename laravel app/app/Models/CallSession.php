<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CallSession extends Model
{
    use HasFactory;

    protected $table = 'call_sessions';

    protected $fillable = [
        'tenant_id',
        'call_sid',
        'from_number',
        'to_number',
        'status',
        'direction',
        'assistant_thread_id',
        'started_at',
        'ended_at',
        'twilio_billable_sec',
        'hangup_cause',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array', // JSON field becomes array
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * Belongs to a Tenant.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Status helper: check if call is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function sttJob()
{
    return $this->belongsTo(SttJob::class);
}

    public function audioAsset()
{
    return $this->belongsTo(AudioAsset::class);
}



    public function appointment()
    {
        return $this->hasOne(Appointment::class);
    }

     public function tempAppointments()
    {
        return $this->hasMany(TempAppointment::class, 'call_sid', 'call_sid');
    }


    public function messages()
{
    return $this->hasMany(CallMessages::class);
}

    public function segments()
    {
        return $this->hasMany(CallSegment::class);
    }


    /**
     * Mark the call as completed and set ended_at timestamp.
     */
    public function markCompleted(string $cause = null): void
    {
        $this->update([
            'status' => 'completed',
            'ended_at' => now(),
            'hangup_cause' => $cause,
        ]);
    }

    /**
     * Mark the call as failed.
     */
    public function markFailed(string $cause = null): void
    {
        $this->update([
            'status' => 'failed',
            'ended_at' => now(),
            'hangup_cause' => $cause,
        ]);
    }
}
