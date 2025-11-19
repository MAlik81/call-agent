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
       Schema::create('twilio_settings', function (Blueprint $table) {
    $table->bigIncrements('id');

    $table->unsignedBigInteger('tenant_id')->nullable(); // must match tenants.id

    $table->string('account_sid', 64);
    $table->text('auth_token_encrypted');
    $table->string('application_sid', 64)->nullable();
    $table->json('phone_numbers')->nullable();
    $table->string('webhook_token', 64)->nullable();
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
        Schema::dropIfExists('twilio_settings');
    }
};
