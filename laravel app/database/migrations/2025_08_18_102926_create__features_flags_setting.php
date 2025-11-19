<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
       Schema::create('feature_flags', function (Blueprint $table) {
    $table->bigIncrements('id'); // Primary key

    $table->unsignedBigInteger('tenant_id'); // Matches tenants.id type
    $table->string('key', 64);
    $table->boolean('enabled')->default(false);
    $table->timestamps();

    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->unique(['tenant_id', 'key']);
});

    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};
