<?php

use App\Filament\Pages\ScheduleImportIssues;
use App\Models\AcademicTerm;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\Lecturer;
use App\Models\ScheduleImportRow;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Services\SchedulePlaceholderAssignmentService;
use App\Services\SchedulePlaceholderService;
use App\Services\WeeklyScheduleIssueService;
use App\Services\WeeklyScheduleSlotConflictDetector;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function taskTwoAdmin(): User
{
    Role::findOrCreate('super-admin', 'web');
    $admin = User::factory()->create([
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);
    $admin->assignRole('super-admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    return $admin;
}

function taskTwoTerm(string $suffix): AcademicTerm
{
    return AcademicTerm::query()->create([
        'display_name' => 'Task 2 '.$suffix,
        'canonical_name' => 'task-2-'.$suffix,
        'teaching_start_date' => '2026-09-07',
        'teaching_end_date' => '2026-09-13',
    ]);
}

function taskTwoBatch(AcademicTerm $term, string $suffix): ImportBatch
{
    $batch = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'task-2-'.$suffix),
        'import_type' => ImportBatch::TYPE_WEEKLY_SCHEDULE,
        'status' => ImportBatch::STATUS_COMPLETED,
        'total_rows' => 10,
        'imported_rows' => 10,
        'completed_at' => now(),
    ]);
    $batch->academicTerms()->attach($term->id, ['row_count' => 10]);

    return $batch;
}

function taskTwoCompletedEnrollmentBatch(AcademicTerm $term, string $suffix): void
{
    $batch = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'task-2-enrollment-'.$suffix),
        'import_type' => ImportBatch::TYPE_ENROLLMENTS,
        'status' => ImportBatch::STATUS_COMPLETED,
        'total_rows' => 1,
        'imported_rows' => 1,
        'completed_at' => now(),
    ]);
    $batch->academicTerms()->attach($term->id, ['row_count' => 1]);
}

function taskTwoSection(AcademicTerm $term, string $type): SubjectSection
{
    static $counter = 0;
    $counter++;
    $prefix = $type === Subject::TYPE_PRACTICAL ? 'P' : 'T';
    $subject = Subject::query()->create([
        'code' => 'TASK2-'.$counter,
        'name' => 'Task 2 subject '.$counter,
        'subject_type' => $type,
        'is_active' => true,
    ]);

    return SubjectSection::query()->create([
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'section_type' => $type,
        'code' => $prefix.$counter,
        'name' => $prefix.$counter,
    ]);
}

function taskTwoHall(string $suffix): Hall
{
    return Hall::query()->create([
        'code' => 'TASK2-HALL-'.$suffix,
        'name' => 'Task 2 hall '.$suffix,
        'is_active' => true,
    ]);
}

function taskTwoLecturer(string $suffix): Lecturer
{
    Role::findOrCreate('course_lecturer', 'web');
    $user = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
        'login_username' => 'task2-'.$suffix,
    ]);
    $user->assignRole('course_lecturer');

    return Lecturer::query()->create([
        'user_id' => $user->id,
        'name' => 'Task 2 lecturer '.$suffix,
        'canonical_name' => 'task 2 lecturer '.$suffix,
        'is_active' => true,
    ]);
}

function taskTwoSlot(ImportBatch $batch, SubjectSection $section, array $overrides = []): SubjectSectionScheduleSlot
{
    return SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $batch->id,
        'academic_term_id' => $section->academic_term_id,
        'subject_id' => $section->subject_id,
        'subject_section_id' => $section->id,
        'weekday' => 1,
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        ...$overrides,
    ]);
}

function taskTwoAssignmentFixture(): array
{
    $term = taskTwoTerm('main');
    taskTwoCompletedEnrollmentBatch($term, 'main');
    $batch = taskTwoBatch($term, 'main');
    $otherBatch = taskTwoBatch($term, 'other');
    $realLecturer = taskTwoLecturer('main');
    $realHall = taskTwoHall('main');

    $theoreticalMissingBoth = taskTwoSlot($batch, taskTwoSection($term, Subject::TYPE_THEORETICAL));
    $practicalMissingLecturer = taskTwoSlot($batch, taskTwoSection($term, Subject::TYPE_PRACTICAL), [
        'hall_id' => $realHall->id,
        'weekday' => 2,
    ]);
    $missingHall = taskTwoSlot($batch, taskTwoSection($term, Subject::TYPE_THEORETICAL), [
        'lecturer_id' => $realLecturer->id,
        'weekday' => 3,
    ]);
    $fullyAssigned = taskTwoSlot($batch, taskTwoSection($term, Subject::TYPE_THEORETICAL), [
        'lecturer_id' => $realLecturer->id,
        'hall_id' => $realHall->id,
        'weekday' => 4,
    ]);
    $invalidTime = taskTwoSlot($batch, taskTwoSection($term, Subject::TYPE_THEORETICAL), [
        'hall_id' => $realHall->id,
        'weekday' => 5,
        'start_time' => '12:00:00',
        'end_time' => '11:00:00',
    ]);
    $otherBatchSlot = taskTwoSlot($otherBatch, taskTwoSection($term, Subject::TYPE_THEORETICAL), [
        'weekday' => 6,
    ]);

    return compact(
        'term',
        'batch',
        'otherBatch',
        'realLecturer',
        'realHall',
        'theoreticalMissingBoth',
        'practicalMissingLecturer',
        'missingHall',
        'fullyAssigned',
        'invalidTime',
        'otherBatchSlot',
    );
}

it('fills only missing batch assignments and recalculates readiness idempotently', function (): void {
    $fixture = taskTwoAssignmentFixture();
    $actor = taskTwoAdmin();
    $issues = app(WeeklyScheduleIssueService::class);
    $before = $issues->result($fixture['term'], $fixture['batch']->id);

    expect($before->preview['source_slot_count'])->toBe(5)
        ->and($before->issueCountsByKey['missing_lecturer_identity'])->toBe(3)
        ->and($before->issueCountsByKey['missing_hall'])->toBe(2)
        ->and($before->uniqueAffectedSlots)->toBe(4)
        ->and($before->readySlots)->toBe(1);

    $service = app(SchedulePlaceholderAssignmentService::class);
    $preview = $service->preview($fixture['term'], $fixture['batch']);
    $result = $service->apply($fixture['term'], $fixture['batch'], $actor);
    $after = $issues->result($fixture['term'], $fixture['batch']->id);
    $placeholders = app(SchedulePlaceholderService::class);

    expect($preview)->toMatchArray([
        'theoretical_lecturer_count' => 2,
        'practical_lecturer_count' => 1,
        'hall_count' => 2,
        'affected_slot_count' => 4,
        'missing_both_count' => 1,
    ])->and($result)->toBe($preview)
        ->and($fixture['theoreticalMissingBoth']->fresh()->lecturer_id)->toBe($placeholders->theoreticalLecturer()->id)
        ->and($fixture['theoreticalMissingBoth']->fresh()->hall_id)->toBe($placeholders->unassignedHall()->id)
        ->and($fixture['practicalMissingLecturer']->fresh()->lecturer_id)->toBe($placeholders->practicalLecturer()->id)
        ->and($fixture['practicalMissingLecturer']->fresh()->hall_id)->toBe($fixture['realHall']->id)
        ->and($fixture['missingHall']->fresh()->lecturer_id)->toBe($fixture['realLecturer']->id)
        ->and($fixture['missingHall']->fresh()->hall_id)->toBe($placeholders->unassignedHall()->id)
        ->and($fixture['fullyAssigned']->fresh()->lecturer_id)->toBe($fixture['realLecturer']->id)
        ->and($fixture['fullyAssigned']->fresh()->hall_id)->toBe($fixture['realHall']->id)
        ->and($fixture['otherBatchSlot']->fresh()->lecturer_id)->toBeNull()
        ->and($fixture['otherBatchSlot']->fresh()->hall_id)->toBeNull()
        ->and($after->issueCountsByKey)->not->toHaveKeys(['missing_lecturer_identity', 'missing_hall', 'missing_active_lecturer_login', 'missing_course_lecturer_role'])
        ->and($after->uniqueAffectedSlots)->toBe(1)
        ->and($after->readySlots)->toBe(4);

    $invalidRow = collect($after->issues)->firstWhere('slot_id', $fixture['invalidTime']->id);
    $second = $service->apply($fixture['term'], $fixture['batch'], $actor);

    expect($invalidRow['reasons'])->toContain('invalid_time_range')
        ->and($second['affected_slot_count'])->toBe(0)
        ->and(Lecturer::query()->whereNotNull('placeholder_type')->count())->toBe(2)
        ->and(Hall::query()->whereNotNull('placeholder_type')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'auto_fill_missing_assignments')->count())->toBe(1);
});

it('does not treat placeholder resources as conflicts but retains real resource conflicts', function (): void {
    $term = taskTwoTerm('conflicts');
    $batch = taskTwoBatch($term, 'conflicts');
    $placeholders = app(SchedulePlaceholderService::class);
    $x0 = $placeholders->unassignedHall();
    $theoretical = $placeholders->theoreticalLecturer();
    $practical = $placeholders->practicalLecturer();
    $row = new ScheduleImportRow(['academic_term_id' => $term->id]);
    $detector = app(WeeklyScheduleSlotConflictDetector::class);

    $existingTheoretical = taskTwoSlot($batch, taskTwoSection($term, Subject::TYPE_THEORETICAL), [
        'lecturer_id' => $theoretical->id,
        'hall_id' => $x0->id,
    ]);
    $theoreticalCandidate = taskTwoSection($term, Subject::TYPE_THEORETICAL);
    $theoreticalConflicts = $detector->conflicts($row, [
        'subject_section_id' => $theoreticalCandidate->id,
        'lecturer_id' => $theoretical->id,
        'hall_id' => $x0->id,
        'weekday' => 1,
        'start_time' => '08:30:00',
        'end_time' => '09:30:00',
    ]);

    $existingPractical = taskTwoSlot($batch, taskTwoSection($term, Subject::TYPE_PRACTICAL), [
        'lecturer_id' => $practical->id,
        'hall_id' => $x0->id,
        'weekday' => 2,
    ]);
    $practicalCandidate = taskTwoSection($term, Subject::TYPE_PRACTICAL);
    $practicalConflicts = $detector->conflicts($row, [
        'subject_section_id' => $practicalCandidate->id,
        'lecturer_id' => $practical->id,
        'hall_id' => $x0->id,
        'weekday' => 2,
        'start_time' => '08:30:00',
        'end_time' => '09:30:00',
    ]);

    $realLecturer = taskTwoLecturer('conflicts');
    $realHall = taskTwoHall('conflicts');
    taskTwoSlot($batch, taskTwoSection($term, Subject::TYPE_THEORETICAL), [
        'lecturer_id' => $realLecturer->id,
        'hall_id' => $realHall->id,
        'weekday' => 3,
    ]);
    $realCandidate = taskTwoSection($term, Subject::TYPE_THEORETICAL);
    $realConflicts = $detector->conflicts($row, [
        'subject_section_id' => $realCandidate->id,
        'lecturer_id' => $realLecturer->id,
        'hall_id' => $realHall->id,
        'weekday' => 3,
        'start_time' => '08:30:00',
        'end_time' => '09:30:00',
    ]);

    expect($existingTheoretical->exists)->toBeTrue()
        ->and($existingPractical->exists)->toBeTrue()
        ->and($theoreticalConflicts)->toBe([])
        ->and($practicalConflicts)->toBe([])
        ->and(collect($realConflicts)->pluck('type')->all())->toContain('lecturer', 'hall');
});

it('keeps generation-backed issue conflicts clear for placeholders and present for real resources', function (): void {
    $term = taskTwoTerm('shared-readiness');
    taskTwoCompletedEnrollmentBatch($term, 'shared-readiness');
    $batch = taskTwoBatch($term, 'shared-readiness');
    $first = taskTwoSlot($batch, taskTwoSection($term, Subject::TYPE_THEORETICAL));
    $second = taskTwoSlot($batch, taskTwoSection($term, Subject::TYPE_THEORETICAL), [
        'start_time' => '08:30:00',
        'end_time' => '09:30:00',
    ]);
    $actor = taskTwoAdmin();

    app(SchedulePlaceholderAssignmentService::class)->apply($term, $batch, $actor);
    $placeholderResult = app(WeeklyScheduleIssueService::class)->result($term, $batch->id);

    expect($first->fresh()->lecturer->isPlaceholder())->toBeTrue()
        ->and($second->fresh()->hall->isPlaceholder())->toBeTrue()
        ->and($placeholderResult->issueCountsByKey)->not->toHaveKeys([
            'weekly_schedule_overlap',
            'missing_active_lecturer_login',
            'missing_course_lecturer_role',
        ])
        ->and($placeholderResult->readySlots)->toBe(2);

    $realLecturer = taskTwoLecturer('shared-readiness');
    $realHall = taskTwoHall('shared-readiness');
    $first->update(['lecturer_id' => $realLecturer->id, 'hall_id' => $realHall->id]);
    $second->update(['lecturer_id' => $realLecturer->id, 'hall_id' => $realHall->id]);
    $realResult = app(WeeklyScheduleIssueService::class)->result($term, $batch->id);

    expect($realResult->issueCountsByKey['weekly_schedule_overlap'])->toBe(2)
        ->and($realResult->uniqueAffectedSlots)->toBe(2);
});

it('rolls back the whole batch when placeholder resolution fails', function (): void {
    $term = taskTwoTerm('rollback');
    $batch = taskTwoBatch($term, 'rollback');
    $slot = taskTwoSlot($batch, taskTwoSection($term, Subject::TYPE_THEORETICAL));
    $actor = taskTwoAdmin();
    $theoretical = app(SchedulePlaceholderService::class)->theoreticalLecturer();
    $mock = Mockery::mock(SchedulePlaceholderService::class);
    $mock->shouldReceive('theoreticalLecturer')->once()->andReturn($theoretical);
    $mock->shouldReceive('unassignedHall')->once()->andThrow(new RuntimeException('forced rollback'));
    app()->instance(SchedulePlaceholderService::class, $mock);

    expect(fn () => app(SchedulePlaceholderAssignmentService::class)->apply($term, $batch, $actor))
        ->toThrow(RuntimeException::class, 'forced rollback')
        ->and($slot->fresh()->lecturer_id)->toBeNull()
        ->and($slot->fresh()->hall_id)->toBeNull()
        ->and(AuditLog::query()->where('action', 'auto_fill_missing_assignments')->exists())->toBeFalse();
});

it('uses the explicit historical batch in the bilingual page action and refreshes counters', function (): void {
    $fixture = taskTwoAssignmentFixture();
    $currentTerm = taskTwoTerm('current-other');
    AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, (string) $currentTerm->id);
    $admin = taskTwoAdmin();
    app()->setLocale('ar');

    $component = Livewire::withQueryParams(['batch' => $fixture['batch']->id])
        ->actingAs($admin)
        ->test(ScheduleImportIssues::class)
        ->assertSet('academicTermId', $fixture['term']->id)
        ->assertSet('importBatchId', $fixture['batch']->id)
        ->assertSee('معالجة النواقص تلقائيًا')
        ->call('openAutoFillConfirmation')
        ->assertSet('autoFillPreview.import_batch_id', $fixture['batch']->id)
        ->assertSee('مدرس نظري غير محدد')
        ->assertSee('مدرس عملي غير محدد')
        ->assertSee('قاعة X0')
        ->call('applyAutoFillAssignments');

    $refreshed = $component->instance()->issueResult();

    expect($component->get('issuesVersion'))->toBe(1)
        ->and($refreshed->issueCountsByKey)->not->toHaveKeys(['missing_lecturer_identity', 'missing_hall'])
        ->and($fixture['otherBatchSlot']->fresh()->lecturer_id)->toBeNull()
        ->and($fixture['otherBatchSlot']->fresh()->hall_id)->toBeNull();

    app()->setLocale('en');
    Livewire::withQueryParams(['batch' => $fixture['otherBatch']->id])
        ->actingAs($admin)
        ->test(ScheduleImportIssues::class)
        ->assertSee('Auto-fill missing assignments')
        ->call('openAutoFillConfirmation')
        ->assertSee('Default values will be assigned only to missing fields.');
});
