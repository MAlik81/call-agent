<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('turn_metrics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('call_message_id')->unsigned();
            $table->integer('user_speech_ms')->nullable();
            $table->integer('vad_silence_ms')->nullable();
            $table->integer('stt_latency_ms')->nullable();
            $table->integer('llm_latency_ms')->nullable();
            $table->integer('tts_latency_ms')->nullable();
            $table->integer('end_to_end_ms')->nullable();
            $table->tinyInteger('barge_in_detected')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('call_message_id');

            // Foreign keys
            $table->foreign('call_message_id')
                ->references('id')
                ->on('call_messages')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turn_metrics');
    }
};
