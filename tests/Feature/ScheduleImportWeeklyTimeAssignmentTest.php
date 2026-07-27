<?php

use App\Filament\Pages\ScheduleImportReconciliationReport;
use App\Models\Hall;
use App\Models\Lecturer;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportRow;
use App\Models\ScheduleImportRowTimeOverride;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Services\ScheduleImportIssueWorkflow;
use App\Services\ScheduleImportReconciliationService;
use App\Services\ScheduleImportRowResolutionContext;
use Filament\Facades\Filament;
use Livewire\Livewire;
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

function weeklyTimeRow(bool $withCanonicalResolution = true): array
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
    ]);
    if ($withCanonicalResolution) {
        $row->update(['resolved_subject_id' => $subject->id, 'resolved_subject_section_id' => $section->id]);
    }
    ScheduleImportIssue::query()->create([
        'schedule_import_row_id' => $row->id, 'deduplication_key' => hash('sha256', 'no-time-'.$row->id),
        'issue_type' => ScheduleImportIssue::TYPE_NO_WEEKLY_TIME, 'severity' => ScheduleImportIssue::SEVERITY_WARNING,
        'reason_ar' => 'لا موعد', 'resolution_status' => ScheduleImportIssue::STATUS_UNRESOLVED,
    ]);

    return [$path, $term, $batch, $subject, $section, $row];
}

function addWeeklyTimeIssue(ScheduleImportRow $row, string $type, string $severity = ScheduleImportIssue::SEVERITY_WARNING): ScheduleImportIssue
{
    return ScheduleImportIssue::query()->create([
        'schedule_import_row_id' => $row->id,
        'deduplication_key' => hash('sha256', $type.'-'.$row->id),
        'issue_type' => $type,
        'severity' => $severity,
        'reason_ar' => $type,
        'resolution_status' => ScheduleImportIssue::STATUS_UNRESOLVED,
    ]);
}

it('uses the original exact import resolution when a no-time row has no manual canonical mapping', function (): void {
    [$path, , , $subject, $section, $row] = weeklyTimeRow(withCanonicalResolution: false);

    try {
        $context = app(ScheduleImportRowResolutionContext::class);

        expect($context->originalMatchedSubjectId($row))->toBe($subject->id)
            ->and($context->originalMatchedSubjectSectionId($row))->toBe($section->id)
            ->and($context->effectiveSubjectId($row))->toBe($subject->id)
            ->and($context->effectiveSubjectSectionId($row))->toBe($section->id)
            ->and(app(ScheduleImportIssueWorkflow::class)->dependencyMessage($row, 'time'))->toBeNull();

        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        $result = app(ScheduleImportReconciliationService::class)->addWeeklyTimes($row, [
            ['weekday' => 6, 'start_time' => '08:30', 'end_time' => '10:30'],
        ], $super);

        expect($result['created_slot_ids'])->toHaveCount(1)
            ->and($row->fresh()->resolved_subject_id)->toBe($subject->id)
            ->and($row->fresh()->resolved_subject_section_id)->toBe($section->id)
            ->and(ScheduleImportRowTimeOverride::query()->count())->toBe(1);
    } finally {
        @unlink($path);
    }
});

it('gives explicit manual canonical mapping precedence over the original exact match', function (): void {
    [$path, $term, , $originalSubject, $originalSection, $row] = weeklyTimeRow(withCanonicalResolution: false);

    try {
        $manualSubject = Subject::query()->create([
            'code' => 'MANUAL-OVERRIDE',
            'name' => 'مادة بديلة معتمدة',
            'subject_type' => Subject::TYPE_THEORETICAL,
            'is_active' => true,
        ]);
        $manualSection = SubjectSection::query()->create([
            'academic_term_id' => $term->id,
            'subject_id' => $manualSubject->id,
            'section_type' => Subject::TYPE_THEORETICAL,
            'code' => 'T1',
            'raw_section_number' => '1',
        ]);
        $row->update([
            'resolved_subject_id' => $manualSubject->id,
            'resolved_subject_section_id' => $manualSection->id,
        ]);
        $context = app(ScheduleImportRowResolutionContext::class);

        expect($context->originalMatchedSubjectId($row))->toBe($originalSubject->id)
            ->and($context->originalMatchedSubjectSectionId($row))->toBe($originalSection->id)
            ->and($context->effectiveSubjectId($row))->toBe($manualSubject->id)
            ->and($context->effectiveSubjectSectionId($row))->toBe($manualSection->id);
    } finally {
        @unlink($path);
    }
});

it('does not make optional lecturer or hall warnings block weekly-time assignment', function (array $issueTypes): void {
    [$path, , , , , $row] = weeklyTimeRow(withCanonicalResolution: false);

    try {
        foreach ($issueTypes as $issueType) {
            addWeeklyTimeIssue($row, $issueType);
        }

        expect(app(ScheduleImportIssueWorkflow::class)->dependencyMessage($row->fresh(), 'time'))->toBeNull();
    } finally {
        @unlink($path);
    }
})->with([
    'missing lecturer' => [[ScheduleImportIssue::TYPE_LECTURER_MISSING]],
    'missing hall' => [[ScheduleImportIssue::TYPE_HALL_MISSING]],
    'missing lecturer and hall' => [[ScheduleImportIssue::TYPE_LECTURER_MISSING, ScheduleImportIssue::TYPE_HALL_MISSING]],
]);

it('still blocks weekly-time assignment when the subject or section is unresolved', function (string $missing): void {
    [$path, , , $subject, , $row] = weeklyTimeRow(withCanonicalResolution: false);

    try {
        $normalized = $row->normalized_payload;

        if ($missing === 'subject') {
            $normalized['subject_code_key'] = 'missing-subject';
            addWeeklyTimeIssue($row, ScheduleImportIssue::TYPE_SUBJECT_NOT_FOUND, ScheduleImportIssue::SEVERITY_ERROR);
            $expected = __('schedule-import-reconciliation.dependencies.subject_first');
        } else {
            $normalized['section_code'] = 'T999';
            $row->resolved_subject_id = $subject->id;
            addWeeklyTimeIssue($row, ScheduleImportIssue::TYPE_SECTION_NOT_FOUND, ScheduleImportIssue::SEVERITY_ERROR);
            $expected = __('schedule-import-reconciliation.dependencies.section_first');
        }

        $row->normalized_payload = $normalized;
        $row->save();

        expect(app(ScheduleImportIssueWorkflow::class)->dependencyMessage($row->fresh(), 'time'))->toBe($expected);
    } finally {
        @unlink($path);
    }
})->with(['subject', 'section']);

it('creates a slot without optional identities and keeps their warnings open', function (): void {
    [$path, , $batch, , , $row] = weeklyTimeRow(withCanonicalResolution: false);

    try {
        $lecturerIssue = addWeeklyTimeIssue($row, ScheduleImportIssue::TYPE_LECTURER_MISSING);
        $hallIssue = addWeeklyTimeIssue($row, ScheduleImportIssue::TYPE_HALL_MISSING);
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        $service = app(ScheduleImportReconciliationService::class);
        $result = $service->addWeeklyTimes($row, [
            ['weekday' => 6, 'start_time' => '08:30', 'end_time' => '10:30'],
        ], $super);
        $slot = SubjectSectionScheduleSlot::query()->findOrFail($result['created_slot_ids'][0]);

        expect($slot->lecturer_id)->toBeNull()
            ->and($slot->hall_id)->toBeNull()
            ->and($row->issues()->where('issue_type', ScheduleImportIssue::TYPE_NO_WEEKLY_TIME)->sole()->resolution_status)->toBe(ScheduleImportIssue::STATUS_RESOLVED)
            ->and($lecturerIssue->fresh()->resolution_status)->toBe(ScheduleImportIssue::STATUS_UNRESOLVED)
            ->and($hallIssue->fresh()->resolution_status)->toBe(ScheduleImportIssue::STATUS_UNRESOLVED)
            ->and($row->fresh()->current_reconciliation_status)->toBe(ScheduleImportRow::STATUS_UNRESOLVED)
            ->and(app(ScheduleImportIssueWorkflow::class)->dependencyMessage($row->fresh(), 'retry'))->toBeNull();

        $slotCount = SubjectSectionScheduleSlot::query()->count();
        expect($service->retryRow($row->fresh(), $super)['status'])->toBe('already_applied')
            ->and($service->retryRow($row->fresh(), $super)['status'])->toBe('already_applied')
            ->and(SubjectSectionScheduleSlot::query()->count())->toBe($slotCount)
            ->and($lecturerIssue->fresh()->resolution_status)->toBe(ScheduleImportIssue::STATUS_UNRESOLVED)
            ->and($hallIssue->fresh()->resolution_status)->toBe(ScheduleImportIssue::STATUS_UNRESOLVED);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $component = Livewire::actingAs($super)->test(ScheduleImportReconciliationReport::class, ['batch' => $batch->uuid]);
        expect($component->instance()->tabCounts()['warnings'])->toBe(1)
            ->and($component->instance()->tabCounts()['successful'])->toBe(0);
    } finally {
        @unlink($path);
    }
});

it('keeps weekly time lecturer hall and batch exclusion actions parallel after catalog resolution', function (): void {
    [$path, , $batch, , , $row] = weeklyTimeRow(withCanonicalResolution: false);

    try {
        addWeeklyTimeIssue($row, ScheduleImportIssue::TYPE_LECTURER_MISSING);
        addWeeklyTimeIssue($row, ScheduleImportIssue::TYPE_HALL_MISSING);
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $component = Livewire::actingAs($super)->test(ScheduleImportReconciliationReport::class, ['batch' => $batch->uuid]);
        $component->call('selectTab', 'warnings');

        foreach (['assign-weekly-time', 'assign-lecturer', 'assign-hall', 'exclude-from-batch-schedule'] as $actionName) {
            $instance = $component->instance();
            $instance->mountTableAction($actionName, (string) $row->id);
            $action = $instance->getMountedTableAction();

            expect($action)->not->toBeNull()
                ->and($action->isDisabled())->toBeFalse();

            if ($actionName === 'assign-weekly-time') {
                $components = collect($instance->getMountedTableActionForm($action)?->getFlatComponents(withHidden: true) ?? [])
                    ->filter(fn (object $field): bool => method_exists($field, 'getName'));
                $names = $components->map(fn (object $field): string => $field->getName())->values();
                $notice = $components->first(fn (object $field): bool => $field->getName() === 'optional_identity_notice');
                $times = $components->first(fn (object $field): bool => $field->getName() === 'times');

                expect($names)->toContain(
                    'effective_subject',
                    'effective_section',
                    'effective_academic_term',
                    'weekday',
                    'start_time',
                    'end_time',
                    'lecturer_id',
                    'hall_id',
                    'section_capacity',
                    'expected_student_count',
                )->and($notice->isVisible())->toBeTrue()
                    ->and($times->isAddable())->toBeTrue()
                    ->and($times->getMinItems())->toBe(1);
            }

            $instance->unmountTableAction();
        }
    } finally {
        @unlink($path);
    }
});

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
