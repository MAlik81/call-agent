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
        Schema::create('knowledge_bases', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id'); // match tenants.id
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->json('stats')->nullable();
            $table->timestamps();

            // Use a UNIQUE name for the foreign key to avoid duplicates
            $table->foreign('tenant_id', 'kb_tenant_fk')
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
        Schema::dropIfExists('knowledge_bases');
    }
};
