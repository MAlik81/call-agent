<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_policies', function (Blueprint $table) {
            $table->id(); // BIGINT PK, auto-increment
            $table->char('tenant_id', 36); // FK to tenants (UUID)
            $table->integer('keep_call_audio_days')->default(30); // retention for call audio
            $table->integer('keep_chunks_days')->default(7); // retention for audio chunks
            $table->integer('keep_logs_days')->default(90); // retention for logs (note typo fixed from 'lovgs')
            $table->boolean('anonymize_pii')->default(false); // 0/1 flag for PII anonymization
            $table->timestamps();

            // Index for tenant-based lookups
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_policies');
    }
};
