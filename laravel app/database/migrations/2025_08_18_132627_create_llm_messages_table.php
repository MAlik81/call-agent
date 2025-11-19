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
        Schema::create('llm_messages', function (Blueprint $table) {
            $table->bigIncrements('id'); // Primary key
            $table->unsignedBigInteger('assistant_thread_id'); // FK to assistant_threads
            $table->enum('role', ['user', 'assistant', 'system', 'tool']); // Role of the message
            $table->longText('content'); // Message content
            $table->string('provider_message_id', 128)->nullable(); // Optional provider-specific ID
            $table->json('meta')->nullable(); // Optional metadata
            $table->timestamps(); // created_at and updated_at

            // Foreign key constraint
            $table->foreign('assistant_thread_id')->references('id')->on('assistant_threads')->onDelete('cascade');

            // Indexes
            $table->index(['assistant_thread_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_messages');
    }
};
