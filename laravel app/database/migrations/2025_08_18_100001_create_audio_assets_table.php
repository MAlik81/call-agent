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
Schema::create('audio_assets', function (Blueprint $table) {
    $table->bigIncrements('id'); // Primary key
    $table->unsignedBigInteger('tenant_id'); // FK to tenants.id (bigint)
    $table->enum('kind', ['upload_chunk', 'tts_output', 'call_recording', 'ringtone', 'other']);
    $table->string('storage_disk', 32)->default('s3');
    $table->string('path', 512);
    $table->string('mime', 64);
    $table->integer('sample_rate')->nullable();
    $table->integer('duration_ms')->nullable();
    $table->integer('size_bytes')->nullable();
    $table->string('checksum', 64)->nullable();
    $table->dateTime('expires_at')->nullable();
    $table->timestamps();

    // Foreign key
    $table->foreign('tenant_id')
          ->references('id')
          ->on('tenants')
          ->onDelete('cascade');

    // Index for fast retrieval
    $table->index(['tenant_id', 'created_at']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audio_assets');
    }
};
