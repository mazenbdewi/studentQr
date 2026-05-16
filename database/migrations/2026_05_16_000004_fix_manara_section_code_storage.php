<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subject_sections')) {
            return;
        }

        Schema::table('subject_sections', function (Blueprint $table): void {
            if (Schema::hasColumn('subject_sections', 'code')) {
                $table->string('code', 20)->change();
            }

            if (! Schema::hasColumn('subject_sections', 'section_number')) {
                $table->unsignedInteger('section_number')
                    ->nullable()
                    ->after('code');
            }
        });

        if (Schema::hasColumn('subject_sections', 'raw_section_number')) {
            DB::table('subject_sections')
                ->select(['id', 'code', 'raw_section_number'])
                ->chunkById(200, function ($sections): void {
                    foreach ($sections as $section) {
                        $raw = $section->raw_section_number
                            ?: preg_replace('/^[TP]\s*/i', '', (string) $section->code);

                        DB::table('subject_sections')
                            ->where('id', $section->id)
                            ->update([
                                'section_number' => ctype_digit((string) $raw) ? (int) $raw : null,
                            ]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('subject_sections')) {
            return;
        }

        Schema::table('subject_sections', function (Blueprint $table): void {
            if (Schema::hasColumn('subject_sections', 'section_number')) {
                $table->dropColumn('section_number');
            }
        });
    }
};
