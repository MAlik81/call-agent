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
        Schema::create('media_chunks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('call_session_id')->unsigned();
            $table->bigInteger('ws_connection_id')->unsigned();
            $table->integer('sequence_start');
            $table->integer('sequence_end');
            $table->bigInteger('audio_asset_id')->unsigned();
            $table->decimal('rms', 6, 5)->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->integer('silence_tail_ms')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['call_session_id', 'id']);

            // Foreign keys
            $table->foreign('call_session_id')->references('id')->on('call_sessions')->onDelete('cascade');
            $table->foreign('ws_connection_id')->references('id')->on('ws_connections')->onDelete('cascade');
            $table->foreign('audio_asset_id')->references('id')->on('audio_assets')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_chunks');
    }
};
