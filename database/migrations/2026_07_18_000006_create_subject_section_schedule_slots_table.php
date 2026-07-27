<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_section_schedule_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_batch_id')
                ->constrained('import_batches')
                ->restrictOnDelete();
            $table->foreignId('academic_term_id')
                ->constrained('academic_terms')
                ->restrictOnDelete();
            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->restrictOnDelete();
            $table->foreignId('subject_section_id')
                ->constrained('subject_sections')
                ->restrictOnDelete();
            $table->foreignId('lecturer_id')
                ->nullable()
                ->constrained('lecturers')
                ->nullOnDelete();
            $table->foreignId('hall_id')
                ->nullable()
                ->constrained('halls')
                ->nullOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('section_capacity')->nullable();
            $table->unsignedInteger('expected_student_count')->nullable();
            $table->string('raw_teacher_name')->nullable();
            $table->string('raw_hall_name')->nullable();
            $table->timestamps();

            $table->unique(
                ['academic_term_id', 'subject_section_id', 'weekday', 'start_time', 'end_time'],
                'schedule_slots_term_section_weekday_time_unique',
            );
            $table->index(
                ['academic_term_id', 'weekday', 'start_time'],
                'schedule_slots_term_weekday_time_index',
            );
            $table->index(
                ['subject_id', 'subject_section_id'],
                'schedule_slots_subject_section_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_section_schedule_slots');
    }
};
