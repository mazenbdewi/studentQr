<?php

use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportIssueAction;
use App\Models\ScheduleImportRow;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/../Support/ScheduleImportReconciliationFixtures.php';

it('uses row-derived batch provenance and sheet-aware row uniqueness', function (): void {
    $path = reconciliationWorkbook([]);

    try {
        [$term, , $batch] = reconciliationSource($path);
        $attributes = [
            'import_batch_id' => $batch->id,
            'academic_term_id' => $term->id,
            'source_sheet_name' => 'Sheet A',
            'source_row_number' => 2,
            'row_fingerprint' => hash('sha256', 'row'),
            'source_payload' => [],
            'normalized_payload' => [],
            'original_import_status' => ScheduleImportRow::ORIGINAL_REJECTED,
            'current_reconciliation_status' => ScheduleImportRow::STATUS_UNRESOLVED,
        ];
        ScheduleImportRow::query()->create($attributes);
        expect(fn () => ScheduleImportRow::query()->create($attributes))->toThrow(QueryException::class);

        ScheduleImportRow::query()->create([...$attributes, 'source_sheet_name' => 'Sheet B']);
        expect(ScheduleImportRow::query()->count())->toBe(2)
            ->and(Schema::hasColumn('schedule_import_issues', 'import_batch_id'))->toBeFalse()
            ->and(Schema::hasColumn('schedule_import_issue_actions', 'import_batch_id'))->toBeFalse();
    } finally {
        @unlink($path);
    }
});

it('nulls optional catalog references while immutable action snapshots survive', function (): void {
    $path = reconciliationWorkbook([]);

    try {
        [$term, , $batch, $subject, $section] = reconciliationSource($path);
        $row = ScheduleImportRow::query()->create([
            'import_batch_id' => $batch->id,
            'academic_term_id' => $term->id,
            'source_sheet_name' => 'Schedule',
            'source_row_number' => 2,
            'row_fingerprint' => hash('sha256', 'row'),
            'source_payload' => [],
            'normalized_payload' => [],
            'original_import_status' => ScheduleImportRow::ORIGINAL_REJECTED,
            'current_reconciliation_status' => ScheduleImportRow::STATUS_RESOLVED,
        ]);
        $issue = ScheduleImportIssue::query()->create([
            'schedule_import_row_id' => $row->id,
            'deduplication_key' => hash('sha256', 'issue'),
            'issue_type' => ScheduleImportIssue::TYPE_SECTION_NOT_FOUND,
            'severity' => ScheduleImportIssue::SEVERITY_ERROR,
            'reason_ar' => 'اختبار',
            'resolved_subject_id' => $subject->id,
            'resolved_subject_section_id' => $section->id,
            'resolution_status' => ScheduleImportIssue::STATUS_RESOLVED,
        ]);
        $action = ScheduleImportIssueAction::query()->create([
            'schedule_import_issue_id' => $issue->id,
            'action' => ScheduleImportIssueAction::ACTION_LINK,
            'previous_status' => ScheduleImportIssue::STATUS_UNRESOLVED,
            'new_status' => ScheduleImportIssue::STATUS_RESOLVED,
            'selected_subject_id' => $subject->id,
            'selected_subject_section_id' => $section->id,
            'previous_state' => [],
            'new_state' => ['subject' => ['id' => $subject->id, 'code' => $subject->code, 'name' => $subject->name], 'section' => ['id' => $section->id, 'code' => $section->code]],
            'performed_at' => now(),
        ]);
        $section->delete();

        expect($issue->fresh()->resolved_subject_section_id)->toBeNull()
            ->and($action->fresh()->selected_subject_section_id)->toBeNull()
            ->and($action->fresh()->new_state['section']['code'])->toBe('T1')
            ->and($action->update(['note' => 'should not change']))->toBeFalse()
            ->and($action->delete())->toBeFalse()
            ->and(ScheduleImportIssueAction::query()->whereKey($action->id)->exists())->toBeTrue();
    } finally {
        @unlink($path);
    }
});
