<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('redaction_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id'); // match tenants.id
            $table->string('pattern', 191);
            $table->string('replacement', 64)->default('[REDACTED]');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id', 'rr_tenant_fk') // unique FK name
                  ->references('id')
                  ->on('tenants')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('redaction_rules');
    }
};
