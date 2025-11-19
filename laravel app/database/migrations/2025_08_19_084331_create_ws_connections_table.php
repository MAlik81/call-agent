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
        Schema::create('ws_connections', function (Blueprint $table) {
    $table->bigIncrements('id');

    // Must match tenants.id type
    $table->unsignedBigInteger('tenant_id');
$table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
;

    $table->unsignedBigInteger('call_session_id');
    $table->foreign('call_session_id')
          ->references('id')
          ->on('call_sessions')
          ->onDelete('cascade');

    $table->string('connection_id', 64);
    $table->binary('client_ip')->nullable();
    $table->string('user_agent', 255)->nullable();
    $table->dateTime('connected_at');
    $table->dateTime('disconnected_at')->nullable();

    $table->bigInteger('bytes_in')->default(0);
    $table->bigInteger('bytes_out')->default(0);
    $table->bigInteger('frames_in')->default(0);
    $table->bigInteger('frames_out')->default(0);

    $table->string('reason', 191)->nullable();

    $table->index(['tenant_id', 'connected_at']);
    $table->index('call_session_id');
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ws_connections');
    }
};
