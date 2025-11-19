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
        Schema::create('twilio_call_actions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('call_session_id')->unsigned();
            $table->enum('action', ['play', 'stop', 'redirect', 'hangup']);
            $table->json('parameters');
            $table->dateTime('sent_at');
            $table->tinyInteger('succeeded')->nullable();
            $table->json('response')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['call_session_id', 'sent_at']);

            // Foreign key
            $table->foreign('call_session_id')
                ->references('id')
                ->on('call_sessions')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('twilio_call_actions');
    }
};
