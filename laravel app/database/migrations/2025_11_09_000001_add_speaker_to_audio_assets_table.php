<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audio_assets', function (Blueprint $table) {
            if (!Schema::hasColumn('audio_assets', 'speaker')) {
                $table->enum('speaker', ['user', 'bot'])->default('user')->after('kind');
            }
        });
    }

    public function down(): void
    {
        Schema::table('audio_assets', function (Blueprint $table) {
            if (Schema::hasColumn('audio_assets', 'speaker')) {
                $table->dropColumn('speaker');
            }
        });
    }
};
