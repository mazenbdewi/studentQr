<?php

use App\Exports\ScheduleImportReconciliationExport;
use App\Filament\Pages\ScheduleImportReconciliationReport;
use App\Models\Enrollment;
use App\Models\LectureSession;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportIssueAction;
use App\Models\ScheduleImportRow;
use App\Models\ScheduleImportRowTimeOverride;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Policies\ScheduleImportIssuePolicy;
use App\Services\ScheduleImportReconciliationService;
use App\Services\ScheduleImportReconciliationSummaryService;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

require_once __DIR__.'/../Support/ScheduleImportReconciliationFixtures.php';

function batchExclusionFixture(bool $withWarnings = true): array
{
    $path = reconciliationWorkbook([]);
    $suffix = strtoupper(substr(hash('sha256', uniqid('', true)), 0, 8));
    [$term, , $batch, $subject, $section] = reconciliationSource($path, suffix: $suffix);
    $row = ScheduleImportRow::query()->create([
        'import_batch_id' => $batch->id,
        'academic_term_id' => $term->id,
        'source_sheet_name' => 'Schedule',
        'source_row_number' => 912,
        'row_fingerprint' => hash('sha256', 'batch-exclusion-'.$suffix),
        'source_payload' => [
            'subject_code' => $subject->code,
            'subject_name' => 'مشروع فصلي',
            'section_type' => 'T',
            'section_number' => '1',
            'expected_student_count' => 1,
            'teacher_name' => '',
            'hall_name' => '',
            'weekday_values' => [],
        ],
        'normalized_payload' => [
            'subject_code_key' => strtolower($subject->code),
            'section_code' => 'T1',
            'section_type' => 'T',
            'expected_student_count' => 1,
        ],
        'original_import_status' => ScheduleImportRow::ORIGINAL_UNSCHEDULED,
        'current_reconciliation_status' => ScheduleImportRow::STATUS_UNRESOLVED,
        'resolved_subject_id' => $subject->id,
        'resolved_subject_section_id' => $section->id,
    ]);
    $noTime = ScheduleImportIssue::query()->create([
        'schedule_import_row_id' => $row->id,
        'deduplication_key' => hash('sha256', 'batch-exclusion-time-'.$suffix),
        'issue_type' => ScheduleImportIssue::TYPE_NO_WEEKLY_TIME,
        'severity' => ScheduleImportIssue::SEVERITY_WARNING,
        'reason_ar' => 'لا يوجد موعد أسبوعي',
        'resolution_status' => ScheduleImportIssue::STATUS_UNRESOLVED,
    ]);
    $warnings = collect();

    if ($withWarnings) {
        foreach ([ScheduleImportIssue::TYPE_LECTURER_MISSING, ScheduleImportIssue::TYPE_HALL_MISSING] as $type) {
            $warnings->push(ScheduleImportIssue::query()->create([
                'schedule_import_row_id' => $row->id,
                'deduplication_key' => hash('sha256', $type.'-'.$suffix),
                'issue_type' => $type,
                'severity' => ScheduleImportIssue::SEVERITY_WARNING,
                'reason_ar' => $type,
                'resolution_status' => ScheduleImportIssue::STATUS_UNRESOLVED,
            ]));
        }
    }

    $student = Student::query()->create([
        'name' => 'طالب اختبار الاستبعاد',
        'student_number' => 'EX-'.$suffix,
        'status' => 'active',
        'is_active' => true,
    ]);
    Enrollment::query()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'academic_term_id' => $term->id,
        'theoretical_section_id' => $section->id,
        'status' => Enrollment::STATUS_ENROLLED,
    ]);

    return [$path, $term, $batch, $subject, $section, $row, $noTime, $warnings];
}

it('shows the two batch schedule choices and a constrained mandatory exclusion reason', function (): void {
    [$path, , $batch, , , $row, $noTime] = batchExclusionFixture();

    try {
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        expect(fn () => app(ScheduleImportReconciliationService::class)->excludeFromBatchSchedule($noTime, $super, 'قصير'))
            ->toThrow(RuntimeException::class, __('schedule-import-reconciliation.validation.exclusion_note_length'))
            ->and(ScheduleImportIssueAction::query()->count())->toBe(0)
            ->and($row->fresh()->excluded_from_weekly_schedule_at)->toBeNull();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $component = Livewire::actingAs($super)->test(ScheduleImportReconciliationReport::class, ['batch' => $batch->uuid]);
        $legacyLabel = implode(' ', ['اعتماد', 'بلا', 'موعد', 'أسبوعي']);
        $component->call('selectTab', 'warnings')
            ->assertSee('إسناد موعد أسبوعي')
            ->assertSee('استبعاد من برنامج الدوام لهذه الدفعة')
            ->assertDontSee($legacyLabel);

        $instance = $component->instance();
        $instance->mountTableAction('exclude-from-batch-schedule', (string) $row->id);
        $action = $instance->getMountedTableAction();
        $field = collect($instance->getMountedTableActionForm($action)?->getFlatComponents(withHidden: true) ?? [])
            ->first(fn (object $component): bool => method_exists($component, 'getName') && $component->getName() === 'exclusion_note');

        expect($action)->not->toBeNull()
            ->and((string) $action->getModalHeading())->toBe('استبعاد الصف من برنامج الدوام لهذه الدفعة')
            ->and((string) $action->getModalDescription())->toContain('لن يتم إنشاء موعد أسبوعي')->toContain('لن يتم حذف أي بيانات أكاديمية')
            ->and($field)->not->toBeNull()
            ->and($field->getLabel())->toBe('سبب الاستبعاد')
            ->and($field->isRequired())->toBeTrue()
            ->and($field->getMinLength())->toBe(5)
            ->and($field->getMaxLength())->toBe(500)
            ->and(__('schedule-import-reconciliation.exclusion.examples'))->toContain('مشروع تخرج')->toContain('مشروع فصلي');
    } finally {
        @unlink($path);
    }
});

it('excludes the row, marks schedule-only identity issues not applicable, and preserves academic data', function (): void {
    [$path, , $batch, $subject, $section, $row, $noTime, $warnings] = batchExclusionFixture();

    try {
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        $before = [
            'subjects' => Subject::query()->count(),
            'sections' => SubjectSection::query()->count(),
            'enrollments' => Enrollment::query()->count(),
            'rows' => ScheduleImportRow::query()->count(),
        ];
        $result = app(ScheduleImportReconciliationService::class)->excludeFromBatchSchedule(
            $noTime,
            $super,
            'مشروع فصلي تتم متابعته خارج برنامج الدوام الأسبوعي.',
        );
        $fresh = $row->fresh();
        $actions = ScheduleImportIssueAction::query()->with('issue')->orderBy('id')->get();
        $noTimeAction = $actions->firstWhere('schedule_import_issue_id', $noTime->id);

        expect($result['status'])->toBe('applied')
            ->and($result['no_slot_created'])->toBeTrue()
            ->and($result['created_slot_ids'])->toBe([])
            ->and($result['batch']['uuid'])->toBe($batch->uuid)
            ->and($result['source_row_number'])->toBe(912)
            ->and($result['subject']['id'])->toBe($subject->id)
            ->and($result['section']['id'])->toBe($section->id)
            ->and($noTime->fresh()->resolution_status)->toBe(ScheduleImportIssue::STATUS_RESOLVED)
            ->and($warnings->every(fn (ScheduleImportIssue $warning): bool => $warning->fresh()->resolution_status === ScheduleImportIssue::STATUS_RESOLVED))->toBeTrue()
            ->and($warnings->every(fn (ScheduleImportIssue $warning): bool => $warning->fresh()->resolution_action === ScheduleImportIssue::RESOLUTION_ACTION_NOT_APPLICABLE_DUE_TO_BATCH_EXCLUSION))->toBeTrue()
            ->and($warnings->every(fn (ScheduleImportIssue $warning): bool => $warning->fresh()->resolution_note === __('schedule-import-reconciliation.exclusion.not_applicable_reason')))->toBeTrue()
            ->and(ScheduleImportIssue::query()->where('schedule_import_row_id', $row->id)->count())->toBe(3)
            ->and($fresh->current_reconciliation_status)->toBe(ScheduleImportRow::STATUS_EXCLUDED_FROM_BATCH_SCHEDULE)
            ->and($fresh->excluded_from_weekly_schedule_at)->not->toBeNull()
            ->and($fresh->excluded_from_weekly_schedule_by)->toBe($super->id)
            ->and($fresh->exclusion_note)->toBe('مشروع فصلي تتم متابعته خارج برنامج الدوام الأسبوعي.')
            ->and(SubjectSectionScheduleSlot::query()->count())->toBe(0)
            ->and(ScheduleImportRowTimeOverride::query()->count())->toBe(0)
            ->and(LectureSession::query()->count())->toBe(0)
            ->and(Subject::query()->count())->toBe($before['subjects'])
            ->and(SubjectSection::query()->count())->toBe($before['sections'])
            ->and(Enrollment::query()->count())->toBe($before['enrollments'])
            ->and(ScheduleImportRow::query()->count())->toBe($before['rows'])
            ->and($actions)->toHaveCount(3)
            ->and($actions->every(fn (ScheduleImportIssueAction $action): bool => $action->action === ScheduleImportIssueAction::ACTION_EXCLUDE_FROM_BATCH_SCHEDULE))->toBeTrue()
            ->and($actions->every(fn (ScheduleImportIssueAction $action): bool => $action->actor_user_id === $super->id))->toBeTrue()
            ->and($actions->every(fn (ScheduleImportIssueAction $action): bool => $action->previous_status === ScheduleImportIssue::STATUS_UNRESOLVED))->toBeTrue()
            ->and($actions->every(fn (ScheduleImportIssueAction $action): bool => $action->new_status === ScheduleImportIssue::STATUS_RESOLVED))->toBeTrue()
            ->and($noTimeAction->note)->toBe($fresh->exclusion_note)
            ->and($noTimeAction->result['batch']['uuid'])->toBe($batch->uuid)
            ->and($noTimeAction->result['source_row_number'])->toBe(912)
            ->and($noTimeAction->result['not_applicable_issue_outcomes'])->toHaveCount(2)
            ->and($noTimeAction->result['not_applicable_issue_outcomes'][0]['reason'])->toBe(__('schedule-import-reconciliation.exclusion.not_applicable_reason'))
            ->and($noTimeAction->new_state['import_batch_id'])->toBe($batch->id)
            ->and($noTimeAction->new_state['source_row_number'])->toBe(912)
            ->and($noTimeAction->new_state['resolution']['subject']['id'])->toBe($subject->id)
            ->and($noTimeAction->new_state['resolution']['section']['id'])->toBe($section->id)
            ->and($noTimeAction->new_state['batch_schedule_exclusion']['note'])->toBe($fresh->exclusion_note)
            ->and($noTimeAction->new_state['batch_schedule_exclusion']['no_slot_created'])->toBeTrue()
            ->and($noTimeAction->performed_at)->not->toBeNull();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $component = Livewire::actingAs($super)->test(ScheduleImportReconciliationReport::class, ['batch' => $batch->uuid]);
        expect($component->instance()->tabCounts()['warnings'])->toBe(0)
            ->and($component->instance()->tabCounts()['excluded'])->toBe(1)
            ->and($component->instance()->tabCounts()['successful'])->toBe(0)
            ->and(app(ScheduleImportReconciliationSummaryService::class)->forBatch($batch->id)['excluded_from_batch_schedule_rows'])->toBe($component->instance()->tabCounts()['excluded']);

        $details = view('filament.components.schedule-import-row-details', [
            'row' => $fresh->load(['resolvedSubject', 'resolvedSubjectSection', 'resolvedLecturer', 'resolvedHall', 'timeOverrides', 'excludedFromWeeklyScheduleBy']),
            'relatedSlots' => new EloquentCollection,
        ])->render();
        expect($details)->toContain('مستبعد من برنامج الدوام لهذه الدفعة')
            ->toContain($fresh->exclusion_note)
            ->toContain('لن يتم حذف أي بيانات أكاديمية');

        $history = view('filament.components.schedule-import-action-history', ['actions' => $actions])->render();
        expect($history)->toContain(ScheduleImportIssue::TYPE_LECTURER_MISSING)
            ->toContain(ScheduleImportIssue::TYPE_HALL_MISSING)
            ->toContain(__('schedule-import-reconciliation.exclusion.not_applicable_reason'))
            ->not->toContain(__('schedule-import-reconciliation.actions.assign_lecturer'))
            ->not->toContain(__('schedule-import-reconciliation.actions.assign_hall'));

        $exportRows = (new ScheduleImportReconciliationExport($batch))->collection();
        expect($exportRows)->not->toBeEmpty()
            ->and($exportRows->every(fn (array $exportRow): bool => $exportRow[12] === 'excluded_from_batch_schedule'))
            ->toBeTrue()
            ->and($exportRows->every(fn (array $exportRow): bool => $exportRow[13] === $fresh->exclusion_note))
            ->toBeTrue();
    } finally {
        @unlink($path);
    }
});

it('keeps an unrelated warning active after exclusion while missing lecturer and hall become not applicable', function (): void {
    [$path, , $batch, , , $row, $noTime, $warnings] = batchExclusionFixture();

    try {
        $unrelatedWarning = ScheduleImportIssue::query()->create([
            'schedule_import_row_id' => $row->id,
            'deduplication_key' => hash('sha256', 'unrelated-warning-'.$row->id),
            'issue_type' => ScheduleImportIssue::TYPE_LECTURER_AMBIGUOUS,
            'severity' => ScheduleImportIssue::SEVERITY_WARNING,
            'reason_ar' => 'تحذير مستقل',
            'resolution_status' => ScheduleImportIssue::STATUS_UNRESOLVED,
        ]);
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        app(ScheduleImportReconciliationService::class)->excludeFromBatchSchedule($noTime, $super, 'مشروع فصلي تتم متابعته خارج برنامج الدوام الأسبوعي.');

        expect($warnings->every(fn (ScheduleImportIssue $warning): bool => $warning->fresh()->resolution_status === ScheduleImportIssue::STATUS_RESOLVED))->toBeTrue()
            ->and($unrelatedWarning->fresh()->resolution_status)->toBe(ScheduleImportIssue::STATUS_UNRESOLVED)
            ->and($row->fresh()->current_reconciliation_status)->toBe(ScheduleImportRow::STATUS_UNRESOLVED)
            ->and(app(ScheduleImportReconciliationSummaryService::class)->forBatch($batch->id)['excluded_from_batch_schedule_rows'])->toBe(0);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $component = Livewire::actingAs($super)->test(ScheduleImportReconciliationReport::class, ['batch' => $batch->uuid]);
        expect($component->instance()->tabCounts()['warnings'])->toBe(1)
            ->and($component->instance()->tabCounts()['excluded'])->toBe(0);
    } finally {
        @unlink($path);
    }
});

it('does not allow exclusion while a subject or section issue remains unresolved', function (): void {
    [$path, , $batch, , , $row, $noTime] = batchExclusionFixture(withWarnings: false);

    try {
        ScheduleImportIssue::query()->create([
            'schedule_import_row_id' => $row->id,
            'deduplication_key' => hash('sha256', 'blocking-subject-'.$row->id),
            'issue_type' => ScheduleImportIssue::TYPE_SUBJECT_NOT_FOUND,
            'severity' => ScheduleImportIssue::SEVERITY_ERROR,
            'reason_ar' => 'مشكلة مادة مانعة',
            'resolution_status' => ScheduleImportIssue::STATUS_UNRESOLVED,
        ]);
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);

        expect(fn () => app(ScheduleImportReconciliationService::class)->excludeFromBatchSchedule(
            $noTime,
            $super,
            'لا يجب الاستبعاد قبل معالجة مشكلة المادة.',
        ))->toThrow(RuntimeException::class, __('schedule-import-reconciliation.validation.resolve_catalog_issues_first'))
            ->and($noTime->fresh()->resolution_status)->toBe(ScheduleImportIssue::STATUS_UNRESOLVED)
            ->and($row->fresh()->excluded_from_weekly_schedule_at)->toBeNull()
            ->and(ScheduleImportIssueAction::query()->count())->toBe(0);

    } finally {
        @unlink($path);
    }
});

it('places a fully resolved exclusion in its own tab and applies it idempotently', function (): void {
    [$path, , $batch, , , $row, $noTime] = batchExclusionFixture(withWarnings: false);

    try {
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        $service = app(ScheduleImportReconciliationService::class);
        $first = $service->excludeFromBatchSchedule($noTime, $super, 'نشاط غير مجدول أسبوعيًا حسب قرار القسم.');
        $second = $service->excludeFromBatchSchedule($noTime->fresh(), $super, 'سبب مختلف لا يجوز استبداله ضمن الإجراء نفسه.');
        $fresh = $row->fresh();

        expect($first['status'])->toBe('applied')
            ->and($second['status'])->toBe('already_applied')
            ->and(ScheduleImportIssueAction::query()->count())->toBe(1)
            ->and($fresh->exclusion_note)->toBe('نشاط غير مجدول أسبوعيًا حسب قرار القسم.')
            ->and($fresh->current_reconciliation_status)->toBe(ScheduleImportRow::STATUS_EXCLUDED_FROM_BATCH_SCHEDULE)
            ->and(app(ScheduleImportReconciliationSummaryService::class)->forBatch($batch->id)['excluded_from_batch_schedule_rows'])->toBe(1);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $component = Livewire::actingAs($super)->test(ScheduleImportReconciliationReport::class, ['batch' => $batch->uuid]);
        expect($component->instance()->tabCounts()['excluded'])->toBe(1)
            ->and($component->instance()->tabCounts()['successful'])->toBe(0);
    } finally {
        @unlink($path);
    }
});

it('prevents exclusion and weekly-time assignment from coexisting without an explicit review workflow', function (): void {
    [$firstPath, , , , , $rowWithOverride, $issueWithOverride] = batchExclusionFixture(withWarnings: false);
    [$secondPath, , , , , $excludedRow, $exclusionIssue] = batchExclusionFixture(withWarnings: false);

    try {
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        ScheduleImportRowTimeOverride::query()->create([
            'schedule_import_row_id' => $rowWithOverride->id,
            'weekday' => 1,
            'start_time' => '08:30:00',
            'end_time' => '10:30:00',
            'created_by' => $super->id,
        ]);

        expect(fn () => app(ScheduleImportReconciliationService::class)->excludeFromBatchSchedule(
            $issueWithOverride,
            $super,
            'لا ينبغي قبول الاستبعاد مع وجود موعد يدوي.',
        ))->toThrow(RuntimeException::class, __('schedule-import-reconciliation.validation.schedule_decision_review_required'))
            ->and($issueWithOverride->fresh()->resolution_status)->toBe(ScheduleImportIssue::STATUS_UNRESOLVED)
            ->and(ScheduleImportIssueAction::query()->count())->toBe(0);

        $service = app(ScheduleImportReconciliationService::class);
        $service->excludeFromBatchSchedule($exclusionIssue, $super, 'نشاط غير مجدول أسبوعيًا حسب قرار القسم.');
        expect(fn () => $service->addWeeklyTimes($excludedRow->fresh(), [
            ['weekday' => 1, 'start_time' => '08:30', 'end_time' => '10:30'],
        ], $super))->toThrow(RuntimeException::class, __('schedule-import-reconciliation.validation.exclusion_review_required'))
            ->and(ScheduleImportRowTimeOverride::query()->where('schedule_import_row_id', $excludedRow->id)->count())->toBe(0)
            ->and(SubjectSectionScheduleSlot::query()->count())->toBe(0);
    } finally {
        @unlink($firstPath);
        @unlink($secondPath);
    }
});

it('uses the existing ignore permission for exclusion without granting manager or lecturer access', function (): void {
    [$path, , , , , , $noTime] = batchExclusionFixture(withWarnings: false);

    try {
        $permission = Permission::findOrCreate(ScheduleImportIssuePolicy::IGNORE, 'web');
        $admin = User::factory()->create(['role' => 'attendance_monitor', 'type' => 'admin']);
        $manager = User::factory()->create(['role' => 'attendance_monitor', 'type' => 'admin']);
        $lecturer = User::factory()->create(['role' => 'course_lecturer', 'type' => 'lecturer']);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $manager->assignRole(Role::findOrCreate('manager', 'web'));
        $lecturer->assignRole(Role::findOrCreate('course_lecturer', 'web'));
        $admin->givePermissionTo($permission);

        expect(Gate::forUser($admin)->allows('excludeFromBatchSchedule', $noTime))->toBeTrue()
            ->and(Gate::forUser($manager)->allows('excludeFromBatchSchedule', $noTime))->toBeFalse()
            ->and(Gate::forUser($lecturer)->allows('excludeFromBatchSchedule', $noTime))->toBeFalse()
            ->and(fn () => app(ScheduleImportReconciliationService::class)->excludeFromBatchSchedule(
                $noTime,
                $manager,
                'نشاط غير مجدول أسبوعيًا حسب قرار القسم.',
            ))->toThrow(AuthorizationException::class);
    } finally {
        @unlink($path);
    }
});
