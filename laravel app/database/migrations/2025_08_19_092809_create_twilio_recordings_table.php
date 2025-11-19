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
        Schema::create('twilio_recordings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('call_session_id')->unsigned();
            $table->string('recording_sid', 64)->unique();
            $table->enum('status', ['processing', 'available', 'failed']);
            $table->bigInteger('audio_asset_id')->unsigned()->nullable();
            $table->integer('duration_sec')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('call_session_id');

            // Foreign keys
            $table->foreign('call_session_id')
                ->references('id')
                ->on('call_sessions')
                ->onDelete('cascade');

            $table->foreign('audio_asset_id')
                ->references('id')
                ->on('audio_assets')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('twilio_recordings');
    }
};
