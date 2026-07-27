<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecture_sessions', function (Blueprint $table): void {
            $table->foreignId('academic_term_id')
                ->nullable()
                ->after('id')
                ->constrained('academic_terms')
                ->restrictOnDelete();
            $table->foreignId('subject_section_schedule_slot_id')
                ->nullable()
                ->after('subject_section_id');
            $table->timestamp('generated_from_weekly_schedule_at')
                ->nullable()
                ->after('subject_section_schedule_slot_id');
            $table->foreignId('lecture_session_generation_run_id')
                ->nullable()
                ->after('generated_from_weekly_schedule_at');

            $table->foreign(
                'subject_section_schedule_slot_id',
                'lecture_sess_source_slot_fk',
            )
                ->references('id')
                ->on('subject_section_schedule_slots')
                ->restrictOnDelete();
            $table->foreign(
                'lecture_session_generation_run_id',
                'lecture_sess_generation_run_fk',
            )
                ->references('id')
                ->on('lecture_session_generation_runs')
                ->nullOnDelete();
            $table->unique(
                ['subject_section_schedule_slot_id', 'session_date'],
                'lecture_sess_slot_date_unique',
            );
            $table->index(
                ['academic_term_id', 'session_date', 'start_time'],
                'lecture_sess_term_date_time_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('lecture_sessions', function (Blueprint $table): void {
            $table->dropIndex('lecture_sess_term_date_time_index');
            $table->dropUnique('lecture_sess_slot_date_unique');
            $table->dropForeign('lecture_sess_generation_run_fk');
            $table->dropForeign('lecture_sess_source_slot_fk');
            $table->dropConstrainedForeignId('academic_term_id');
            $table->dropColumn([
                'subject_section_schedule_slot_id',
                'generated_from_weekly_schedule_at',
                'lecture_session_generation_run_id',
            ]);
        });
    }
};
