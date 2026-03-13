<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            $table->foreignId('faculty_id')
                ->nullable()
                ->constrained('faculties')
                ->nullOnDelete();

            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();

            $table->tinyInteger('year')->nullable();

            $table->string('type')->default('student');

            $table->string('phone')->nullable();
            $table->enum('status', ['pending', 'active', 'blocked', 'suspended'])->default('pending');

            $table->string('student_number')->unique()->nullable();
            $table->string('national_number')->unique()->nullable();
            $table->string('avatar')->nullable();
            $table->boolean('is_active')->default(true);


            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
