<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('attendance_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lecture_session_id')->constrained()->cascadeOnDelete();
            $table->enum('token_type', ['qr', 'otp'])->default('qr');
            $table->string('token_value', 100)->unique();
            $table->string('qr_image_path')->nullable();
            $table->timestamp('expires_at');
            $table->boolean('is_used')->default(false);
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('attendance_tokens');
    }
};
