<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('assistant_threads', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id'); // Now compatible
            $table->unsignedBigInteger('call_session_id');
            $table->string('provider', 32)->default('openai');
            $table->string('assistant_id', 128);
            $table->string('thread_id', 128)->unique();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                  ->references('id')
                  ->on('tenants')
                  ->onDelete('cascade');

            $table->foreign('call_session_id')
                  ->references('id')
                  ->on('call_sessions')
                  ->onDelete('cascade');

            $table->index(['tenant_id', 'call_session_id']);
        });
    }

    public function down(): void {
        Schema::table('assistant_threads', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['call_session_id']);
        });
        Schema::dropIfExists('assistant_threads');
    }
};
