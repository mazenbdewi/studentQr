<?php

use App\Exports\BlockedWeeklySlotsExport;
use App\Filament\Pages\BlockedWeeklySlots;
use App\Filament\Resources\LectureSessions\LectureSessionResource;
use App\Filament\Resources\LectureSessions\Pages\ListLectureSessions;
use App\Models\AcademicTerm;
use App\Models\Attendance;
use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\Lecturer;
use App\Models\LectureSession;
use App\Models\LectureSessionGenerationRun;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Services\BlockedWeeklySlotReportService;
use App\Services\LectureSessionGenerationService;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Carbon::setTestNow('2026-07-22 10:00:00');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'course_lecturer', 'guard_name' => 'web']);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function postGenerationAdmin(): User
{
    $user = User::factory()->create([
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);
    $user->assignRole('super-admin');

    return $user;
}

function postGenerationLecturer(string $name): User
{
    $user = User::factory()->create([
        'name' => $name,
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);
    $user->assignRole('course_lecturer');

    return $user;
}

function postGenerationSubject(User $lecturer, string $code = 'PG-101'): Subject
{
    $code = Subject::query()->where('code', $code)->exists()
        ? $code.'-'.(Subject::query()->count() + 1)
        : $code;

    return Subject::query()->create([
        'code' => $code,
        'name' => "مادة {$code}",
        'subject_type' => Subject::TYPE_THEORETICAL,
        'lecturer_id' => $lecturer->id,
        'is_active' => true,
    ]);
}

function postGenerationSection(AcademicTerm $term, Subject $subject, User $lecturer, string $code = 'T1'): SubjectSection
{
    return SubjectSection::query()->create([
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'lecturer_id' => $lecturer->id,
        'section_type' => Subject::TYPE_THEORETICAL,
        'code' => $code,
        'name' => $code,
        'capacity' => 40,
    ]);
}

function postGenerationHall(string $code = 'H-1'): Hall
{
    $code = Hall::query()->where('code', $code)->exists()
        ? $code.'-'.(Hall::query()->count() + 1)
        : $code;

    return Hall::query()->create([
        'code' => $code,
        'name' => "قاعة {$code}",
        'floor' => 1,
        'is_active' => true,
    ]);
}

function postGenerationTerm(): AcademicTerm
{
    return AcademicTerm::query()->create([
        'display_name' => 'الفصل الصيفي 2025/2026',
        'canonical_name' => 'summer-2025-2026',
        'teaching_start_date' => '2026-07-22',
        'teaching_end_date' => '2026-08-31',
    ]);
}

function postGenerationBatch(AcademicTerm $term): ImportBatch
{
    $batch = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'post-generation-schedule-'.$term->id),
        'import_type' => ImportBatch::TYPE_WEEKLY_SCHEDULE,
        'status' => ImportBatch::STATUS_COMPLETED_WITH_ERRORS,
        'imported_rows' => 1,
        'total_rows' => 1,
        'completed_at' => now(),
    ]);
    $batch->academicTerms()->attach($term->id, ['row_count' => 1]);

    return $batch;
}

function postGenerationSession(User $lecturer, array $overrides = []): LectureSession
{
    $term = $overrides['term'] ?? postGenerationTerm();
    $subject = $overrides['subject'] ?? postGenerationSubject($lecturer);
    $section = $overrides['section'] ?? postGenerationSection($term, $subject, $lecturer);
    $hall = $overrides['hall'] ?? postGenerationHall();

    return LectureSession::query()->create([
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'subject_section_id' => $section->id,
        'lecturer_id' => $lecturer->id,
        'hall_id' => $hall->id,
        'session_date' => '2026-07-23',
        'start_time' => '11:00:00',
        'end_time' => '12:00:00',
        'status' => 'scheduled',
        'attendance_mode' => 'qr_otp',
        'qr_refresh_rate' => 120,
        ...collect($overrides)->except(['term', 'subject', 'section', 'hall'])->all(),
    ]);
}

function postGenerationStudent(string $number): Student
{
    return Student::query()->create([
        'name' => "طالب {$number}",
        'student_number' => $number,
        'status' => 'active',
        'is_active' => true,
    ]);
}

it('displays the linked lecturer user name and filters by lecturer user id', function (): void {
    $admin = postGenerationAdmin();
    $lecturer = postGenerationLecturer('محمد ابراهيم علي');
    $otherLecturer = postGenerationLecturer('أحمد سالم محمود');
    $session = postGenerationSession($lecturer);
    $otherSession = postGenerationSession($otherLecturer, [
        'term' => $session->academicTerm,
        'session_date' => '2026-07-24',
    ]);

    Livewire\Livewire::actingAs($admin)
        ->test(ListLectureSessions::class)
        ->set('activeTab', 'upcoming')
        ->assertSee('محمد ابراهيم علي')
        ->filterTable('lecturer', $lecturer->id)
        ->assertCanSeeTableRecords([$session])
        ->assertCanNotSeeTableRecords([$otherSession])
        ->assertSee('محمد ابراهيم علي')
        ->assertDontSee('أحمد سالم محمود');
});

it('uses the lecture session lecturer relation to users, not lecturer identities', function (): void {
    $relation = (new LectureSession)->lecturer();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(User::class)
        ->and($relation->getForeignKeyName())->toBe('lecturer_id');
});

it('loads actual attendance counts as a distinct aggregate without per-row queries', function (): void {
    $admin = postGenerationAdmin();
    $lecturer = postGenerationLecturer('ليلى حسن');
    $session = postGenerationSession($lecturer);
    $emptySession = postGenerationSession($lecturer, [
        'term' => $session->academicTerm,
        'subject' => $session->subject,
        'section' => $session->subjectSection,
        'hall' => $session->hall,
        'session_date' => '2026-07-24',
    ]);
    $studentOne = postGenerationStudent('S-1');
    $studentTwo = postGenerationStudent('S-2');

    Attendance::query()->create([
        'lecture_session_id' => $session->id,
        'student_id' => $studentOne->id,
        'attendance_time' => now(),
        'attendance_method' => 'qr_scan',
        'attendance_status' => 'present',
    ]);
    Attendance::query()->create([
        'lecture_session_id' => $session->id,
        'student_id' => $studentTwo->id,
        'attendance_time' => now(),
        'attendance_method' => 'manual',
        'attendance_status' => 'present',
    ]);

    $this->actingAs($admin);

    $records = LectureSessionResource::getEloquentQuery()
        ->whereKey([$session->id, $emptySession->id])
        ->orderBy('id')
        ->get();

    expect($records->firstWhere('id', $session->id)->actual_attendance_count)->toBe(2)
        ->and($records->firstWhere('id', $emptySession->id)->actual_attendance_count)->toBe(0);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $records->each(fn (LectureSession $record): int => (int) $record->actual_attendance_count);

    expect(DB::getQueryLog())->toBe([]);

    DB::disableQueryLog();
});

it('groups blocked report rows by unique source slot and keeps error-row count distinct', function (): void {
    [$run] = postGenerationBlockedReportFixture();

    $report = app(BlockedWeeklySlotReportService::class)->reportForRun($run);

    expect($report['summary']['error_report_rows'])->toBe(3)
        ->and($report['summary']['unique_affected_slots'])->toBe(2)
        ->and($report['summary']['multi_issue'])->toBe(1)
        ->and($report['rows'])->toHaveCount(2)
        ->and($report['rows'][0]['المشكلات'])->toContain('لم يتم تحديد المدرس')
        ->and($report['rows'][0]['المشكلات'])->toContain('لم يتم تحديد القاعة');
});

it('shows both sides of conflict details and conflict dimensions', function (): void {
    [$run] = postGenerationBlockedReportFixture(withConflict: true);

    $report = app(BlockedWeeklySlotReportService::class)->reportForRun($run);
    $conflict = $report['conflicts'][0];

    expect($conflict['source_slot_id'])->toBe(3)
        ->and($conflict['conflicting_source_slot_id'])->toBe(4)
        ->and($conflict['source_subject_section'])->toContain('مادة CON-A')
        ->and($conflict['conflicting_subject_section'])->toContain('مادة CON-B')
        ->and($conflict['conflict_dimension'])->toContain('same hall')
        ->and($conflict['actual_overlap_interval'])->toBe('09:30 - 10:00');
});

it('exports blocked slot report as xlsx with Arabic RTL worksheets', function (): void {
    [$run] = postGenerationBlockedReportFixture(withConflict: true);
    $report = app(BlockedWeeklySlotReportService::class)->reportForRun($run);

    $bytes = Excel::raw(
        new BlockedWeeklySlotsExport($report['rows'], $report['conflicts']),
        ExcelWriter::XLSX,
    );
    $spreadsheet = spreadsheetFromXlsxBytes($bytes);

    expect($bytes)->not->toBe('')
        ->and($spreadsheet->getSheetByName('الخانات المحجوبة'))->not->toBeNull()
        ->and($spreadsheet->getSheetByName('تفاصيل التعارضات'))->not->toBeNull()
        ->and($spreadsheet->getSheetByName('الخانات المحجوبة')->getRightToLeft())->toBeTrue()
        ->and($spreadsheet->getSheetByName('تفاصيل التعارضات')->getRightToLeft())->toBeTrue()
        ->and(spreadsheetCellValues($spreadsheet))->toContain('رقم الموعد الأسبوعي', 'المشكلات', 'الموعد المصدر');
});

it('downloads the blocked slot workbook with xlsx filename and excel mime type', function (): void {
    postGenerationAdmin();
    postGenerationBlockedReportFixture(withConflict: true);

    $response = (new BlockedWeeklySlots)->downloadBlockedWeeklySlots();

    expect($response->headers->get('content-disposition'))->toContain('.xlsx')
        ->and($response->headers->get('content-type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('keeps generation preview read-only', function (): void {
    $admin = postGenerationAdmin();
    $lecturer = postGenerationLecturer('سعاد أحمد');
    $term = postGenerationTerm();
    $batch = postGenerationBatch($term);
    $subject = postGenerationSubject($lecturer, 'PREV-101');
    $section = postGenerationSection($term, $subject, $lecturer);
    $hall = postGenerationHall('PREV-H');
    $identity = Lecturer::query()->create([
        'user_id' => $lecturer->id,
        'name' => $lecturer->name,
        'canonical_name' => 'preview lecturer',
        'is_active' => true,
    ]);

    SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $batch->id,
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'subject_section_id' => $section->id,
        'lecturer_id' => $identity->id,
        'hall_id' => $hall->id,
        'weekday' => 3,
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'section_capacity' => 40,
        'expected_student_count' => 30,
    ]);

    $sessionsBefore = LectureSession::query()->count();
    $slotsBefore = SubjectSectionScheduleSlot::query()->count();
    $usersBefore = User::query()->count();

    $preview = app(LectureSessionGenerationService::class)->preview($term);

    expect($preview['safe_to_create_count'])->toBeGreaterThan(0)
        ->and(LectureSession::query()->count())->toBe($sessionsBefore)
        ->and(SubjectSectionScheduleSlot::query()->count())->toBe($slotsBefore)
        ->and(User::query()->count())->toBe($usersBefore);
});

function postGenerationBlockedReportFixture(bool $withConflict = false): array
{
    $admin = postGenerationAdmin();
    $lecturer = postGenerationLecturer('مدرس التقرير');
    $otherLecturer = postGenerationLecturer('مدرس التعارض');
    $term = postGenerationTerm();
    $batch = postGenerationBatch($term);
    $subject = postGenerationSubject($lecturer, 'BLK-A');
    $section = postGenerationSection($term, $subject, $lecturer, 'T1');
    $hall = postGenerationHall('BLK-H');

    $missingBoth = SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $batch->id,
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'subject_section_id' => $section->id,
        'lecturer_id' => null,
        'hall_id' => null,
        'weekday' => 3,
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
    ]);
    $reportIdentity = Lecturer::query()->create([
        'user_id' => $lecturer->id,
        'name' => $lecturer->name,
        'canonical_name' => 'report lecturer',
        'is_active' => true,
    ]);
    $missingHall = SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $batch->id,
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'subject_section_id' => $section->id,
        'lecturer_id' => $reportIdentity->id,
        'hall_id' => null,
        'weekday' => 4,
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
    ]);

    $errorReport = [
        postGenerationErrorRow($missingBoth, 'missing_lecturer_identity'),
        postGenerationErrorRow($missingBoth, 'missing_hall'),
        postGenerationErrorRow($missingHall, 'missing_hall'),
    ];
    $blockedSlots = [
        ['source_slot_id' => $missingBoth->id, 'reasons' => ['missing_lecturer_identity', 'missing_hall'], 'occurrence_count' => 6],
        ['source_slot_id' => $missingHall->id, 'reasons' => ['missing_hall'], 'occurrence_count' => 6],
    ];
    $conflicts = [];

    if ($withConflict) {
        $subjectA = postGenerationSubject($lecturer, 'CON-A');
        $subjectB = postGenerationSubject($otherLecturer, 'CON-B');
        $sectionA = postGenerationSection($term, $subjectA, $lecturer, 'P1');
        $sectionB = postGenerationSection($term, $subjectB, $otherLecturer, 'P2');
        $identityA = $reportIdentity;
        $identityB = Lecturer::query()->create([
            'user_id' => $otherLecturer->id,
            'name' => $otherLecturer->name,
            'canonical_name' => 'conflict lecturer b',
            'is_active' => true,
        ]);
        $slotA = SubjectSectionScheduleSlot::query()->create([
            'import_batch_id' => $batch->id,
            'academic_term_id' => $term->id,
            'subject_id' => $subjectA->id,
            'subject_section_id' => $sectionA->id,
            'lecturer_id' => $identityA->id,
            'hall_id' => $hall->id,
            'weekday' => 6,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);
        $slotB = SubjectSectionScheduleSlot::query()->create([
            'import_batch_id' => $batch->id,
            'academic_term_id' => $term->id,
            'subject_id' => $subjectB->id,
            'subject_section_id' => $sectionB->id,
            'lecturer_id' => $identityB->id,
            'hall_id' => $hall->id,
            'weekday' => 6,
            'start_time' => '09:30:00',
            'end_time' => '10:30:00',
        ]);
        $errorReport[] = postGenerationErrorRow($slotA, 'weekly_schedule_overlap');
        $errorReport[] = postGenerationErrorRow($slotA, 'scheduling_conflict');
        $errorReport[] = postGenerationErrorRow($slotB, 'scheduling_conflict');
        $conflicts[] = [
            'reason' => 'weekly_schedule_overlap',
            'source_slot_id' => $slotA->id,
            'conflicting_source_slot_id' => $slotB->id,
            'session_date' => '2026-07-25',
            'weekday' => 6,
            'start_time' => '09:30:00',
            'end_time' => '10:00:00',
        ];
    }

    $run = LectureSessionGenerationRun::query()->create([
        'academic_term_id' => $term->id,
        'schedule_import_batch_id' => $batch->id,
        'started_by' => $admin->id,
        'teaching_start_date' => '2026-07-22',
        'teaching_end_date' => '2026-08-31',
        'status' => 'completed_with_errors',
        'source_slot_count' => SubjectSectionScheduleSlot::query()->count(),
        'candidate_session_count' => 0,
        'created_session_count' => 0,
        'skipped_session_count' => 0,
        'blocked_slot_count' => count($blockedSlots),
        'conflict_count' => count($conflicts),
        'summary' => [
            'error_report' => $errorReport,
            'blocked_slots' => $blockedSlots,
            'conflicts' => $conflicts,
        ],
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    return [$run];
}

function postGenerationErrorRow(SubjectSectionScheduleSlot $slot, string $code): array
{
    return [
        'الموعد الأسبوعي المصدر' => $slot->id,
        'المادة' => $slot->subject?->name,
        'الشعبة' => $slot->subjectSection?->code,
        'المدرس' => $slot->lecturer?->name,
        'القاعة' => $slot->hall?->name,
        'اليوم' => __('weekly-schedule.weekdays')[$slot->weekday],
        'الوقت' => "{$slot->start_time} - {$slot->end_time}",
        'رمز الخطأ' => $code,
        'السبب بالعربية' => $code,
        'الإجراء المقترح' => "إجراء {$code}",
    ];
}
