<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->foreignId('faculty_id')->nullable()->constrained();
//            $table->foreignId('head_of_department')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->integer('total_students')->default(0);
            $table->integer('total_lecturers')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
