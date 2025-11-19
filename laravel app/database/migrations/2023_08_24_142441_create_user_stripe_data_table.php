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
        Schema::create('user_stripe_data', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            // Keep lengths small to avoid hitting the 1000-byte MySQL index limit
            $table->string('stripe_customer_id', 100)->nullable();
            $table->string('stripe_payment_method_id', 100)->nullable();

            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Unique index: user_id (8 bytes) + 100-char strings (100 × 4 bytes each = 400 bytes each)
            // Total = 8 + 400 + 400 = 808 bytes < 1000 bytes limit
            $table->unique(
                ['user_id', 'stripe_customer_id', 'stripe_payment_method_id'],
                'user_stripe_data_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_stripe_data');
    }
};

