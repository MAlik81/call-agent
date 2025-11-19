<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Must match tenants.id type
            $table->unsignedBigInteger('tenant_id');

            $table->unsignedBigInteger('call_session_id')->nullable();
            $table->enum('subject', [
                'telephony_sec',
                'openai_tokens_in',
                'openai_tokens_out',
                'tts_chars',
                'stt_ms'
            ]);
            $table->bigInteger('quantity');
            $table->string('unit', 32);
            $table->string('provider', 64)->nullable();
            $table->dateTime('occurred_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['subject', 'occurred_at']);

            // Foreign keys
            $table->foreign('tenant_id')
                  ->references('id')
                  ->on('tenants')
                  ->onDelete('cascade');

            $table->foreign('call_session_id')
                  ->references('id')
                  ->on('call_sessions')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
