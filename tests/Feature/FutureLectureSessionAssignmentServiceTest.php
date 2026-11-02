<?php

use App\Models\AcademicTerm;
use App\Models\AppSetting;
use App\Models\Attendance;
use App\Models\AttendanceToken;
use App\Models\AuditLog;
use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\Lecturer;
use App\Models\LectureSession;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\FutureLectureSessionAssignmentService;
use App\Services\SchedulePlaceholderService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-01 10:00:00');
    Role::findOrCreate('super-admin', 'web');
    Role::findOrCreate('course_lecturer', 'web');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function futureAssignmentFixture(bool $lecturerHasValidUser = true): array
{
    $term = AcademicTerm::query()->create([
        'display_name' => 'Task 4 Term',
        'canonical_name' => 'task-4-term',
        'teaching_start_date' => '2026-10-01',
        'teaching_end_date' => '2026-12-31',
    ]);
    AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, (string) $term->id);

    $admin = User::factory()->create(['role' => 'super_admin', 'type' => 'admin', 'status' => 'active', 'is_active' => true]);
    $admin->assignRole('super-admin');
    $lecturerUser = $lecturerHasValidUser
        ? User::factory()->create(['name' => 'Ahmad Ali', 'role' => 'course_lecturer', 'type' => 'lecturer', 'status' => 'active', 'is_active' => true])
        : null;
    $lecturerUser?->assignRole('course_lecturer');
    $realLecturer = Lecturer::query()->create([
        'user_id' => $lecturerUser?->id,
        'name' => 'أحمد علي',
        'name_en' => 'Ahmad Ali',
        'canonical_name' => 'أحمد علي',
        'is_active' => true,
    ]);
    $realHall = Hall::query()->create([
        'code' => 'LAB-3',
        'name' => 'المخبر 3',
        'name_en' => 'LAB-3',
        'is_active' => true,
    ]);
    $placeholders = app(SchedulePlaceholderService::class);
    $placeholderLecturer = $placeholders->practicalLecturer();
    $placeholderHall = $placeholders->unassignedHall();
    $subject = Subject::query()->create([
        'code' => 'TASK4-101',
        'name' => 'Task 4 Subject',
        'subject_type' => Subject::TYPE_PRACTICAL,
        'is_active' => true,
    ]);
    $section = SubjectSection::query()->create([
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'section_type' => Subject::TYPE_PRACTICAL,
        'code' => 'P1',
        'name' => 'P1',
        'capacity' => 30,
    ]);
    $batch = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'task-4-schedule-'.$term->id),
        'import_type' => ImportBatch::TYPE_WEEKLY_SCHEDULE,
        'status' => ImportBatch::STATUS_COMPLETED,
        'imported_rows' => 1,
        'total_rows' => 1,
        'completed_at' => now(),
    ]);
    $batch->academicTerms()->attach($term->id, ['row_count' => 1]);
    $slot = SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $batch->id,
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'subject_section_id' => $section->id,
        'lecturer_id' => $placeholderLecturer->id,
        'hall_id' => $placeholderHall->id,
        'weekday' => 1,
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
    ]);

    $makeSession = function (string $date, string $status = 'scheduled') use ($term, $subject, $section, $slot, $placeholderLecturer, $placeholderHall): LectureSession {
        return LectureSession::query()->create([
            'academic_term_id' => $term->id,
            'subject_id' => $subject->id,
            'subject_section_id' => $section->id,
            'subject_section_schedule_slot_id' => $slot->id,
            'lecturer_id' => null,
            'lecturer_identity_id' => $placeholderLecturer->id,
            'hall_id' => $placeholderHall->id,
            'session_date' => $date,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'status' => $status,
            'attendance_mode' => 'qr_otp',
            'qr_refresh_rate' => 120,
        ]);
    };

    $past = $makeSession('2026-10-31');
    $completed = $makeSession('2026-11-02', 'completed');
    $withAttendance = $makeSession('2026-11-03');
    $future = collect([
        $makeSession('2026-11-04'),
        $makeSession('2026-11-05'),
        $makeSession('2026-11-06'),
    ]);
    $student = Student::query()->create([
        'name' => 'Task 4 Student',
        'student_number' => 'TASK4-STUDENT',
        'status' => 'active',
        'is_active' => true,
    ]);
    Attendance::query()->create([
        'lecture_session_id' => $withAttendance->id,
        'student_id' => $student->id,
        'attendance_time' => now(),
        'attendance_method' => 'manual',
        'attendance_status' => 'present',
    ]);

    return compact(
        'term', 'admin', 'lecturerUser', 'realLecturer', 'realHall', 'placeholderLecturer', 'placeholderHall',
        'subject', 'section', 'batch', 'slot', 'past', 'completed', 'withAttendance', 'future',
    );
}

it('updates only safe future sessions after an explicit lecturer and hall replacement', function (): void {
    $fixture = futureAssignmentFixture();
    $service = app(FutureLectureSessionAssignmentService::class);
    $beforeIds = LectureSession::query()->pluck('lecturer_identity_id', 'id');
    $beforeHalls = LectureSession::query()->pluck('hall_id', 'id');
    $beforeNeedsAssignment = LectureSession::query()->with(['lecturerIdentity', 'hall'])->get()->filter->needsAssignment()->count();

    $service->replacePlaceholders(
        $fixture['slot'],
        $fixture['realLecturer']->id,
        $fixture['realHall']->id,
        $fixture['admin'],
    );

    expect(LectureSession::query()->pluck('lecturer_identity_id', 'id')->all())->toBe($beforeIds->all())
        ->and(LectureSession::query()->pluck('hall_id', 'id')->all())->toBe($beforeHalls->all())
        ->and($beforeNeedsAssignment)->toBe(6)
        ->and($fixture['slot']->fresh()->lecturer_id)->toBe($fixture['realLecturer']->id)
        ->and($fixture['slot']->fresh()->hall_id)->toBe($fixture['realHall']->id);

    $preview = $service->preview($fixture['slot']->fresh());

    expect($preview)->toMatchArray([
        'source_session_count' => 6,
        'eligible_future_count' => 3,
        'skipped_past_count' => 1,
        'skipped_completed_count' => 1,
        'skipped_attendance_count' => 1,
        'conflict_count' => 0,
        'lecturer_account_missing' => false,
    ])
        ->and($preview['lecturer_from_names'])->toContain($fixture['placeholderLecturer']->name)
        ->and($preview['hall_from_names'])->toContain($fixture['placeholderHall']->name);

    $previewHtml = view('filament.components.future-session-assignment-preview', compact('preview'))->render();
    expect($previewHtml)->toContain($fixture['placeholderLecturer']->name)
        ->and($previewHtml)->toContain($fixture['realLecturer']->name)
        ->and($previewHtml)->toContain($fixture['placeholderHall']->name)
        ->and($previewHtml)->toContain($fixture['realHall']->name);

    $result = $service->apply($fixture['slot']->fresh(), $fixture['admin']);

    expect($result['updated_sessions_count'])->toBe(3)
        ->and($fixture['past']->fresh()->lecturer_identity_id)->toBe($fixture['placeholderLecturer']->id)
        ->and($fixture['completed']->fresh()->lecturer_identity_id)->toBe($fixture['placeholderLecturer']->id)
        ->and($fixture['withAttendance']->fresh()->lecturer_identity_id)->toBe($fixture['placeholderLecturer']->id);

    foreach ($fixture['future'] as $session) {
        $session->refresh()->load(['lecturerIdentity', 'hall']);
        expect($session->lecturer_identity_id)->toBe($fixture['realLecturer']->id)
            ->and($session->lecturer_id)->toBe($fixture['lecturerUser']->id)
            ->and($session->hall_id)->toBe($fixture['realHall']->id)
            ->and($session->needsAssignment())->toBeFalse()
            ->and($session->status)->toBe('scheduled');
    }

    expect(AuditLog::query()->where('action', 'apply_schedule_assignment_changes_to_future_sessions')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'apply_schedule_assignment_changes_to_future_sessions')->first()->metadata)
        ->toMatchArray([
            'academic_term_id' => $fixture['term']->id,
            'batch_id' => $fixture['batch']->id,
            'schedule_slot_id' => $fixture['slot']->id,
            'old_lecturer_identity_id' => $fixture['placeholderLecturer']->id,
            'new_lecturer_identity_id' => $fixture['realLecturer']->id,
            'old_hall_id' => $fixture['placeholderHall']->id,
            'new_hall_id' => $fixture['realHall']->id,
            'updated_sessions_count' => 3,
            'skipped_past_count' => 1,
            'skipped_completed_count' => 1,
            'skipped_attendance_count' => 1,
            'user_id' => $fixture['admin']->id,
        ])
        ->and(LectureSession::query()->with(['lecturerIdentity', 'hall'])->get()->filter->needsAssignment()->count())->toBe(3);

    $second = $service->apply($fixture['slot']->fresh(), $fixture['admin']);
    expect($second['updated_sessions_count'])->toBe(0)
        ->and(LectureSession::query()->count())->toBe(6)
        ->and(AuditLog::query()->where('action', 'apply_schedule_assignment_changes_to_future_sessions')->count())->toBe(1);
});

it('supports lecturer-only replacement without changing X0', function (): void {
    $fixture = futureAssignmentFixture();
    $service = app(FutureLectureSessionAssignmentService::class);
    $service->replacePlaceholders($fixture['slot'], $fixture['realLecturer']->id, null, $fixture['admin']);
    $result = $service->apply($fixture['slot']->fresh(), $fixture['admin']);

    expect($result['updated_sessions_count'])->toBe(3);
    foreach ($fixture['future'] as $session) {
        $session->refresh()->load(['lecturerIdentity', 'hall']);
        expect($session->lecturer_identity_id)->toBe($fixture['realLecturer']->id)
            ->and($session->lecturer_id)->toBe($fixture['lecturerUser']->id)
            ->and($session->hall_id)->toBe($fixture['placeholderHall']->id)
            ->and($session->needsAssignment())->toBeTrue();
    }
});

it('supports hall-only replacement without changing the placeholder lecturer', function (): void {
    $fixture = futureAssignmentFixture();
    $service = app(FutureLectureSessionAssignmentService::class);
    $service->replacePlaceholders($fixture['slot'], null, $fixture['realHall']->id, $fixture['admin']);
    $result = $service->apply($fixture['slot']->fresh(), $fixture['admin']);

    expect($result['updated_sessions_count'])->toBe(3);
    foreach ($fixture['future'] as $session) {
        $session->refresh()->load(['lecturerIdentity', 'hall']);
        expect($session->lecturer_identity_id)->toBe($fixture['placeholderLecturer']->id)
            ->and($session->lecturer_id)->toBeNull()
            ->and($session->hall_id)->toBe($fixture['realHall']->id)
            ->and($session->needsAssignment())->toBeTrue();
    }
});

it('updates academic lecturer identity without inventing a user account', function (): void {
    $fixture = futureAssignmentFixture(lecturerHasValidUser: false);
    $service = app(FutureLectureSessionAssignmentService::class);
    $service->replacePlaceholders($fixture['slot'], $fixture['realLecturer']->id, $fixture['realHall']->id, $fixture['admin']);
    $preview = $service->preview($fixture['slot']->fresh());
    $result = $service->apply($fixture['slot']->fresh(), $fixture['admin']);

    expect($preview['lecturer_account_missing'])->toBeTrue()
        ->and($result['updated_sessions_count'])->toBe(3)
        ->and(__('weekly-schedule.future_assignment.account_warning'))->toContain('لا يملك حساب مستخدم');
    foreach ($fixture['future'] as $session) {
        expect($session->fresh()->lecturer_identity_id)->toBe($fixture['realLecturer']->id)
            ->and($session->fresh()->lecturer_id)->toBeNull();
    }
});

it('preserves future sessions that have started or have attendance activity', function (): void {
    $fixture = futureAssignmentFixture();
    $started = $fixture['future']->first()->replicate();
    $started->session_date = '2026-11-07';
    $started->status = 'active';
    $started->save();
    $withActivity = $fixture['future']->first()->replicate();
    $withActivity->session_date = '2026-11-08';
    $withActivity->save();
    AttendanceToken::query()->create([
        'lecture_session_id' => $withActivity->id,
        'token_type' => 'qr',
        'token_value' => 'task-4-activity-token',
        'expires_at' => '2026-11-08 09:00:00',
        'is_used' => false,
    ]);
    $service = app(FutureLectureSessionAssignmentService::class);
    $service->replacePlaceholders($fixture['slot'], $fixture['realLecturer']->id, $fixture['realHall']->id, $fixture['admin']);
    $preview = $service->preview($fixture['slot']->fresh());
    $result = $service->apply($fixture['slot']->fresh(), $fixture['admin']);

    expect($preview['skipped_started_count'])->toBe(1)
        ->and($preview['skipped_activity_count'])->toBe(1)
        ->and($result['updated_sessions_count'])->toBe(3)
        ->and($started->fresh()->lecturer_identity_id)->toBe($fixture['placeholderLecturer']->id)
        ->and($started->fresh()->hall_id)->toBe($fixture['placeholderHall']->id)
        ->and($withActivity->fresh()->lecturer_identity_id)->toBe($fixture['placeholderLecturer']->id)
        ->and($withActivity->fresh()->hall_id)->toBe($fixture['placeholderHall']->id);
});

it('does not update sessions belonging to another weekly slot or academic term context', function (): void {
    $fixture = futureAssignmentFixture();
    $otherTerm = AcademicTerm::query()->create(['display_name' => 'Other', 'canonical_name' => 'other-task-4']);
    AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, (string) $otherTerm->id);
    $otherSlot = $fixture['slot']->replicate();
    $otherSlot->weekday = 2;
    $otherSlot->save();
    $unrelated = $fixture['future']->first()->replicate();
    $unrelated->subject_section_schedule_slot_id = $otherSlot->id;
    $unrelated->session_date = '2026-11-07';
    $unrelated->save();
    $service = app(FutureLectureSessionAssignmentService::class);
    $service->replacePlaceholders($fixture['slot'], $fixture['realLecturer']->id, $fixture['realHall']->id, $fixture['admin']);
    $service->apply($fixture['slot']->fresh(), $fixture['admin']);

    expect($unrelated->fresh()->lecturer_identity_id)->toBe($fixture['placeholderLecturer']->id)
        ->and($unrelated->fresh()->hall_id)->toBe($fixture['placeholderHall']->id);
});

it('blocks the whole update when the real lecturer would conflict', function (): void {
    $fixture = futureAssignmentFixture();
    $candidate = $fixture['future']->first();
    $conflicting = $candidate->replicate();
    $conflicting->subject_section_schedule_slot_id = null;
    $conflicting->subject_section_id = null;
    $conflicting->lecturer_identity_id = $fixture['realLecturer']->id;
    $conflicting->lecturer_id = $fixture['lecturerUser']->id;
    $conflicting->hall_id = Hall::query()->create(['code' => 'OTHER-H', 'name' => 'Other Hall', 'is_active' => true])->id;
    $conflicting->save();
    $service = app(FutureLectureSessionAssignmentService::class);
    $service->replacePlaceholders($fixture['slot'], $fixture['realLecturer']->id, null, $fixture['admin']);
    $preview = $service->preview($fixture['slot']->fresh());

    expect($preview['conflicts'])->toContainEqual([
        'session_id' => $candidate->id,
        'conflicting_session_id' => $conflicting->id,
        'type' => 'lecturer',
    ]);

    expect(fn () => $service->apply($fixture['slot']->fresh(), $fixture['admin']))
        ->toThrow(ValidationException::class);
    expect($candidate->fresh()->lecturer_identity_id)->toBe($fixture['placeholderLecturer']->id);
});

it('blocks the whole update when the real hall would conflict', function (): void {
    $fixture = futureAssignmentFixture();
    $candidate = $fixture['future']->first();
    $conflicting = $candidate->replicate();
    $conflicting->subject_section_schedule_slot_id = null;
    $conflicting->subject_section_id = null;
    $conflicting->lecturer_identity_id = null;
    $conflicting->hall_id = $fixture['realHall']->id;
    $conflicting->save();
    $service = app(FutureLectureSessionAssignmentService::class);
    $service->replacePlaceholders($fixture['slot'], null, $fixture['realHall']->id, $fixture['admin']);

    expect(fn () => $service->apply($fixture['slot']->fresh(), $fixture['admin']))
        ->toThrow(ValidationException::class);
    expect($candidate->fresh()->hall_id)->toBe($fixture['placeholderHall']->id);
});

it('searches only available real resources and rechecks availability while saving', function (): void {
    $fixture = futureAssignmentFixture();
    $service = app(FutureLectureSessionAssignmentService::class);
    $otherSubject = Subject::query()->create([
        'code' => 'TASK4-CONFLICT',
        'name' => 'Task 4 Conflict Subject',
        'subject_type' => Subject::TYPE_PRACTICAL,
        'is_active' => true,
    ]);
    $otherSection = SubjectSection::query()->create([
        'academic_term_id' => $fixture['term']->id,
        'subject_id' => $otherSubject->id,
        'section_type' => Subject::TYPE_PRACTICAL,
        'code' => 'P2',
        'name' => 'P2',
    ]);
    $conflictingSlot = $fixture['slot']->replicate();
    $conflictingSlot->subject_id = $otherSubject->id;
    $conflictingSlot->subject_section_id = $otherSection->id;
    $conflictingSlot->lecturer_id = $fixture['realLecturer']->id;
    $conflictingSlot->hall_id = $fixture['realHall']->id;
    $conflictingSlot->save();
    $availableLecturer = Lecturer::query()->create([
        'name' => 'أحمد المتاح',
        'canonical_name' => 'أحمد المتاح',
        'is_active' => true,
    ]);
    $availableHall = Hall::query()->create([
        'code' => 'LAB-AVAILABLE',
        'name' => 'المخبر المتاح',
        'is_active' => true,
    ]);

    $lecturers = $service->searchAvailableLecturers($fixture['slot'], 'أحمد');
    $halls = $service->searchAvailableHalls($fixture['slot'], 'المخبر');

    expect($lecturers)->toHaveKey($availableLecturer->id)
        ->not->toHaveKey($fixture['realLecturer']->id)
        ->not->toHaveKey($fixture['placeholderLecturer']->id)
        ->and($halls)->toHaveKey($availableHall->id)
        ->not->toHaveKey($fixture['realHall']->id)
        ->not->toHaveKey($fixture['placeholderHall']->id)
        ->and(count($service->searchAvailableLecturers($fixture['slot'], '')))->toBeLessThanOrEqual(50)
        ->and(count($service->searchAvailableHalls($fixture['slot'], '')))->toBeLessThanOrEqual(50);

    try {
        $service->replacePlaceholders($fixture['slot'], $fixture['realLecturer']->id, $fixture['realHall']->id, $fixture['admin']);
        $this->fail('Expected the locked availability recheck to reject both resources.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKeys(['lecturer_id', 'hall_id'])
            ->and($exception->errors()['lecturer_id'][0])->toBe(__('weekly-schedule.future_assignment.validation.lecturer_unavailable'))
            ->and($exception->errors()['hall_id'][0])->toBe(__('weekly-schedule.future_assignment.validation.hall_unavailable'));
    }

    expect($fixture['slot']->fresh()->lecturer_id)->toBe($fixture['placeholderLecturer']->id)
        ->and($fixture['slot']->fresh()->hall_id)->toBe($fixture['placeholderHall']->id);
});

it('rolls back every session update when applying the operation fails', function (): void {
    $fixture = futureAssignmentFixture();
    $realLogger = app(ActivityLogger::class);
    $service = app(FutureLectureSessionAssignmentService::class);
    $service->replacePlaceholders($fixture['slot'], $fixture['realLecturer']->id, $fixture['realHall']->id, $fixture['admin']);
    $failingLogger = Mockery::mock(ActivityLogger::class);
    $failingLogger->shouldReceive('log')->once()->andThrow(new RuntimeException('audit failure'));
    $failingService = new FutureLectureSessionAssignmentService($failingLogger);

    expect(fn () => $failingService->apply($fixture['slot']->fresh(), $fixture['admin']))
        ->toThrow(RuntimeException::class, 'audit failure');
    foreach ($fixture['future'] as $session) {
        expect($session->fresh()->lecturer_identity_id)->toBe($fixture['placeholderLecturer']->id)
            ->and($session->fresh()->hall_id)->toBe($fixture['placeholderHall']->id);
    }
    expect($realLogger)->toBeInstanceOf(ActivityLogger::class);
});

it('provides Arabic and English workflow labels', function (): void {
    app()->setLocale('ar');
    expect(__('weekly-schedule.future_assignment.apply_action'))->toBe('تطبيق التغييرات على الجلسات المستقبلية');

    app()->setLocale('en');
    expect(__('weekly-schedule.future_assignment.apply_action'))->toBe('Apply changes to future sessions')
        ->and(__('weekly-schedule.future_assignment.account_warning'))->toContain('does not have a valid');
});
