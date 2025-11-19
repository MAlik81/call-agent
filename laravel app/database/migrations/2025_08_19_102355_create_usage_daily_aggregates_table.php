<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_daily_aggregates', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Must match tenants.id type
            $table->unsignedBigInteger('tenant_id');

            $table->date('date');
            $table->enum('metric', [
                'telephony_sec',
                'openai_tokens_in',
                'openai_tokens_out',
                'tts_chars',
                'stt_ms'
            ]);
            $table->bigInteger('quantity');
            $table->string('provider', 64)->nullable();
            $table->timestamps();

            // Unique constraint
            $table->unique(['tenant_id', 'date', 'metric', 'provider'], 'usage_daily_aggregates_unique');

            // Foreign key
            $table->foreign('tenant_id')
                  ->references('id')
                  ->on('tenants')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_daily_aggregates');
    }
};
