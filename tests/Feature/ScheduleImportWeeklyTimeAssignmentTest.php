<?php

use App\Models\Hall;
use App\Models\Lecturer;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportRow;
use App\Models\ScheduleImportRowTimeOverride;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Services\ScheduleImportReconciliationService;

require_once __DIR__.'/../Support/ScheduleImportReconciliationFixtures.php';

function weeklyTimeRow(): array
{
    $path = reconciliationWorkbook([]);
    $suffix = strtoupper(substr(hash('sha256', uniqid('', true)), 0, 8));
    [$term, , $batch, $subject, $section] = reconciliationSource($path, type: \App\Models\ImportBatch::TYPE_WEEKLY_SCHEDULE, suffix: $suffix);
    $row = ScheduleImportRow::query()->create([
        'import_batch_id' => $batch->id, 'academic_term_id' => $term->id, 'source_sheet_name' => 'Schedule',
        'source_row_number' => 2, 'row_fingerprint' => hash('sha256', uniqid('time-row-', true)),
        'source_payload' => ['expected_student_count' => 18],
        'normalized_payload' => ['subject_code_key' => strtolower($subject->code), 'section_type' => 'T', 'section_code' => 'T1', 'expected_student_count' => 18],
        'original_import_status' => ScheduleImportRow::ORIGINAL_UNSCHEDULED,
        'current_reconciliation_status' => ScheduleImportRow::STATUS_UNRESOLVED,
        'resolved_subject_id' => $subject->id, 'resolved_subject_section_id' => $section->id,
    ]);
    ScheduleImportIssue::query()->create([
        'schedule_import_row_id' => $row->id, 'deduplication_key' => hash('sha256', 'no-time-'.$row->id),
        'issue_type' => ScheduleImportIssue::TYPE_NO_WEEKLY_TIME, 'severity' => ScheduleImportIssue::SEVERITY_WARNING,
        'reason_ar' => 'لا موعد', 'resolution_status' => ScheduleImportIssue::STATUS_UNRESOLVED,
    ]);

    return [$path, $term, $batch, $subject, $section, $row];
}

it('stores multiple manual times and permits adding another later', function (): void {
    [$path, , , , , $row] = weeklyTimeRow();

    try {
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        $service = app(ScheduleImportReconciliationService::class);
        $first = $service->addWeeklyTimes($row, [
            ['weekday' => 6, 'start_time' => '08:30', 'end_time' => '10:30', 'expected_student_count' => 18],
            ['weekday' => 1, 'start_time' => '10:30', 'end_time' => '12:30', 'expected_student_count' => 18],
        ], $super);
        $second = $service->addWeeklyTimes($row->fresh(), [
            ['weekday' => 3, 'start_time' => '12:30', 'end_time' => '14:30', 'expected_student_count' => 18],
        ], $super);

        expect($first['created_slot_ids'])->toHaveCount(2)
            ->and($second['created_slot_ids'])->toHaveCount(1)
            ->and(ScheduleImportRowTimeOverride::query()->where('schedule_import_row_id', $row->id)->count())->toBe(3)
            ->and(SubjectSectionScheduleSlot::query()->count())->toBe(3)
            ->and($row->fresh()->relatedScheduleSlotIds())->toHaveCount(3);

        expect(fn () => $service->addWeeklyTimes($row->fresh(), [
            ['weekday' => 6, 'start_time' => '08:30', 'end_time' => '10:30'],
        ], $super))->toThrow(RuntimeException::class)
            ->and(ScheduleImportRowTimeOverride::query()->count())->toBe(3)
            ->and(SubjectSectionScheduleSlot::query()->count())->toBe(3);
    } finally {
        @unlink($path);
    }
});

it('rejects invalid end time and overlap without creating records', function (): void {
    [$path, $term, $batch, $subject, $section, $row] = weeklyTimeRow();

    try {
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        $service = app(ScheduleImportReconciliationService::class);
        expect(fn () => $service->addWeeklyTimes($row, [['weekday' => 6, 'start_time' => '10:30', 'end_time' => '08:30']], $super))->toThrow(RuntimeException::class);

        SubjectSectionScheduleSlot::query()->create([
            'import_batch_id' => $batch->id, 'academic_term_id' => $term->id, 'subject_id' => $subject->id,
            'subject_section_id' => $section->id, 'weekday' => 6, 'start_time' => '09:00:00', 'end_time' => '11:00:00',
        ]);
        expect(fn () => $service->addWeeklyTimes($row->fresh(), [['weekday' => 6, 'start_time' => '08:30', 'end_time' => '10:30']], $super))->toThrow(RuntimeException::class)
            ->and(ScheduleImportRowTimeOverride::query()->count())->toBe(0)
            ->and(SubjectSectionScheduleSlot::query()->count())->toBe(1);
    } finally {
        @unlink($path);
    }
});

it('reuses an exact slot and detects lecturer and hall overlaps', function (): void {
    [$path, $term, $batch, $subject, $section, $row] = weeklyTimeRow();

    try {
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        $service = app(ScheduleImportReconciliationService::class);
        $exact = SubjectSectionScheduleSlot::query()->create([
            'import_batch_id' => $batch->id, 'academic_term_id' => $term->id, 'subject_id' => $subject->id,
            'subject_section_id' => $section->id, 'weekday' => 6, 'start_time' => '08:30:00', 'end_time' => '10:30:00',
        ]);
        $result = $service->addWeeklyTimes($row, [['weekday' => 6, 'start_time' => '08:30', 'end_time' => '10:30']], $super);

        expect($result['status'])->toBe('already_exists')
            ->and($result['already_existing_slot_ids'])->toBe([$exact->id])
            ->and(SubjectSectionScheduleSlot::query()->count())->toBe(1);

        [$path2, $term2, $batch2, $subject2, $section2, $row2] = weeklyTimeRow();
        $lecturer = Lecturer::query()->create(['name' => 'مدرس تعارض', 'canonical_name' => 'مدرس تعارض', 'is_active' => true]);
        $hall = Hall::query()->create(['code' => 'CONFLICT-HALL', 'name' => 'قاعة تعارض', 'floor' => null, 'is_active' => true]);
        $otherSection = SubjectSection::query()->create([
            'academic_term_id' => $term2->id, 'subject_id' => $subject2->id, 'section_type' => $section2->section_type,
            'code' => 'T2', 'raw_section_number' => '2',
        ]);
        SubjectSectionScheduleSlot::query()->create([
            'import_batch_id' => $batch2->id, 'academic_term_id' => $term2->id, 'subject_id' => $subject2->id,
            'subject_section_id' => $otherSection->id, 'lecturer_id' => $lecturer->id, 'hall_id' => $hall->id,
            'weekday' => 1, 'start_time' => '09:00:00', 'end_time' => '11:00:00',
        ]);

        expect(fn () => $service->addWeeklyTimes($row2, [[
            'weekday' => 1, 'start_time' => '10:00', 'end_time' => '12:00', 'lecturer_id' => $lecturer->id, 'hall_id' => $hall->id,
        ]], $super))->toThrow(RuntimeException::class)
            ->and(ScheduleImportRowTimeOverride::query()->where('schedule_import_row_id', $row2->id)->count())->toBe(0)
            ->and(SubjectSectionScheduleSlot::query()->where('import_batch_id', $batch2->id)->count())->toBe(1);
    } finally {
        @unlink($path);
        if (isset($path2)) {
            @unlink($path2);
        }
    }
});
