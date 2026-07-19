<?php

use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportIssueAction;
use App\Models\ScheduleImportRow;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Services\ScheduleImportReconciliationService;

require_once __DIR__.'/../Support/ScheduleImportReconciliationFixtures.php';

it('retains deterministic unique row-to-slot ids from imported and reconciliation results', function (): void {
    $path = reconciliationWorkbook([]);

    try {
        [$term, , $batch, $subject, $section] = reconciliationSource($path);
        $slots = collect([6, 1])->map(fn (int $weekday) => SubjectSectionScheduleSlot::query()->create([
            'import_batch_id' => $batch->id, 'academic_term_id' => $term->id, 'subject_id' => $subject->id,
            'subject_section_id' => $section->id, 'weekday' => $weekday, 'start_time' => '08:30:00', 'end_time' => '10:30:00',
        ]));
        $row = ScheduleImportRow::query()->create([
            'import_batch_id' => $batch->id, 'academic_term_id' => $term->id, 'source_sheet_name' => 'Schedule',
            'source_row_number' => 2, 'row_fingerprint' => hash('sha256', 'relation-row'), 'source_payload' => [], 'normalized_payload' => [],
            'original_import_status' => ScheduleImportRow::ORIGINAL_IMPORTED, 'current_reconciliation_status' => ScheduleImportRow::STATUS_RESOLVED,
            'import_result' => ['slot_ids' => [$slots[0]->id, $slots[1]->id], 'reconciliation_slot_ids' => [$slots[1]->id]],
        ]);

        expect($row->relatedScheduleSlotIds())->toBe([$slots[0]->id, $slots[1]->id]);
    } finally {
        @unlink($path);
    }
});

it('retries all row issues idempotently and appends immutable audits', function (): void {
    $path = reconciliationWorkbook([]);

    try {
        [$term, , $batch, $subject, $section] = reconciliationSource($path);
        $row = ScheduleImportRow::query()->create([
            'import_batch_id' => $batch->id, 'academic_term_id' => $term->id, 'source_sheet_name' => 'Schedule',
            'source_row_number' => 2, 'row_fingerprint' => hash('sha256', 'full-retry-row'), 'source_payload' => [],
            'normalized_payload' => ['weekday_values' => [6 => '08:30AM-10:30AM'], 'section_capacity' => 20, 'expected_student_count' => 18],
            'original_import_status' => ScheduleImportRow::ORIGINAL_REJECTED, 'current_reconciliation_status' => ScheduleImportRow::STATUS_UNRESOLVED,
            'resolved_subject_id' => $subject->id, 'resolved_subject_section_id' => $section->id,
        ]);
        foreach ([ScheduleImportIssue::TYPE_SUBJECT_NOT_FOUND, ScheduleImportIssue::TYPE_SECTION_NOT_FOUND] as $index => $type) {
            ScheduleImportIssue::query()->create([
                'schedule_import_row_id' => $row->id, 'deduplication_key' => hash('sha256', 'retry-'.$type),
                'issue_type' => $type, 'severity' => ScheduleImportIssue::SEVERITY_ERROR, 'reason_ar' => $type,
                'resolution_status' => ScheduleImportIssue::STATUS_UNRESOLVED,
            ]);
        }
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        $service = app(ScheduleImportReconciliationService::class);
        $first = $service->retryRow($row, $super);
        $slotCount = SubjectSectionScheduleSlot::query()->count();
        $second = $service->retryRow($row->fresh(), $super);

        expect($first['created_slot_ids'])->toHaveCount(1)
            ->and($second['already_existing_slot_ids'])->toHaveCount(1)
            ->and(SubjectSectionScheduleSlot::query()->count())->toBe($slotCount)
            ->and($row->issues()->where('resolution_status', ScheduleImportIssue::STATUS_RESOLVED)->count())->toBe(2)
            ->and(ScheduleImportIssueAction::query()->where('action', ScheduleImportIssueAction::ACTION_RETRY)->count())->toBe(4);

        $action = ScheduleImportIssueAction::query()->firstOrFail();
        expect($action->update(['note' => 'تعديل ممنوع']))->toBeFalse()
            ->and($action->delete())->toBeFalse();
    } finally {
        @unlink($path);
    }
});
