<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('call_session_id');
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->longText('text')->nullable();
            $table->unsignedBigInteger('audio_asset_id')->nullable();
            $table->unsignedBigInteger('stt_job_id')->nullable();
            $table->unsignedBigInteger('llm_run_id')->nullable();
            $table->unsignedBigInteger('tts_render_id')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['call_session_id', 'id']);

            // Foreign keys
            $table->foreign('call_session_id')
                  ->references('id')
                  ->on('call_sessions')
                  ->cascadeOnDelete();

            $table->foreign('audio_asset_id')
                  ->references('id')
                  ->on('audio_assets')
                  ->nullOnDelete();

            $table->foreign('stt_job_id')
                  ->references('id')
                  ->on('stt_jobs')
                  ->nullOnDelete();

            $table->foreign('llm_run_id')
                  ->references('id')
                  ->on('llm_runs')
                  ->nullOnDelete();

            $table->foreign('tts_render_id')
                  ->references('id')
                  ->on('tts_renders')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('call_messages', function (Blueprint $table) {
            $table->dropForeign(['call_session_id']);
            $table->dropForeign(['audio_asset_id']);
            $table->dropForeign(['stt_job_id']);
            $table->dropForeign(['llm_run_id']);
            $table->dropForeign(['tts_render_id']);
        });

        Schema::dropIfExists('call_messages');
    }
};
