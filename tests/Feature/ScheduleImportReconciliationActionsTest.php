<?php

use App\Models\AcademicTerm;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportIssueAction;
use App\Models\ScheduleImportRow;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\User;
use App\Services\ScheduleImportReconciliationService;
use Spatie\Permission\Models\Permission;
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

it('enforces permission and term-safe mapping while appending audit history', function (): void {
    $path = reconciliationWorkbook([]);

    try {
        [$term, , $batch, $subject, $section] = reconciliationSource($path);
        $row = ScheduleImportRow::query()->create([
            'import_batch_id' => $batch->id, 'academic_term_id' => $term->id, 'source_sheet_name' => 'Schedule',
            'source_row_number' => 2, 'row_fingerprint' => hash('sha256', 'row'), 'source_payload' => [], 'normalized_payload' => [],
            'original_import_status' => ScheduleImportRow::ORIGINAL_REJECTED, 'current_reconciliation_status' => ScheduleImportRow::STATUS_UNRESOLVED,
        ]);
        $issue = ScheduleImportIssue::query()->create([
            'schedule_import_row_id' => $row->id, 'deduplication_key' => hash('sha256', 'issue'),
            'issue_type' => ScheduleImportIssue::TYPE_SECTION_NOT_FOUND, 'severity' => ScheduleImportIssue::SEVERITY_ERROR,
            'reason_ar' => 'الشعبة مفقودة', 'resolution_status' => ScheduleImportIssue::STATUS_UNRESOLVED,
        ]);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $permission = Permission::firstOrCreate(['name' => 'resolve schedule-import section mapping', 'guard_name' => 'web']);
        $adminRole->givePermissionTo($permission);
        $admin = User::factory()->create(['role' => 'attendance_monitor', 'type' => 'admin']);
        $admin->assignRole($adminRole);
        $unauthorized = User::factory()->create(['role' => 'attendance_monitor', 'type' => 'admin']);

        expect(fn () => app(ScheduleImportReconciliationService::class)->link($issue, $subject->id, $section->id, $unauthorized))->toThrow(Illuminate\Auth\Access\AuthorizationException::class);

        $otherTerm = AcademicTerm::query()->create(['display_name' => 'فصل آخر', 'canonical_name' => 'فصل آخر']);
        $otherSection = SubjectSection::query()->create(['academic_term_id' => $otherTerm->id, 'subject_id' => $subject->id, 'section_type' => Subject::TYPE_THEORETICAL, 'code' => 'T2', 'raw_section_number' => '2']);
        expect(fn () => app(ScheduleImportReconciliationService::class)->link($issue, $subject->id, $otherSection->id, $admin))->toThrow(RuntimeException::class);

        app(ScheduleImportReconciliationService::class)->link($issue, $subject->id, $section->id, $admin, 'ربط مؤكد');
        $action = ScheduleImportIssueAction::query()->sole();
        expect($issue->fresh()->resolution_status)->toBe(ScheduleImportIssue::STATUS_RESOLVED)
            ->and($row->fresh()->resolved_subject_id)->toBe($subject->id)
            ->and($row->fresh()->resolved_subject_section_id)->toBe($section->id)
            ->and($issue->fresh()->resolved_subject_id)->toBeNull()
            ->and($issue->fresh()->resolved_subject_section_id)->toBeNull()
            ->and($row->fresh()->current_reconciliation_status)->toBe(ScheduleImportRow::STATUS_UNRESOLVED)
            ->and($action->actor_user_id)->toBe($admin->id)
            ->and($action->new_state['actor']['name'])->toBe($admin->name)
            ->and($action->new_state['resolution']['subject']['code'])->toBe('SCH101')
            ->and($action->note)->toBe('ربط مؤكد');
    } finally {
        @unlink($path);
    }
});

it('keeps rows excluded from the batch schedule separate and creates no slot', function (): void {
    $path = reconciliationWorkbook([]);

    try {
        [$term, , $batch, $subject, $section] = reconciliationSource($path);
        $row = ScheduleImportRow::query()->create([
            'import_batch_id' => $batch->id, 'academic_term_id' => $term->id, 'source_sheet_name' => 'Schedule',
            'source_row_number' => 2, 'row_fingerprint' => hash('sha256', 'row2'), 'source_payload' => [], 'normalized_payload' => ['subject_code_key' => strtolower($subject->code), 'section_code' => 'T1', 'section_type' => 'T'],
            'original_import_status' => ScheduleImportRow::ORIGINAL_UNSCHEDULED, 'current_reconciliation_status' => ScheduleImportRow::STATUS_UNRESOLVED,
            'resolved_subject_id' => $subject->id, 'resolved_subject_section_id' => $section->id,
        ]);
        $issue = ScheduleImportIssue::query()->create([
            'schedule_import_row_id' => $row->id, 'deduplication_key' => hash('sha256', 'no-time'),
            'issue_type' => ScheduleImportIssue::TYPE_NO_WEEKLY_TIME, 'severity' => ScheduleImportIssue::SEVERITY_WARNING,
            'reason_ar' => 'لا موعد', 'resolution_status' => ScheduleImportIssue::STATUS_UNRESOLVED,
        ]);
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        app(ScheduleImportReconciliationService::class)->excludeFromBatchSchedule($issue, $super, 'مشروع تخرج بلا موعد أسبوعي ثابت');

        expect($row->fresh()->current_reconciliation_status)->toBe(ScheduleImportRow::STATUS_EXCLUDED_FROM_BATCH_SCHEDULE)
            ->and(App\Models\SubjectSectionScheduleSlot::query()->count())->toBe(0)
            ->and(ScheduleImportIssueAction::query()->sole()->action)->toBe(ScheduleImportIssueAction::ACTION_EXCLUDE_FROM_BATCH_SCHEDULE);
    } finally {
        @unlink($path);
    }
});
