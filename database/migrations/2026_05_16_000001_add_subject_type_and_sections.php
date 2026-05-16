<?php

use App\Models\Subject;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            if (! Schema::hasColumn('subjects', 'subject_type')) {
                $table->string('subject_type', 20)
                    ->default(Subject::TYPE_THEORETICAL)
                    ->after('name')
                    ->index();
            }
        });

        if (! Schema::hasTable('subject_sections')) {
            Schema::create('subject_sections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
                $table->string('code', 20);
                $table->string('name')->nullable();
                $table->unsignedInteger('capacity')->nullable();
                $table->timestamps();

                $table->unique(['subject_id', 'code']);
                $table->index('code');
            });
        }

        Schema::table('lecture_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('lecture_sessions', 'subject_section_id')) {
                $table->foreignId('subject_section_id')
                    ->nullable()
                    ->after('subject_id')
                    ->constrained('subject_sections')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('lecture_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('lecture_sessions', 'subject_section_id')) {
                $table->dropConstrainedForeignId('subject_section_id');
            }
        });

        Schema::dropIfExists('subject_sections');

        Schema::table('subjects', function (Blueprint $table): void {
            if (Schema::hasColumn('subjects', 'subject_type')) {
                $table->dropColumn('subject_type');
            }
        });
    }
};
