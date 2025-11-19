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
        Schema::create('open_ai_settings', function (Blueprint $table) {
            $table->bigIncrements('id'); // BIGINT PK
            $table->char('tenant_id', 36); // CHAR(36), FK
            $table->tinyInteger('allow_override')->default(0); // TINYINT(1) DEFAULT 0
            $table->text('api_key_encrypted')->nullable(); // TEXT NULL
            $table->string('default_model', 64)->default('gpt-4o-mini'); // VARCHAR(64) DEFAULT 'gpt-4o-mini'
            $table->longText('instructions')->nullable(); // LONGTEXT NULL
            $table->json('extra')->nullable(); // JSON NULL
            $table->timestamps();

            // Indexes
            $table->index('tenant_id');

            // Optional: foreign key if tenants table exists
            // $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('open_ai_settings');
    }
};









