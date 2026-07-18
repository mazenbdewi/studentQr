<?php

use App\Models\LectureSession;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportIssueAction;
use App\Models\ScheduleImportRow;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Services\ScheduleImportReconciliationService;

require_once __DIR__.'/../Support/ScheduleImportReconciliationFixtures.php';

it('retries one mapped row idempotently without users or dated sessions', function (): void {
    $path = reconciliationWorkbook([]);

    try {
        [$term, , $batch, $subject, $section] = reconciliationSource($path);
        $row = ScheduleImportRow::query()->create([
            'import_batch_id' => $batch->id,
            'academic_term_id' => $term->id,
            'source_sheet_name' => 'Schedule',
            'source_row_number' => 2,
            'row_fingerprint' => hash('sha256', 'retry-row'),
            'source_payload' => ['subject_code' => 'UNKNOWN'],
            'normalized_payload' => [
                'weekday_values' => [6 => '08:30AM-10:30AM'],
                'teacher_name' => null,
                'teacher_name_source' => null,
                'hall_name' => null,
                'hall_name_source' => null,
                'section_capacity' => 20,
                'expected_student_count' => 18,
            ],
            'original_import_status' => ScheduleImportRow::ORIGINAL_REJECTED,
            'current_reconciliation_status' => ScheduleImportRow::STATUS_UNRESOLVED,
        ]);
        $issue = ScheduleImportIssue::query()->create([
            'schedule_import_row_id' => $row->id,
            'deduplication_key' => hash('sha256', 'retry-issue'),
            'issue_type' => ScheduleImportIssue::TYPE_SUBJECT_NOT_FOUND,
            'severity' => ScheduleImportIssue::SEVERITY_ERROR,
            'reason_ar' => 'مقرر مفقود',
            'resolved_subject_id' => $subject->id,
            'resolved_subject_section_id' => $section->id,
            'resolution_status' => ScheduleImportIssue::STATUS_RESOLVED,
        ]);
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        $usersBefore = User::query()->count();
        $service = app(ScheduleImportReconciliationService::class);
        $first = $service->retry($issue, $super);
        $second = $service->retry($first, $super);

        expect(SubjectSectionScheduleSlot::query()->count())->toBe(1)
            ->and($first->retry_result['created_slot_ids'])->toHaveCount(1)
            ->and($second->retry_result['already_existing_slot_ids'])->toHaveCount(1)
            ->and(User::query()->count())->toBe($usersBefore)
            ->and(LectureSession::query()->count())->toBe(0)
            ->and(ScheduleImportIssueAction::query()->where('action', ScheduleImportIssueAction::ACTION_RETRY)->count())->toBe(2);
    } finally {
        @unlink($path);
    }
});

it('does not overwrite a conflicting existing slot', function (): void {
    $path = reconciliationWorkbook([]);

    try {
        [$term, , $batch, $subject, $section] = reconciliationSource($path);
        $slot = SubjectSectionScheduleSlot::query()->create([
            'import_batch_id' => $batch->id, 'academic_term_id' => $term->id, 'subject_id' => $subject->id,
            'subject_section_id' => $section->id, 'weekday' => 6, 'start_time' => '08:30:00', 'end_time' => '10:30:00',
            'section_capacity' => 99,
        ]);
        $row = ScheduleImportRow::query()->create([
            'import_batch_id' => $batch->id, 'academic_term_id' => $term->id, 'source_sheet_name' => 'Schedule',
            'source_row_number' => 2, 'row_fingerprint' => hash('sha256', 'conflict-row'), 'source_payload' => [],
            'normalized_payload' => ['weekday_values' => [6 => '08:30AM-10:30AM'], 'section_capacity' => 20, 'expected_student_count' => null, 'teacher_name' => null, 'hall_name' => null],
            'original_import_status' => ScheduleImportRow::ORIGINAL_REJECTED, 'current_reconciliation_status' => ScheduleImportRow::STATUS_UNRESOLVED,
        ]);
        $issue = ScheduleImportIssue::query()->create([
            'schedule_import_row_id' => $row->id, 'deduplication_key' => hash('sha256', 'conflict-issue'),
            'issue_type' => ScheduleImportIssue::TYPE_SECTION_NOT_FOUND, 'severity' => ScheduleImportIssue::SEVERITY_ERROR,
            'reason_ar' => 'اختبار', 'resolved_subject_id' => $subject->id, 'resolved_subject_section_id' => $section->id,
            'resolution_status' => ScheduleImportIssue::STATUS_RESOLVED,
        ]);
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        $result = app(ScheduleImportReconciliationService::class)->retry($issue, $super);

        expect($result->resolution_status)->toBe(ScheduleImportIssue::STATUS_RETRY_FAILED)
            ->and($slot->fresh()->section_capacity)->toBe(99)
            ->and(SubjectSectionScheduleSlot::query()->count())->toBe(1);
    } finally {
        @unlink($path);
    }
});
