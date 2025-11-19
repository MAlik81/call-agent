<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'call_session_id',
        'service',
        'client_name',
        'client_phone',
        'client_email',
        'start_at',
        'end_at',
        'timezone',
        'duration_minutes',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function callSession(): BelongsTo
    {
        return $this->belongsTo(CallSession::class);
    }
    // App\Models\Appointment.php

protected static function booted(): void
{
    static::created(fn ($record) => \App\Filament\Dashboard\Resources\AppointmentResource::syncWithGoogleCalendar($record));
    static::updated(fn ($record) => \App\Filament\Dashboard\Resources\AppointmentResource::syncWithGoogleCalendar($record));
    static::deleted(fn ($record) => \App\Filament\Dashboard\Resources\AppointmentResource::deleteFromGoogleCalendar($record));
}

}
