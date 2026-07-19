<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_import_row_time_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('schedule_import_row_id');
            $table->foreign('schedule_import_row_id', 'sirto_schedule_row_fk')
                ->references('id')
                ->on('schedule_import_rows')
                ->restrictOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('lecturer_id')->nullable()->constrained('lecturers')->nullOnDelete();
            $table->foreignId('hall_id')->nullable()->constrained('halls')->nullOnDelete();
            $table->unsignedInteger('section_capacity')->nullable();
            $table->unsignedInteger('expected_student_count')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['schedule_import_row_id', 'weekday', 'start_time', 'end_time'],
                'schedule_row_time_overrides_row_weekday_time_unique',
            );
            $table->index(
                ['schedule_import_row_id', 'weekday'],
                'schedule_row_time_overrides_row_weekday_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_import_row_time_overrides');
    }
};
