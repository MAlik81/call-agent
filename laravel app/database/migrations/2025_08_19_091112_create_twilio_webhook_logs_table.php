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
        Schema::create('twilio_webhook_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Must match tenants.id type (bigIncrements → unsignedBigInteger)
            $table->unsignedBigInteger('tenant_id')->nullable();

            $table->string('call_sid', 64)->nullable();
            $table->enum('endpoint', ['incoming', 'status']);
            $table->json('payload');
            $table->tinyInteger('valid_signature')->default(0);
            $table->dateTime('received_at');
            $table->timestamps();

            // Indexes
            $table->index(['call_sid', 'received_at']);

            // Foreign key
            $table->foreign('tenant_id')
                  ->references('id')
                  ->on('tenants')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('twilio_webhook_logs');
    }
};
