<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); // BIGINT PK, auto-increment
            $table->char('tenant_id', 36)->nullable(); // FK to tenants (UUID)
            $table->foreignId('actor_user_id')->nullable()->constrained('users'); // FK to users
            $table->string('action', 128); // action name
            $table->string('target_type', 128); // class/model type
            $table->string('target_id', 64)->nullable(); // id of target entity
            $table->json('changes')->nullable(); // store JSON diff
            $table->binary('ip')->nullable(); // VARBINARY(16) for IPv4/IPv6
            $table->string('user_agent', 255)->nullable(); // browser/client info
            $table->dateTime('occurred_at'); // timestamp of action
            $table->timestamps();

            // Indexes for faster lookups
            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['actor_user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
