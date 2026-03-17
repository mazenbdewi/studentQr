<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lecture_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete(); // student
            $table->foreignId('attendance_token_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('attendance_time');
            $table->enum('attendance_method', ['qr_scan', 'manual', 'admin'])->default('qr_scan');
            $table->enum('attendance_status', ['pending','present', 'late', 'absent', 'excused'])->default('pending');
            $table->string('ip_address', 45)->nullable();
            $table->string('device_fingerprint', 255)->nullable();
            $table->json('location_data')->nullable();


            $table->unique(['lecture_session_id', 'student_id']);

            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
