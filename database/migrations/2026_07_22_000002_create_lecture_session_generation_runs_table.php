<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecture_session_generation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_term_id')
                ->constrained('academic_terms')
                ->restrictOnDelete();
            $table->foreignId('schedule_import_batch_id')
                ->nullable()
                ->constrained('import_batches')
                ->nullOnDelete();
            $table->foreignId('started_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->date('teaching_start_date');
            $table->date('teaching_end_date');
            $table->string('status', 40)->index();
            $table->unsignedInteger('source_slot_count')->default(0);
            $table->unsignedInteger('candidate_session_count')->default(0);
            $table->unsignedInteger('created_session_count')->default(0);
            $table->unsignedInteger('skipped_session_count')->default(0);
            $table->unsignedInteger('blocked_slot_count')->default(0);
            $table->unsignedInteger('conflict_count')->default(0);
            $table->json('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecture_session_generation_runs');
    }
};
