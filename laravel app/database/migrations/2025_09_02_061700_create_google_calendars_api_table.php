<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendars_apis', function (Blueprint $table) {
            $table->id();

            // Foreign key linking to tenants table
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')
                  ->references('id')
                  ->on('tenants')
                  ->onDelete('cascade');

            // Name of the service account JSON file
            $table->string('file_name')->comment('Uploaded JSON file name');

            // Full JSON content of the service account
            $table->longText('json_content')->comment('Service account JSON content');

            // Path of the uploaded file on disk
            $table->string('json_file_path')->nullable()->comment('Stored file path of JSON');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendars_apis');
    }
};
