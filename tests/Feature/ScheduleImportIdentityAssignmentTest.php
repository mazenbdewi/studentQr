<?php

use App\Models\Hall;
use App\Models\Lecturer;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportIssueAction;
use App\Models\ScheduleImportRow;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Services\ScheduleImportReconciliationService;
use Spatie\Permission\Models\Role;

require_once __DIR__.'/../Support/ScheduleImportReconciliationFixtures.php';

beforeEach(function (): void {
    Role::findOrCreate('super-admin', 'web');
    User::created(function (User $user): void {
        if ($user->role === 'super_admin') {
            $user->assignRole('super-admin');
        }
    });
});

function identityAssignmentRow(string $issueType, ?int $lecturerId = null, ?int $hallId = null): array
{
    $path = reconciliationWorkbook([]);
    $suffix = strtoupper(substr(hash('sha256', uniqid('', true)), 0, 8));
    [$term, , $batch, $subject, $section] = reconciliationSource($path, type: \App\Models\ImportBatch::TYPE_WEEKLY_SCHEDULE, suffix: $suffix);
    $slot = SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $batch->id, 'academic_term_id' => $term->id, 'subject_id' => $subject->id,
        'subject_section_id' => $section->id, 'lecturer_id' => $lecturerId, 'hall_id' => $hallId,
        'weekday' => 6, 'start_time' => '08:30:00', 'end_time' => '10:30:00',
    ]);
    $row = ScheduleImportRow::query()->create([
        'import_batch_id' => $batch->id, 'academic_term_id' => $term->id, 'source_sheet_name' => 'Schedule',
        'source_row_number' => 2, 'row_fingerprint' => hash('sha256', uniqid('identity-row-', true)),
        'source_payload' => [], 'normalized_payload' => ['subject_code_key' => strtolower($subject->code), 'section_type' => 'T', 'section_code' => 'T1'],
        'original_import_status' => ScheduleImportRow::ORIGINAL_WARNING_ONLY,
        'current_reconciliation_status' => ScheduleImportRow::STATUS_UNRESOLVED,
        'import_result' => ['slot_ids' => [$slot->id]],
    ]);
    ScheduleImportIssue::query()->create([
        'schedule_import_row_id' => $row->id, 'deduplication_key' => hash('sha256', $issueType.'-'.$row->id),
        'issue_type' => $issueType, 'severity' => ScheduleImportIssue::SEVERITY_WARNING,
        'reason_ar' => $issueType, 'resolution_status' => ScheduleImportIssue::STATUS_UNRESOLVED,
    ]);

    return [$path, $row, $slot];
}

it('fills null lecturer and hall values and audits slot snapshots', function (): void {
    [$lecturerPath, $lecturerRow, $lecturerSlot] = identityAssignmentRow(ScheduleImportIssue::TYPE_LECTURER_MISSING);

    try {
        $lecturer = Lecturer::query()->create(['name' => 'مدرس حقيقي', 'canonical_name' => 'مدرس حقيقي', 'is_active' => true]);
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        $result = app(ScheduleImportReconciliationService::class)->assignLecturer($lecturerRow, $lecturer->id, $super);
        $initialAction = ScheduleImportIssueAction::query()->latest('id')->firstOrFail();
        $repeated = app(ScheduleImportReconciliationService::class)->assignLecturer($lecturerRow->fresh(), $lecturer->id, $super);
        $repeatedAction = ScheduleImportIssueAction::query()->latest('id')->firstOrFail();

        expect($result['status'])->toBe('completed')
            ->and($repeated['status'])->toBe('already_applied')
            ->and($lecturerSlot->fresh()->lecturer_id)->toBe($lecturer->id)
            ->and($lecturerRow->fresh()->resolved_lecturer_id)->toBe($lecturer->id)
            ->and(ScheduleImportIssueAction::query()->where('action', ScheduleImportIssueAction::ACTION_ASSIGN_LECTURER)->count())->toBe(2)
            ->and(data_get($initialAction->result, 'slot_changes.0.before.lecturer_id'))->toBeNull()
            ->and(data_get($initialAction->result, 'slot_changes.0.after.lecturer_id'))->toBe($lecturer->id)
            ->and(data_get($repeatedAction->result, 'status'))->toBe('already_applied');
    } finally {
        @unlink($lecturerPath);
    }

    [$hallPath, $hallRow, $hallSlot] = identityAssignmentRow(ScheduleImportIssue::TYPE_HALL_MISSING);

    try {
        $hall = Hall::query()->create(['code' => 'H-1', 'name' => 'قاعة 1', 'floor' => null, 'is_active' => true]);
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        $result = app(ScheduleImportReconciliationService::class)->assignHall($hallRow, $hall->id, $super);
        $repeated = app(ScheduleImportReconciliationService::class)->assignHall($hallRow->fresh(), $hall->id, $super);

        expect($result['status'])->toBe('completed')
            ->and($repeated['status'])->toBe('already_applied')
            ->and($hallSlot->fresh()->hall_id)->toBe($hall->id)
            ->and($hallRow->fresh()->resolved_hall_id)->toBe($hall->id)
            ->and(__('hall.not_specified'))->toBe('غير محدد');
    } finally {
        @unlink($hallPath);
    }
});

it('never overwrites different lecturer or hall values', function (): void {
    $existingLecturer = Lecturer::query()->create(['name' => 'الأول', 'canonical_name' => 'الأول', 'is_active' => true]);
    [$path, $row, $slot] = identityAssignmentRow(ScheduleImportIssue::TYPE_LECTURER_MISSING, $existingLecturer->id);

    try {
        $selected = Lecturer::query()->create(['name' => 'الثاني', 'canonical_name' => 'الثاني', 'is_active' => true]);
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        $result = app(ScheduleImportReconciliationService::class)->assignLecturer($row, $selected->id, $super);

        expect($result['status'])->toBe('conflict')
            ->and($slot->fresh()->lecturer_id)->toBe($existingLecturer->id)
            ->and($row->issues()->sole()->resolution_status)->toBe(ScheduleImportIssue::STATUS_RETRY_FAILED);
    } finally {
        @unlink($path);
    }

    $existingHall = Hall::query()->create(['code' => 'OLD', 'name' => 'القديمة', 'floor' => 1, 'is_active' => true]);
    [$path, $row, $slot] = identityAssignmentRow(ScheduleImportIssue::TYPE_HALL_MISSING, null, $existingHall->id);

    try {
        $selected = Hall::query()->create(['code' => 'NEW', 'name' => 'الجديدة', 'floor' => null, 'is_active' => true]);
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        $result = app(ScheduleImportReconciliationService::class)->assignHall($row, $selected->id, $super);

        expect($result['status'])->toBe('conflict')
            ->and($slot->fresh()->hall_id)->toBe($existingHall->id);
    } finally {
        @unlink($path);
    }
});

it('creates non-login identities only explicitly and rejects sentinel names', function (): void {
    [$path, $row] = identityAssignmentRow(ScheduleImportIssue::TYPE_LECTURER_MISSING);

    try {
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        $users = User::query()->count();
        $result = app(ScheduleImportReconciliationService::class)->createLecturerIdentity($row, 'مدرس جديد', $super);
        $lecturer = Lecturer::findOrFail($result['created_lecturer_id']);

        expect($lecturer->user_id)->toBeNull()
            ->and($lecturer->email)->toBeNull()
            ->and(User::query()->count())->toBe($users);
    } finally {
        @unlink($path);
    }

    foreach (['0', '0.0', '-', 'NaN', ' '] as $invalid) {
        [$path, $row] = identityAssignmentRow(ScheduleImportIssue::TYPE_LECTURER_MISSING);
        try {
            $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
            expect(fn () => app(ScheduleImportReconciliationService::class)->createLecturerIdentity($row, $invalid, $super))->toThrow(RuntimeException::class);
        } finally {
            @unlink($path);
        }
    }
});

it('creates a hall explicitly with a null floor and never creates login data', function (): void {
    [$path, $row, $slot] = identityAssignmentRow(ScheduleImportIssue::TYPE_HALL_MISSING);

    try {
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        $users = User::query()->count();
        $result = app(ScheduleImportReconciliationService::class)->createHall($row, 'NEW-HALL', 'قاعة جديدة', $super);
        $hall = Hall::findOrFail($result['created_hall_id']);

        expect($hall->floor)->toBeNull()
            ->and($slot->fresh()->hall_id)->toBe($hall->id)
            ->and(User::query()->count())->toBe($users);

        foreach (['0', '0.0', '-', 'NaN', ' '] as $invalid) {
            [$invalidPath, $invalidRow] = identityAssignmentRow(ScheduleImportIssue::TYPE_HALL_MISSING);
            try {
                expect(fn () => app(ScheduleImportReconciliationService::class)->createHall($invalidRow, $invalid, $invalid, $super))->toThrow(RuntimeException::class);
            } finally {
                @unlink($invalidPath);
            }
        }
    } finally {
        @unlink($path);
    }
});
