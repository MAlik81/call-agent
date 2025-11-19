<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErrorLogs extends Model
{
    use HasFactory;

    protected $table = 'errors_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'scope',
        'severity',
        'message',
        'context',
        'occurred_at',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'context' => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * Relationship to Tenant
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
