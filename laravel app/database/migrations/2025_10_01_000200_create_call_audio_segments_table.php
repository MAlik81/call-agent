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
        Schema::create('call_audio_segments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('call_session_id');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedInteger('segment_index');
            $table->string('role', 32)->nullable();
            $table->string('format', 32)->nullable();
            $table->unsignedInteger('sample_rate')->nullable();
            $table->longText('audio_base64');
            $table->json('metadata')->nullable();
            $table->enum('stt_status', ['pending', 'done', 'failed'])->default('pending');
            $table->text('transcript')->nullable();
            $table->timestamps();

            $table->foreign('call_session_id')
                ->references('id')
                ->on('call_sessions')
                ->cascadeOnDelete();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->nullOnDelete();

            $table->unique(['call_session_id', 'segment_index']);
            $table->index(['tenant_id', 'stt_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_audio_segments');
    }
};
