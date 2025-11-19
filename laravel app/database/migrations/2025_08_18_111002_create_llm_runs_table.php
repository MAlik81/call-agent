<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id'); // FK to tenants
            $table->unsignedBigInteger('assistant_thread_id'); // FK to assistant_threads
            $table->string('model', 64);
            $table->enum('status', ['queued', 'in_progress', 'completed', 'failed'])->default('queued');
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->json('tool_calls')->nullable();
            $table->decimal('temperature', 3, 2)->nullable();
            $table->longText('system_prompt_snapshot')->nullable();
            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->integer('cost_estimate_cents')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            // FKs
            $table->foreign('tenant_id')
                  ->references('id')->on('tenants')
                  ->cascadeOnDelete();

            $table->foreign('assistant_thread_id')
                  ->references('id')->on('assistant_threads')
                  ->cascadeOnDelete();

            // Indexes
            $table->index(['tenant_id', 'assistant_thread_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('llm_runs', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['assistant_thread_id']);
        });

        Schema::dropIfExists('llm_runs');
    }
};
