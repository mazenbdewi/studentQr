<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('failed_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lecture_session_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('reason', ['wrong_otp', 'wrong_ip', 'device_blocked', 'duplicate_device', 'late', 'other'])->default('other');
            $table->string('ip_address', 45)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('failed_attempts');
    }
};
