<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->foreignId('academic_term_id')
                ->nullable()
                ->after('subject_id')
                ->constrained('academic_terms')
                ->restrictOnDelete();

            $table->dropUnique('enrollments_student_id_subject_id_unique');
            $table->unique(
                ['academic_term_id', 'student_id', 'subject_id'],
                'enrollments_term_student_subject_unique',
            );
        });

        Schema::table('subject_sections', function (Blueprint $table): void {
            $table->foreignId('academic_term_id')
                ->nullable()
                ->after('subject_id')
                ->constrained('academic_terms')
                ->restrictOnDelete();

            $table->index('subject_id', 'subject_sections_subject_id_index');
            $table->dropUnique('subject_sections_subject_id_code_unique');
            $table->dropUnique('subject_sections_subject_type_code_unique');
            $table->unique(
                ['academic_term_id', 'subject_id', 'code'],
                'subject_sections_term_subject_code_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('subject_sections', function (Blueprint $table): void {
            $table->dropUnique('subject_sections_term_subject_code_unique');
            $table->dropConstrainedForeignId('academic_term_id');
            $table->unique(['subject_id', 'code']);
            $table->unique(
                ['subject_id', 'section_type', 'code'],
                'subject_sections_subject_type_code_unique',
            );
            $table->dropIndex('subject_sections_subject_id_index');
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropUnique('enrollments_term_student_subject_unique');
            $table->dropConstrainedForeignId('academic_term_id');
            $table->unique(['student_id', 'subject_id']);
        });
    }
};
