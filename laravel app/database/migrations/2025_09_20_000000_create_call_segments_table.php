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
        Schema::create('call_segments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('call_session_id');
            $table->unsignedInteger('segment_index');
            $table->enum('role', ['user', 'assistant']);
            $table->string('format', 32);
            $table->unsignedInteger('sample_rate');
            $table->unsignedBigInteger('audio_asset_id');
            $table->string('stt_status', 32)->default('pending');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['call_session_id', 'segment_index']);
            $table->index(['tenant_id', 'call_session_id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->foreign('call_session_id')->references('id')->on('call_sessions')->cascadeOnDelete();
            $table->foreign('audio_asset_id')->references('id')->on('audio_assets')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_segments');
    }
};
