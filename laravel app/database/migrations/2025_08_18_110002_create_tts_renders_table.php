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
        Schema::create('tts_renders', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedBigInteger('tenant_id'); // match tenants.id
    $table->enum('provider', ['elevenlabs', 'other']);
    $table->string('voice_id', 128)->nullable();
    $table->string('model', 128)->nullable();
    $table->integer('input_chars')->default(0);
    $table->unsignedBigInteger('audio_asset_id');
    $table->integer('latency_ms')->nullable();
    $table->integer('cost_estimate_cents')->nullable();
    $table->json('raw_request')->nullable();
    $table->json('raw_response')->nullable();
    $table->enum('status', ['processing', 'completed', 'failed']);
    $table->dateTime('started_at')->nullable();
    $table->dateTime('completed_at')->nullable();
    $table->timestamps();

    // Foreign keys
    $table->foreign('tenant_id')
          ->references('id')
          ->on('tenants')
          ->onDelete('cascade');

    $table->foreign('audio_asset_id')
          ->references('id')
          ->on('audio_assets')
          ->onDelete('cascade');

    // Indexes
    $table->index('tenant_id');
    $table->index('created_at');
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tts_renders');
    }
};
