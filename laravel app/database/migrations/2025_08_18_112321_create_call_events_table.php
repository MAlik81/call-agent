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
        Schema::create('call_events', function (Blueprint $table) {
            
            $table->bigIncrements('id');
            $table->unsignedBigInteger('call_session_id');
            $table->string('event_type', 64);
            $table->json('payload');
            $table->dateTime('at');
            $table->timestamps();

            // Foreign key
            $table->foreign('call_session_id')
                  ->references('id')
                  ->on('call_sessions')
                  ->onDelete('cascade');

            // Index for fast retrieval by call and time
            $table->index(['call_session_id', 'at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_events');
    }
};
