<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
             $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('semester')->nullable();
            $table->tinyInteger('year')->nullable();
            $table->enum('status', ['enrolled', 'dropped', 'passed', 'failed'])->default('enrolled');
            $table->timestamps();

            $table->unique(['student_id', 'subject_id']);
        });

    }


    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
