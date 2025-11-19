<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('temp_appointments', function (Blueprint $table) {
            $table->id();
            $table->string('call_sid'); // link to CallSession
            $table->string('from_number')->nullable();
            $table->string('to_number')->nullable();
            $table->string('customer_name')->nullable();
            $table->date('appointment_date')->nullable(); // date of requested appointment
            $table->time('appointment_time')->nullable(); // optional separate time
            $table->string('status')->default('pending'); // pending / confirmed / canceled
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temp_appointments');
    }
};

