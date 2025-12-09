<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stt_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('stt_jobs', 'language')) {
                $table->string('language', 32)->nullable()->after('text');
            }

            if (!Schema::hasColumn('stt_jobs', 'error_message')) {
                $table->text('error_message')->nullable()->after('completed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stt_jobs', function (Blueprint $table) {
            if (Schema::hasColumn('stt_jobs', 'language')) {
                $table->dropColumn('language');
            }

            if (Schema::hasColumn('stt_jobs', 'error_message')) {
                $table->dropColumn('error_message');
            }
        });
    }
};
