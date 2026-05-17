<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subject_sections') || Schema::hasColumn('subject_sections', 'lecturer_id')) {
            return;
        }

        Schema::table('subject_sections', function (Blueprint $table): void {
            $table->foreignId('lecturer_id')
                ->nullable()
                ->after('subject_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('subject_sections')
            ->whereNull('lecturer_id')
            ->orderBy('id')
            ->chunkById(500, function ($sections): void {
                $lecturersBySubject = DB::table('subjects')
                    ->whereIn('id', $sections->pluck('subject_id')->unique()->all())
                    ->pluck('lecturer_id', 'id');

                foreach ($sections as $section) {
                    $lecturerId = $lecturersBySubject[$section->subject_id] ?? null;

                    if ($lecturerId) {
                        DB::table('subject_sections')
                            ->where('id', $section->id)
                            ->update(['lecturer_id' => $lecturerId]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('subject_sections') || ! Schema::hasColumn('subject_sections', 'lecturer_id')) {
            return;
        }

        Schema::table('subject_sections', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lecturer_id');
        });
    }
};
