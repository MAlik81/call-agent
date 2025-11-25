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
            $table->boolean('realtime_enabled')->default(false)->after('stt_model');
            $table->string('realtime_model', 64)->nullable()->after('realtime_enabled');
            $table->longText('realtime_system_prompt')->nullable()->after('realtime_model');
            $table->string('realtime_voice', 64)->nullable()->after('realtime_system_prompt');
            $table->string('realtime_language', 32)->nullable()->after('realtime_voice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('open_ai_settings', function (Blueprint $table) {
            $table->dropColumn([
                'realtime_enabled',
                'realtime_model',
                'realtime_system_prompt',
                'realtime_voice',
                'realtime_language',
            ]);
        });
    }
};
