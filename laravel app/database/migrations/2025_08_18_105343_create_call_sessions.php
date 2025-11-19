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
        Schema::create('call_sessions', function (Blueprint $table) {
              $table->bigIncrements('id'); // Primary key
    $table->unsignedBigInteger('tenant_id')->nullable(); // Matches tenants.id
            $table->string('call_sid', 64)->unique(); // Unique call identifier (from Twilio or similar)
            $table->string('from_number', 32)->nullable(); // Caller number
            $table->string('to_number', 32)->nullable(); // Recipient number
            $table->enum('status', ['initiated', 'active', 'completed', 'failed'])->default('initiated'); // Call lifecycle state
            $table->enum('direction', ['inbound', 'outbound'])->default('inbound'); // Call direction
            $table->string('assistant_thread_id', 128)->nullable(); // Optional link to LLM assistant thread
            $table->dateTime('started_at')->nullable(); // When call started
            $table->dateTime('ended_at')->nullable(); // When call ended
            $table->integer('twilio_billable_sec')->default(0); // Billing seconds from Twilio
            $table->string('hangup_cause', 64)->nullable(); // Reason for hangup
            $table->json('meta')->nullable(); // Extra metadata for the call
            $table->timestamps();

            // Foreign key
            $table->foreign('tenant_id')
                  ->references('id')
                  ->on('tenants')
                  ->onDelete('cascade');

            // Indexes
            $table->index(['tenant_id', 'started_at']);
            $table->index('call_sid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_sessions');
    }
};
