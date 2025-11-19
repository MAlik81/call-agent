<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('errors_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Must match tenants.id type
            $table->unsignedBigInteger('tenant_id')->nullable();

            $table->string('scope', 64);
            $table->enum('severity', ['debug', 'info', 'warn', 'error', 'fatal']);
            $table->text('message');
            $table->json('context')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'occurred_at'], 'errors_tenant_occurred_idx');
            $table->index(['scope', 'severity'], 'errors_scope_severity_idx');

            // Foreign key
            $table->foreign('tenant_id')
                  ->references('id')
                  ->on('tenants')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('errors_logs');
    }
};
