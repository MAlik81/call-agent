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
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('knowledge_base_id');
            $table->enum('source', ['upload', 'url', 's3', 'api']);
            $table->string('uri', 512);
            $table->enum('status', ['queued', 'processing', 'ready', 'failed'])->default('queued');
            $table->json('meta')->nullable();
            $table->timestamps();

            // Use a UNIQUE name for the foreign key
            $table->foreign('knowledge_base_id', 'kd_kb_fk')
                  ->references('id')
                  ->on('knowledge_bases')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};
