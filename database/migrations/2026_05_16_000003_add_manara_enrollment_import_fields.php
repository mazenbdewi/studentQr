<?php

use App\Models\Subject;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subject_sections')) {
            Schema::table('subject_sections', function (Blueprint $table): void {
                if (Schema::hasColumn('subject_sections', 'code')) {
                    $table->string('code', 20)->change();
                }
            });

            Schema::table('subject_sections', function (Blueprint $table): void {
                if (! Schema::hasColumn('subject_sections', 'section_type')) {
                    $table->string('section_type', 20)
                        ->default(Subject::TYPE_THEORETICAL)
                        ->after('subject_id')
                        ->index();
                }

                if (! Schema::hasColumn('subject_sections', 'section_number')) {
                    $table->unsignedInteger('section_number')
                        ->nullable()
                        ->after('code');
                }

                if (! Schema::hasColumn('subject_sections', 'raw_section_number')) {
                    $table->string('raw_section_number', 20)
                        ->nullable()
                        ->after('section_number');
                }
            });

            DB::table('subject_sections')
                ->where('code', 'like', 'P%')
                ->update(['section_type' => Subject::TYPE_PRACTICAL]);

            DB::table('subject_sections')
                ->whereNull('raw_section_number')
                ->select(['id', 'code'])
                ->chunkById(200, function ($sections): void {
                    foreach ($sections as $section) {
                        $raw = preg_replace('/^[TP]\s*/i', '', (string) $section->code);

                        DB::table('subject_sections')
                            ->where('id', $section->id)
                            ->update([
                                'raw_section_number' => $raw ?: null,
                                'section_number' => ctype_digit((string) $raw) ? (int) $raw : null,
                            ]);
                    }
                });

            Schema::table('subject_sections', function (Blueprint $table): void {
                $table->unique(
                    ['subject_id', 'section_type', 'code'],
                    'subject_sections_subject_type_code_unique',
                );
            });
        }

        if (Schema::hasTable('enrollments')) {
            Schema::table('enrollments', function (Blueprint $table): void {
                if (! Schema::hasColumn('enrollments', 'theoretical_section_id')) {
                    $table->foreignId('theoretical_section_id')
                        ->nullable()
                        ->after('subject_id')
                        ->constrained('subject_sections')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('enrollments', 'practical_section_id')) {
                    $table->foreignId('practical_section_id')
                        ->nullable()
                        ->after('theoretical_section_id')
                        ->constrained('subject_sections')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('enrollments', 'registration_date')) {
                    $table->date('registration_date')
                        ->nullable()
                        ->after('practical_section_id')
                        ->index();
                }
            });
        }

        if (Schema::hasTable('faculties')) {
            Schema::table('faculties', function (Blueprint $table): void {
                $table->index('name', 'faculties_name_lookup_index');
            });
        }

        if (Schema::hasTable('departments')) {
            Schema::table('departments', function (Blueprint $table): void {
                $table->index(['faculty_id', 'name'], 'departments_faculty_name_lookup_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('departments')) {
            Schema::table('departments', function (Blueprint $table): void {
                $table->dropIndex('departments_faculty_name_lookup_index');
            });
        }

        if (Schema::hasTable('faculties')) {
            Schema::table('faculties', function (Blueprint $table): void {
                $table->dropIndex('faculties_name_lookup_index');
            });
        }

        if (Schema::hasTable('enrollments')) {
            Schema::table('enrollments', function (Blueprint $table): void {
                if (Schema::hasColumn('enrollments', 'theoretical_section_id')) {
                    $table->dropConstrainedForeignId('theoretical_section_id');
                }

                if (Schema::hasColumn('enrollments', 'practical_section_id')) {
                    $table->dropConstrainedForeignId('practical_section_id');
                }

                if (Schema::hasColumn('enrollments', 'registration_date')) {
                    $table->dropColumn('registration_date');
                }
            });
        }

        if (Schema::hasTable('subject_sections')) {
            Schema::table('subject_sections', function (Blueprint $table): void {
                $table->dropUnique('subject_sections_subject_type_code_unique');

                if (Schema::hasColumn('subject_sections', 'section_type')) {
                    $table->dropColumn('section_type');
                }

                if (Schema::hasColumn('subject_sections', 'section_number')) {
                    $table->dropColumn('section_number');
                }

                if (Schema::hasColumn('subject_sections', 'raw_section_number')) {
                    $table->dropColumn('raw_section_number');
                }
            });
        }
    }
};
