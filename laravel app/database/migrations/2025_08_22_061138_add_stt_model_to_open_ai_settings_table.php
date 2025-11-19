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
        Schema::table('open_ai_settings', function (Blueprint $table) {
            $table->string('stt_model', 64)
                  ->default('whisper-1')
                  ->after('default_model'); // put after default_model
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('open_ai_settings', function (Blueprint $table) {
            $table->dropColumn('stt_model');
        });
    }
};
