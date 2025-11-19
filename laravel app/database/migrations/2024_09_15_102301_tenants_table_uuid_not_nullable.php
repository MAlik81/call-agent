<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Change the column to not nullable
            $table->string('uuid')->nullable(false)->change();
        });

        // Add unique index safely
        try {
            DB::statement('ALTER TABLE tenants DROP INDEX tenants_uuid_unique');
        } catch (\Exception $e) {
            // Index does not exist, ignore
        }

        DB::statement('ALTER TABLE tenants ADD UNIQUE tenants_uuid_unique(uuid)');
    }

    public function down(): void
    {
        // Drop unique index safely
        try {
            DB::statement('ALTER TABLE tenants DROP INDEX tenants_uuid_unique');
        } catch (\Exception $e) {
            // Index does not exist, ignore
        }

        Schema::table('tenants', function (Blueprint $table) {
            // Make the column nullable again
            $table->string('uuid')->nullable()->change();
        });
    }
};
