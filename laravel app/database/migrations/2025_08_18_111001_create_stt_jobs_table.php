<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stt_jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id'); // FK to tenants
            $table->enum('provider', ['deepgram', 'google', 'whisper']);
            $table->string('model', 128)->nullable();
            $table->unsignedBigInteger('input_audio_asset_id'); // FK to audio_assets
            $table->enum('mode', ['chunked', 'streaming', 'batch'])->default('chunked');
            $table->enum('status', ['queued', 'processing', 'completed', 'failed']);
            $table->longText('text')->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->json('word_timing')->nullable();
            $table->integer('cost_estimate_cents')->nullable();
            $table->json('raw_response')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('tenant_id')
                  ->references('id')
                  ->on('tenants')
                  ->cascadeOnDelete();

            $table->foreign('input_audio_asset_id')
                  ->references('id')
                  ->on('audio_assets')
                  ->cascadeOnDelete();

            $table->index(['tenant_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('stt_jobs', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['input_audio_asset_id']);
        });
        Schema::dropIfExists('stt_jobs');
    }
};
