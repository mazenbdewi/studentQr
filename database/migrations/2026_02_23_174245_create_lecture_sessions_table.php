<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecture_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('hall_id')->constrained()->cascadeOnDelete();
            $table->date('session_date');
            $table->string('session_otp', 6)->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamp('actual_start')->nullable();
            $table->timestamp('actual_end')->nullable();
            $table->enum('status', ['scheduled', 'active', 'completed', 'cancelled'])->default('scheduled');
            $table->enum('attendance_mode', ['qr_only', 'qr_otp', 'manual'])->default('qr_otp');
            $table->smallInteger('qr_refresh_rate')->default(40);
            $table->smallInteger('expected_students')->default(0);
            $table->smallInteger('actual_attendance')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('lecture_sessions');
    }
};
