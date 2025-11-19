<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhoneNumbers extends Model
{
    use HasFactory;

    protected $table = 'phone_numbers';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'e164',
        'friendly_name',
        'capabilities',
        'status',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'capabilities' => 'array',
        'tenant_id' => 'string',
    ];

    protected $attributes = [
    'capabilities' => '[]', // default empty JSON array
];


    /**
     * Relationships
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
