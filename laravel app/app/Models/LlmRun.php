<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LlmRun extends Model
{
    use HasFactory;

    protected $table = 'llm_runs';

    protected $fillable = [
        'tenant_id',
        'assistant_thread_id',
        'model',
        'status',
        'input_tokens',
        'output_tokens',
        'tool_calls',
        'temperature',
        'system_prompt_snapshot',
        'raw_request',
        'raw_response',
        'latency_ms',
        'cost_estimate_cents',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'tool_calls' => 'array',
        'raw_request' => 'array',
        'raw_response' => 'array',
        'temperature' => 'float',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

  

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function messages()
    {
        return $this->hasMany(CallMessages::class);
    }
}
