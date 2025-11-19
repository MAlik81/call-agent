<?php
// database/migrations/2025_08_27_000000_create_appointments_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('call_session_id')->nullable()->constrained('call_sessions')->nullOnDelete();

            $table->string('service')->nullable();
            $table->string('client_name')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('client_email')->nullable();

            $table->timestamp('start_at')->index(); // UTC
            $table->timestamp('end_at')->index();   // UTC
            $table->string('timezone')->default('UTC');

            $table->integer('duration_minutes')->default(30);
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');

            $table->json('meta')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
