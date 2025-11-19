<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deletion_tasks', function (Blueprint $table) {
            $table->id(); // BIGINT PK, auto-increment
            $table->char('tenant_id', 36); // FK to tenants (UUID)
            $table->string('target_table', 64); // Table to delete from
            $table->json('selector'); // Conditions/filters for deletion
            $table->enum('status', ['queued', 'running', 'done', 'failed'])->default('queued'); // Job state
            $table->dateTime('run_at'); // Scheduled execution timestamp
            $table->json('result')->nullable(); // Outcome of deletion (e.g., deleted count)
            $table->timestamps();

            // Index for tenant-based queries
            $table->index('tenant_id');
            $table->index('status');
            $table->index('run_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deletion_tasks');
    }
};
