<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_import_rows', function (Blueprint $table): void {
            $table->foreignId('resolved_subject_id')->nullable()->after('import_result')->constrained('subjects')->nullOnDelete();
            $table->foreignId('resolved_subject_section_id')->nullable()->after('resolved_subject_id')->constrained('subject_sections')->nullOnDelete();
            $table->foreignId('resolved_lecturer_id')->nullable()->after('resolved_subject_section_id')->constrained('lecturers')->nullOnDelete();
            $table->foreignId('resolved_hall_id')->nullable()->after('resolved_lecturer_id')->constrained('halls')->nullOnDelete();
            $table->unsignedInteger('resolved_section_capacity')->nullable()->after('resolved_hall_id');
            $table->unsignedInteger('resolved_expected_student_count')->nullable()->after('resolved_section_capacity');
            $table->json('resolution_payload')->nullable()->after('resolved_expected_student_count');
            $table->foreignId('resolution_updated_by')->nullable()->after('resolution_payload')->constrained('users')->nullOnDelete();
            $table->timestamp('resolution_updated_at')->nullable()->after('resolution_updated_by');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_import_rows', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('resolution_updated_by');
            $table->dropConstrainedForeignId('resolved_hall_id');
            $table->dropConstrainedForeignId('resolved_lecturer_id');
            $table->dropConstrainedForeignId('resolved_subject_section_id');
            $table->dropConstrainedForeignId('resolved_subject_id');
            $table->dropColumn([
                'resolved_section_capacity',
                'resolved_expected_student_count',
                'resolution_payload',
                'resolution_updated_at',
            ]);
        });
    }
};
