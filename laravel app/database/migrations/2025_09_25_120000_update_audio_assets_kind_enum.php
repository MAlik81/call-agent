<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE audio_assets MODIFY kind ENUM('upload_chunk', 'tts_output', 'call_recording', 'ringtone', 'other', 'call_segment')"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(
            "ALTER TABLE audio_assets MODIFY kind ENUM('upload_chunk', 'tts_output', 'call_recording', 'ringtone', 'other')"
        );
    }
};
