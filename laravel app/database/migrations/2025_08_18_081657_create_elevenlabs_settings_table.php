

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
        Schema::create('elevenlabs_settings', function (Blueprint $table) {
            $table->bigIncrements('id'); // BIGINT PK
            $table->char('tenant_id', 36); // CHAR(36), FK
            $table->text('elevenlabs_api_key_encrypted')->nullable(); // TEXT NULL
            $table->string('elevenlabs_voice_id', 64)->nullable(); // VARCHAR(64) NULL
            $table->enum('stt_provider', ['deepgram', 'google', 'whisper','elevenlabs'])->default(value: 'elevenlabs'); // ENUM DEFAULT 'deepgram'
            $table->string('stt_model', 128)->nullable(); // VARCHAR(128) NULL
            $table->string('tts_model', 128)->nullable(); // VARCHAR(128) NULL
            $table->string('language', 16)->nullable(); // VARCHAR(16) NULL
            $table->timestamps();

            // Index on tenant_id
            $table->index('tenant_id');

            // Optional FK:
            // $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voice_settings');
    }
};
