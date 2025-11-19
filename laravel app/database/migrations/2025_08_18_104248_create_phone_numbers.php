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
       Schema::create('phone_numbers', function (Blueprint $table) {
    $table->bigIncrements('id'); // Primary key
    $table->unsignedBigInteger('tenant_id')->nullable(); // Matches tenants.id
    $table->string('e164', 32)->unique();
    $table->string('friendly_name', 191)->nullable();
    $table->json('capabilities');
    $table->enum('status', ['active', 'released'])->default('active');
    $table->timestamps();

    $table->foreign('tenant_id')
          ->references('id')
          ->on('tenants')
          ->onDelete('set null');

    $table->index('tenant_id');
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phone_numbers');
    }
};
