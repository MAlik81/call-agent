<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TempAppointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'call_sid',
        'from_number',
        'to_number',
        'customer_name',
        'appointment_date',
        'appointment_time',
        'status',
        'notes',
    ];

    /**
     * Relationship: TempAppointment belongs to a CallSession
     * Using call_sid as the foreign key
     */
    public function callSession()
    {
        return $this->belongsTo(CallSession::class, 'call_sid', 'call_sid');
    }
    



}
