<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoogleCalendarApi extends Model
{
    use HasFactory;

    // Table name (optional if follows Laravel convention)
    protected $table = 'google_calendars_apis';

    // Fillable fields for mass assignment
    protected $fillable = [
        'tenant_id',
        'file_name',
        'json_content',
        'json_file_path', // stores file path
        'calendar_id',    // new column added
    ];

    /**
     * Relation to the Tenant model
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
