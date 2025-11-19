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
        Schema::create('conversation_metrics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('call_session_id')->unsigned();
            $table->integer('user_turns')->default(0);
            $table->integer('assistant_turns')->default(0);
            $table->integer('barge_in_count')->default(0);
            $table->integer('avg_user_turn_ms')->nullable();
            $table->integer('avg_response_ms')->nullable();
            $table->decimal('silence_ratio', 5, 4)->nullable();
            $table->timestamps();

            // Indexes
            $table->index('call_session_id');

            // Foreign keys
            $table->foreign('call_session_id')
                ->references('id')
                ->on('call_sessions')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_metrics');
    }
};
