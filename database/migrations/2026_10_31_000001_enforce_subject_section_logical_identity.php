<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subject_sections', function (Blueprint $table): void {
            // MySQL may use the old composite unique index to support this foreign key.
            $table->index('academic_term_id', 'subject_sections_academic_term_id_index');
            $table->foreignId('academic_term_id')->nullable(false)->change();
        });

        Schema::table('subject_sections', function (Blueprint $table): void {
            $table->dropUnique('subject_sections_term_subject_code_unique');
            $table->unique(
                ['academic_term_id', 'subject_id', 'section_type', 'code'],
                'subject_sections_logical_identity_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('subject_sections', function (Blueprint $table): void {
            $table->dropUnique('subject_sections_logical_identity_unique');
            $table->unique(
                ['academic_term_id', 'subject_id', 'code'],
                'subject_sections_term_subject_code_unique',
            );
            $table->foreignId('academic_term_id')->nullable()->change();
        });

        Schema::table('subject_sections', function (Blueprint $table): void {
            $table->dropIndex('subject_sections_academic_term_id_index');
        });
    }
};
