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
        Schema::table('roadmap_items', function (Blueprint $table) {
    $table->string('slug', 191)->change(); // was 255, changed to 191
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
{
    Schema::table('roadmap_items', function (Blueprint $table) {
        $table->dropUnique('roadmap_items_slug_unique'); // drop existing unique key
    });

    Schema::table('roadmap_items', function (Blueprint $table) {
        $table->uuid('slug')->change(); // just change type
    });

    Schema::table('roadmap_items', function (Blueprint $table) {
        $table->unique('slug'); // reapply unique
    });
}

};
