<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TwilioSetting extends Model
{
    use HasFactory;

    protected $table = 'twilio_settings';

    protected $fillable = [
        'tenant_id',
        'account_sid',
        'auth_token_encrypted',
        'application_sid',
        'phone_numbers',
        'webhook_token',
    ];

    protected $casts = [
        'phone_numbers' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    

    public function phoneNumbers()
{
    return $this->hasMany(\App\Models\PhoneNumbers::class, 'tenant_id', 'tenant_id');
}

}
