<?php

use App\Exports\LectureSessionGenerationReportExport;
use App\Models\AcademicTerm;
use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\Lecturer;
use App\Models\LectureSession;
use App\Models\ScheduleImportRow;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Services\LectureSessionGenerationService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

function lectureSessionGenerationFixture(array $slotOverrides = []): array
{
    $term = AcademicTerm::query()->create([
        'display_name' => 'Fall 2026',
        'canonical_name' => 'fall-2026',
        'teaching_start_date' => '2026-09-07',
        'teaching_end_date' => '2026-09-21',
    ]);

    $admin = User::factory()->create([
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);

    $lecturerUser = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);
    Role::firstOrCreate(['name' => 'course_lecturer', 'guard_name' => 'web']);
    $lecturerUser->assignRole('course_lecturer');

    $subject = Subject::query()->create([
        'code' => 'PH3-101',
        'name' => 'Phase Three Subject',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'lecturer_id' => $lecturerUser->id,
        'is_active' => true,
    ]);

    $section = SubjectSection::query()->create([
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'lecturer_id' => $lecturerUser->id,
        'section_type' => Subject::TYPE_THEORETICAL,
        'code' => 'T1',
        'name' => 'T1',
        'capacity' => 40,
    ]);

    $lecturerIdentity = Lecturer::query()->create([
        'user_id' => $lecturerUser->id,
        'name' => 'Phase Three Lecturer',
        'canonical_name' => 'phase three lecturer',
        'is_active' => true,
    ]);

    $hall = Hall::query()->create([
        'code' => 'PH3-H1',
        'name' => 'Phase Three Hall',
        'floor' => 1,
        'is_active' => true,
    ]);

    $enrollmentBatch = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'phase3-enrollments'),
        'import_type' => ImportBatch::TYPE_ENROLLMENTS,
        'status' => ImportBatch::STATUS_COMPLETED,
        'imported_rows' => 10,
        'total_rows' => 10,
        'completed_at' => now(),
    ]);
    $enrollmentBatch->academicTerms()->attach($term->id, ['row_count' => 10]);

    $scheduleBatch = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'phase3-schedule'),
        'import_type' => ImportBatch::TYPE_WEEKLY_SCHEDULE,
        'status' => ImportBatch::STATUS_COMPLETED,
        'imported_rows' => 1,
        'total_rows' => 1,
        'completed_at' => now(),
    ]);
    $scheduleBatch->academicTerms()->attach($term->id, ['row_count' => 1]);

    $slot = SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $scheduleBatch->id,
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'subject_section_id' => $section->id,
        'lecturer_id' => $lecturerIdentity->id,
        'hall_id' => $hall->id,
        'weekday' => Carbon::MONDAY,
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'section_capacity' => 40,
        'expected_student_count' => 35,
        ...$slotOverrides,
    ]);

    return compact(
        'term',
        'admin',
        'lecturerUser',
        'subject',
        'section',
        'lecturerIdentity',
        'hall',
        'enrollmentBatch',
        'scheduleBatch',
        'slot',
    );
}

it('generates dated sessions from weekly slots using the linked lecturer login account', function (): void {
    Carbon::setTestNow('2026-07-22 12:00:00');
    $fixture = lectureSessionGenerationFixture();

    $result = app(LectureSessionGenerationService::class)->generate($fixture['term'], $fixture['admin']);

    expect($result['created_session_count'])->toBe(3)
        ->and($result['candidate_session_count'])->toBe(3)
        ->and(LectureSession::query()->count())->toBe(3);

    $session = LectureSession::query()->orderBy('session_date')->first();

    expect($session->lecturer_id)->toBe($fixture['lecturerUser']->id)
        ->and($session->lecturer_id)->not->toBe($fixture['lecturerIdentity']->id)
        ->and($session->subject_section_schedule_slot_id)->toBe($fixture['slot']->id)
        ->and($session->academic_term_id)->toBe($fixture['term']->id)
        ->and($session->generated_from_weekly_schedule_at)->not->toBeNull()
        ->and($session->lecture_session_generation_run_id)->not->toBeNull();

    $secondRun = app(LectureSessionGenerationService::class)->generate($fixture['term'], $fixture['admin']);

    expect($secondRun['created_session_count'])->toBe(0)
        ->and($secondRun['skipped_session_count'])->toBe(3)
        ->and(LectureSession::query()->count())->toBe(3);

    Carbon::setTestNow();
});

it('blocks weekly slots whose lecturer identity has no active linked user login', function (): void {
    $fixture = lectureSessionGenerationFixture();
    $fixture['lecturerIdentity']->update(['user_id' => null]);

    $preview = app(LectureSessionGenerationService::class)->preview($fixture['term']);

    expect($preview['ready'])->toBeFalse()
        ->and($preview['blocked_slot_count'])->toBe(1)
        ->and($preview['blocked_slots'][0]['reasons'])->toContain('missing_active_lecturer_login')
        ->and(LectureSession::query()->count())->toBe(0);

    app(LectureSessionGenerationService::class)->generate($fixture['term'], $fixture['admin']);
})->throws(ValidationException::class);

it('previews generation without creating users or sessions', function (): void {
    $fixture = lectureSessionGenerationFixture();
    $usersBefore = User::query()->count();
    $sessionsBefore = LectureSession::query()->count();

    $preview = app(LectureSessionGenerationService::class)->preview($fixture['term']);

    expect($preview['ready'])->toBeTrue()
        ->and($preview['ready_for_partial_generation'])->toBeTrue()
        ->and($preview['safe_to_create_count'])->toBe(3)
        ->and($preview['structural_readiness']['total_weekly_slots'])->toBe(1)
        ->and($preview['structural_readiness']['ready_slots'])->toBe(1)
        ->and(User::query()->count())->toBe($usersBefore)
        ->and(LectureSession::query()->count())->toBe($sessionsBefore);
});

it('does not block partial-safe generation when the installed weekly schedule batch completed with errors', function (): void {
    $fixture = lectureSessionGenerationFixture();
    $fixture['scheduleBatch']->update(['status' => ImportBatch::STATUS_COMPLETED_WITH_ERRORS]);
    $blockedSection = SubjectSection::query()->create([
        'academic_term_id' => $fixture['term']->id,
        'subject_id' => $fixture['subject']->id,
        'lecturer_id' => $fixture['lecturerUser']->id,
        'section_type' => Subject::TYPE_THEORETICAL,
        'code' => 'T2',
        'name' => 'T2',
        'capacity' => 20,
    ]);

    SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $fixture['scheduleBatch']->id,
        'academic_term_id' => $fixture['term']->id,
        'subject_id' => $fixture['subject']->id,
        'subject_section_id' => $blockedSection->id,
        'lecturer_id' => $fixture['lecturerIdentity']->id,
        'hall_id' => null,
        'weekday' => Carbon::TUESDAY,
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'section_capacity' => 20,
        'expected_student_count' => 15,
    ]);

    $preview = app(LectureSessionGenerationService::class)->preview($fixture['term']);

    expect($preview['ready'])->toBeFalse()
        ->and($preview['ready_for_partial_generation'])->toBeTrue()
        ->and($preview['prerequisite_errors'])->not->toContain('missing_completed_weekly_schedule_batch')
        ->and($preview['to_create_count'])->toBe(3)
        ->and($preview['safe_to_create_count'])->toBe(3)
        ->and($preview['blocked_slot_count'])->toBe(1)
        ->and($preview['blocked_slots'][0]['reasons'])->toContain('missing_hall');
});

it('reports structural readiness even when teaching dates are missing', function (): void {
    $fixture = lectureSessionGenerationFixture();
    $fixture['term']->update([
        'teaching_start_date' => null,
        'teaching_end_date' => null,
    ]);

    $preview = app(LectureSessionGenerationService::class)->preview($fixture['term']);

    expect($preview['ready'])->toBeFalse()
        ->and($preview['ready_for_partial_generation'])->toBeFalse()
        ->and($preview['prerequisite_errors'])->toContain('missing_teaching_dates')
        ->and($preview['source_slot_count'])->toBe(1)
        ->and($preview['structural_readiness']['total_weekly_slots'])->toBe(1)
        ->and($preview['structural_readiness']['ready_slots'])->toBe(1);
});

it('treats weekly schedule weekday seven as Sunday', function (): void {
    $fixture = lectureSessionGenerationFixture(['weekday' => 7]);

    $result = app(LectureSessionGenerationService::class)->generate($fixture['term'], $fixture['admin']);

    expect($result['created_session_count'])->toBe(2)
        ->and(LectureSession::query()->orderBy('session_date')->pluck('session_date')->map->toDateString()->all())
        ->toBe(['2026-09-13', '2026-09-20']);
});

it('keeps generation blocked until the linked lecturer account has the course lecturer role', function (): void {
    $fixture = lectureSessionGenerationFixture();
    $fixture['lecturerUser']->removeRole('course_lecturer');

    $preview = app(LectureSessionGenerationService::class)->preview($fixture['term']);

    expect($preview['ready'])->toBeFalse()
        ->and($preview['blocked_slot_count'])->toBe(1)
        ->and($preview['blocked_slots'][0]['reasons'])->toContain('missing_course_lecturer_role')
        ->and(LectureSession::query()->count())->toBe(0);

    app(LectureSessionGenerationService::class)->generate($fixture['term'], $fixture['admin']);
})->throws(ValidationException::class);

it('generates only ready sessions and reports blocked weekly slots separately', function (): void {
    $fixture = lectureSessionGenerationFixture();
    $blockedSection = SubjectSection::query()->create([
        'academic_term_id' => $fixture['term']->id,
        'subject_id' => $fixture['subject']->id,
        'lecturer_id' => $fixture['lecturerUser']->id,
        'section_type' => Subject::TYPE_THEORETICAL,
        'code' => 'T2',
        'name' => 'T2',
        'capacity' => 20,
    ]);

    SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $fixture['scheduleBatch']->id,
        'academic_term_id' => $fixture['term']->id,
        'subject_id' => $fixture['subject']->id,
        'subject_section_id' => $blockedSection->id,
        'lecturer_id' => $fixture['lecturerIdentity']->id,
        'hall_id' => null,
        'weekday' => Carbon::TUESDAY,
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'section_capacity' => 20,
        'expected_student_count' => 15,
    ]);

    $preview = app(LectureSessionGenerationService::class)->preview($fixture['term']);

    expect($preview['ready'])->toBeFalse()
        ->and($preview['to_create_count'])->toBe(3)
        ->and($preview['blocked_slot_count'])->toBe(1)
        ->and($preview['blocked_slots'][0]['reasons'])->toContain('missing_hall');

    $result = app(LectureSessionGenerationService::class)->generateReadySessions($fixture['term'], $fixture['admin']);

    expect($result['created_session_count'])->toBe(3)
        ->and(collect($result['error_report'])->pluck('رمز الخطأ'))->toContain('missing_hall')
        ->and($result['success_report'])->toHaveCount(3)
        ->and(LectureSession::query()->count())->toBe(3)
        ->and(LectureSession::query()->where('subject_section_id', $blockedSection->id)->count())->toBe(0);
});

it('exports lecture-session generation success and skipped reports as Arabic RTL xlsx', function (): void {
    $fixture = lectureSessionGenerationFixture();
    $blockedSection = SubjectSection::query()->create([
        'academic_term_id' => $fixture['term']->id,
        'subject_id' => $fixture['subject']->id,
        'section_type' => Subject::TYPE_THEORETICAL,
        'code' => 'BLOCKED',
        'name' => 'BLOCKED',
    ]);

    SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $fixture['scheduleBatch']->id,
        'academic_term_id' => $fixture['term']->id,
        'subject_id' => $fixture['subject']->id,
        'subject_section_id' => $blockedSection->id,
        'lecturer_id' => $fixture['lecturerIdentity']->id,
        'hall_id' => null,
        'weekday' => Carbon::TUESDAY,
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'section_capacity' => 20,
        'expected_student_count' => 15,
    ]);

    $result = app(LectureSessionGenerationService::class)->generateReadySessions($fixture['term'], $fixture['admin']);
    $successBook = spreadsheetFromXlsxBytes(Excel::raw(LectureSessionGenerationReportExport::success($result['success_report']), ExcelWriter::XLSX));
    $errorBook = spreadsheetFromXlsxBytes(Excel::raw(LectureSessionGenerationReportExport::errors($result['error_report']), ExcelWriter::XLSX));
    $successSheet = $successBook->getSheetByName('العمليات الناجحة');
    $errorSheet = $errorBook->getSheetByName('الأخطاء والحالات المستبعدة');
    $successResponse = Excel::download(
        LectureSessionGenerationReportExport::success($result['success_report']),
        'lecture-session-generation-success.xlsx',
        ExcelWriter::XLSX,
        ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    );
    $errorResponse = Excel::download(
        LectureSessionGenerationReportExport::errors($result['error_report']),
        'lecture-session-generation-errors.xlsx',
        ExcelWriter::XLSX,
        ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    );

    expect($successResponse->headers->get('content-type'))->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and((string) $successResponse->headers->get('content-disposition'))->toContain('lecture-session-generation-success.xlsx')
        ->and($errorResponse->headers->get('content-type'))->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and((string) $errorResponse->headers->get('content-disposition'))->toContain('lecture-session-generation-errors.xlsx')
        ->and($successSheet)->not->toBeNull()
        ->and($errorSheet)->not->toBeNull()
        ->and($successSheet->getRightToLeft())->toBeTrue()
        ->and($errorSheet->getRightToLeft())->toBeTrue()
        ->and($successSheet->getCell('A1')->getValue())->toBe('الرقم')
        ->and($successSheet->getCell('B1')->getValue())->toBe('المادة')
        ->and($successSheet->getCell('K1')->getValue())->toBe('وقت النهاية')
        ->and($successSheet->getCell('M1')->getValue())->toBe('رقم الموعد الأسبوعي المصدر')
        ->and($errorSheet->getCell('A1')->getValue())->toBe('الرقم')
        ->and($errorSheet->getCell('B1')->getValue())->toBe('الموعد الأسبوعي المصدر')
        ->and($errorSheet->getCell('K1')->getValue())->toBe('الإجراء المقترح')
        ->and(spreadsheetCellValues($errorBook))->toContain('missing_hall');
});

it('does not partially create either side of a weekly schedule conflict', function (): void {
    $fixture = lectureSessionGenerationFixture();
    $secondUser = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);
    $secondUser->assignRole('course_lecturer');
    $secondLecturer = Lecturer::query()->create([
        'user_id' => $secondUser->id,
        'name' => 'Second Lecturer',
        'canonical_name' => 'second lecturer',
        'is_active' => true,
    ]);
    $secondSection = SubjectSection::query()->create([
        'academic_term_id' => $fixture['term']->id,
        'subject_id' => $fixture['subject']->id,
        'lecturer_id' => $secondUser->id,
        'section_type' => Subject::TYPE_THEORETICAL,
        'code' => 'T2',
        'name' => 'T2',
        'capacity' => 20,
    ]);

    SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $fixture['scheduleBatch']->id,
        'academic_term_id' => $fixture['term']->id,
        'subject_id' => $fixture['subject']->id,
        'subject_section_id' => $secondSection->id,
        'lecturer_id' => $secondLecturer->id,
        'hall_id' => $fixture['hall']->id,
        'weekday' => Carbon::MONDAY,
        'start_time' => '08:30:00',
        'end_time' => '09:30:00',
        'section_capacity' => 20,
        'expected_student_count' => 15,
    ]);

    $result = app(LectureSessionGenerationService::class)->generateReadySessions($fixture['term'], $fixture['admin']);

    expect($result['created_session_count'])->toBe(0)
        ->and($result['conflict_count'])->toBe(3)
        ->and(collect($result['error_report'])->pluck('الموعد الأسبوعي المصدر'))->toContain($fixture['slot']->id)
        ->and(collect($result['error_report'])->pluck('الموعد الأسبوعي المصدر'))->toContain(SubjectSectionScheduleSlot::query()->latest('id')->value('id'))
        ->and(LectureSession::query()->count())->toBe(0);
});

it('detects overlapping persisted sessions and allows adjacent sessions', function (): void {
    $fixture = lectureSessionGenerationFixture();

    LectureSession::query()->create([
        'subject_id' => $fixture['subject']->id,
        'subject_section_id' => $fixture['section']->id,
        'lecturer_id' => $fixture['lecturerUser']->id,
        'hall_id' => $fixture['hall']->id,
        'session_date' => '2026-09-07',
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'status' => 'scheduled',
        'attendance_mode' => 'qr_otp',
        'qr_refresh_rate' => 120,
    ]);

    $preview = app(LectureSessionGenerationService::class)->preview($fixture['term']);

    expect($preview['ready'])->toBeTrue()
        ->and($preview['conflict_count'])->toBe(0);

    LectureSession::query()->create([
        'subject_id' => $fixture['subject']->id,
        'subject_section_id' => $fixture['section']->id,
        'lecturer_id' => $fixture['lecturerUser']->id,
        'hall_id' => $fixture['hall']->id,
        'session_date' => '2026-09-14',
        'start_time' => '08:30:00',
        'end_time' => '09:30:00',
        'status' => 'scheduled',
        'attendance_mode' => 'qr_otp',
        'qr_refresh_rate' => 120,
    ]);

    $preview = app(LectureSessionGenerationService::class)->preview($fixture['term']);

    expect($preview['ready'])->toBeFalse()
        ->and($preview['conflict_count'])->toBe(1)
        ->and($preview['conflicts'][0]['reason'])->toBe('persisted_session_overlap');
});

it('does not create or link over matching manual sessions', function (): void {
    $fixture = lectureSessionGenerationFixture();

    $manualSession = LectureSession::query()->create([
        'subject_id' => $fixture['subject']->id,
        'subject_section_id' => $fixture['section']->id,
        'lecturer_id' => $fixture['lecturerUser']->id,
        'hall_id' => $fixture['hall']->id,
        'session_date' => '2026-09-07',
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'status' => 'scheduled',
        'attendance_mode' => 'qr_otp',
        'qr_refresh_rate' => 120,
    ]);

    $result = app(LectureSessionGenerationService::class)->generate($fixture['term'], $fixture['admin']);

    expect($result['created_session_count'])->toBe(2)
        ->and($result['skipped_session_count'])->toBe(1)
        ->and($manualSession->fresh()->subject_section_schedule_slot_id)->toBeNull()
        ->and(LectureSession::query()->count())->toBe(3);
});

it('excludes source slots that were removed from the weekly schedule reconciliation set', function (): void {
    $fixture = lectureSessionGenerationFixture();

    ScheduleImportRow::query()->create([
        'import_batch_id' => $fixture['scheduleBatch']->id,
        'academic_term_id' => $fixture['term']->id,
        'source_sheet_name' => 'Sheet1',
        'source_row_number' => 2,
        'row_fingerprint' => hash('sha256', 'excluded-slot-row'),
        'source_payload' => [],
        'normalized_payload' => [],
        'original_import_status' => ScheduleImportRow::ORIGINAL_IMPORTED,
        'current_reconciliation_status' => ScheduleImportRow::STATUS_EXCLUDED_FROM_BATCH_SCHEDULE,
        'import_result' => ['slot_ids' => [$fixture['slot']->id]],
        'excluded_from_weekly_schedule_at' => now(),
    ]);

    $preview = app(LectureSessionGenerationService::class)->preview($fixture['term']);

    expect($preview['ready'])->toBeFalse()
        ->and($preview['excluded_slot_count'])->toBe(1)
        ->and($preview['blocked_slots'][0]['reasons'])->toContain('excluded_from_weekly_schedule')
        ->and(LectureSession::query()->count())->toBe(0);
});
