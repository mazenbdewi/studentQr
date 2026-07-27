<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table): void {
            $table->string('source_file_path')->nullable()->after('source_filename');
        });

        Schema::create('schedule_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->restrictOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->restrictOnDelete();
            $table->string('source_sheet_name');
            $table->unsignedInteger('source_row_number');
            $table->string('row_fingerprint', 64)->index();
            $table->json('source_payload');
            $table->json('normalized_payload');
            $table->string('original_import_status', 40)->index();
            $table->string('current_reconciliation_status', 40)->index();
            $table->json('import_result')->nullable();
            $table->timestamps();

            $table->unique(
                ['import_batch_id', 'source_sheet_name', 'source_row_number'],
                'schedule_import_rows_batch_sheet_row_unique',
            );
            $table->index(
                ['import_batch_id', 'original_import_status'],
                'schedule_import_rows_batch_original_status_index',
            );
            $table->index(
                ['import_batch_id', 'current_reconciliation_status'],
                'schedule_import_rows_batch_current_status_index',
            );
            $table->index(
                ['academic_term_id', 'source_row_number'],
                'schedule_import_rows_term_row_index',
            );
        });

        Schema::create('schedule_import_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('schedule_import_row_id')
                ->constrained('schedule_import_rows')
                ->restrictOnDelete();
            $table->string('deduplication_key', 64)->unique();
            $table->string('issue_type', 64)->index();
            $table->string('severity', 20)->index();
            $table->text('reason_ar');
            $table->json('suggested_matches')->nullable();
            $table->foreignId('resolved_subject_id')
                ->nullable()
                ->constrained('subjects')
                ->nullOnDelete();
            $table->foreignId('resolved_subject_section_id')
                ->nullable()
                ->constrained('subject_sections')
                ->nullOnDelete();
            $table->string('resolution_status', 40)->index();
            $table->string('resolution_action', 40)->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->json('retry_result')->nullable();
            $table->timestamps();

            $table->index(
                ['schedule_import_row_id', 'severity', 'resolution_status'],
                'schedule_import_issues_row_severity_status_index',
            );
        });

        Schema::create('schedule_import_issue_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('schedule_import_issue_id')
                ->constrained('schedule_import_issues')
                ->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('previous_status', 40);
            $table->string('new_status', 40);
            $table->foreignId('previous_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('previous_subject_section_id')->nullable();
            $table->foreignId('selected_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('selected_subject_section_id')->nullable();
            $table->json('previous_state');
            $table->json('new_state');
            $table->json('result')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('performed_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('previous_subject_section_id', 'schedule_issue_actions_previous_section_fk')
                ->references('id')
                ->on('subject_sections')
                ->nullOnDelete();
            $table->foreign('selected_subject_section_id', 'schedule_issue_actions_selected_section_fk')
                ->references('id')
                ->on('subject_sections')
                ->nullOnDelete();

            $table->index(
                ['schedule_import_issue_id', 'performed_at'],
                'schedule_import_issue_actions_issue_time_index',
            );
            $table->index(
                ['actor_user_id', 'performed_at'],
                'schedule_import_issue_actions_actor_time_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_import_issue_actions');
        Schema::dropIfExists('schedule_import_issues');
        Schema::dropIfExists('schedule_import_rows');

        Schema::table('import_batches', function (Blueprint $table): void {
            $table->dropColumn('source_file_path');
        });
    }
};
