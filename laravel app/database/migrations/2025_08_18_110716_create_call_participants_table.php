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
        Schema::create('call_participants', function (Blueprint $table) {
            $table->bigIncrements('id'); // Primary key
            $table->unsignedBigInteger('call_session_id'); // Foreign key to call_sessions
            $table->enum('type', ['caller', 'callee', 'agent', 'bot']); // Role in the call
            $table->string('identifier', 191); // Phone number or user ID
            $table->dateTime('joined_at')->nullable(); // When participant joined
            $table->dateTime('left_at')->nullable(); // When participant left
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('call_session_id')
                  ->references('id')
                  ->on('call_sessions')
                  ->onDelete('cascade');

            // Index for faster lookups per call
            $table->index('call_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_participants');
    }
};
