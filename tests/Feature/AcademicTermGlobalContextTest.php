<?php

use App\Filament\Pages\ScheduleImportIssues;
use App\Filament\Pages\WeeklySchedule;
use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Resources\LectureSessions\Pages\ListLectureSessions;
use App\Filament\Resources\Students\Pages\CreateStudent;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Subjects\Pages\EditSubject;
use App\Filament\Resources\Subjects\Pages\ListSubjects;
use App\Filament\Resources\Subjects\RelationManagers\StudentsRelationManager;
use App\Filament\Resources\Subjects\RelationManagers\SubjectSectionsRelationManager;
use App\Http\Controllers\Manager\DashboardController as ManagerDashboardController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboardController;
use App\Models\AcademicTerm;
use App\Models\AppSetting;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\LectureSession;
use App\Models\LectureSessionGenerationRun;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Services\AcademicTermActivationService;
use App\Support\AcademicTermContext;
use App\Support\StudentAttendanceReport;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function globalContextAdmin(): User
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

/** @return array<string, mixed> */
function globalContextFixture(): array
{
    $summer = AcademicTerm::query()->create([
        'display_name' => 'Summer 2025/2026',
        'canonical_name' => 'summer-2025-2026',
        'is_archived' => false,
    ]);
    $first = AcademicTerm::query()->create([
        'display_name' => 'First Semester 2026/2027',
        'canonical_name' => 'first-2026-2027',
        'is_archived' => true,
    ]);
    AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, (string) $summer->id);

    $faculty = Faculty::query()->create(['name' => 'Context Faculty', 'is_active' => true]);
    $department = Department::query()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Context Department',
        'is_active' => true,
    ]);
    Role::findOrCreate('course_lecturer', 'web');
    $lecturer = User::factory()->create([
        'name' => 'Context Lecturer',
        'login_username' => 'context-lecturer',
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);
    $lecturer->assignRole('course_lecturer');
    $hall = Hall::query()->create(['code' => 'CTX-H', 'name' => 'Context Hall', 'is_active' => true]);

    $shared = Subject::query()->create([
        'department_id' => $department->id,
        'code' => 'CTX101',
        'name' => 'Shared Context Subject',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'is_active' => true,
    ]);
    $summerOnly = Subject::query()->create([
        'department_id' => $department->id,
        'code' => 'SUM101',
        'name' => 'Summer Only Subject',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'is_active' => true,
    ]);
    $firstOnly = Subject::query()->create([
        'department_id' => $department->id,
        'code' => 'FIR101',
        'name' => 'First Only Subject',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'is_active' => true,
    ]);

    $summerSection = SubjectSection::query()->create([
        'academic_term_id' => $summer->id,
        'subject_id' => $shared->id,
        'section_type' => Subject::TYPE_THEORETICAL,
        'code' => 'T1',
        'lecturer_id' => $lecturer->id,
    ]);
    $firstSection = SubjectSection::query()->create([
        'academic_term_id' => $first->id,
        'subject_id' => $shared->id,
        'section_type' => Subject::TYPE_THEORETICAL,
        'code' => 'T2',
        'lecturer_id' => $lecturer->id,
    ]);
    SubjectSection::query()->create([
        'academic_term_id' => $summer->id,
        'subject_id' => $summerOnly->id,
        'section_type' => Subject::TYPE_THEORETICAL,
        'code' => 'T1',
    ]);
    SubjectSection::query()->create([
        'academic_term_id' => $first->id,
        'subject_id' => $firstOnly->id,
        'section_type' => Subject::TYPE_THEORETICAL,
        'code' => 'T1',
    ]);

    $student = Student::query()->create([
        'name' => 'Context Student',
        'student_number' => 'CTX-STUDENT',
        'faculty_id' => $faculty->id,
        'department_id' => $department->id,
        'status' => 'active',
        'is_active' => true,
    ]);
    $summerEnrollment = Enrollment::query()->create([
        'academic_term_id' => $summer->id,
        'student_id' => $student->id,
        'subject_id' => $shared->id,
        'theoretical_section_id' => $summerSection->id,
        'year' => 1,
        'status' => Enrollment::STATUS_ENROLLED,
    ]);
    $firstEnrollment = Enrollment::query()->create([
        'academic_term_id' => $first->id,
        'student_id' => $student->id,
        'subject_id' => $shared->id,
        'theoretical_section_id' => $firstSection->id,
        'year' => 1,
        'status' => Enrollment::STATUS_ENROLLED,
    ]);

    $summerSession = LectureSession::query()->create([
        'academic_term_id' => $summer->id,
        'subject_id' => $shared->id,
        'subject_section_id' => $summerSection->id,
        'lecturer_id' => $lecturer->id,
        'hall_id' => $hall->id,
        'session_date' => today(),
        'start_time' => '22:00',
        'end_time' => '22:30',
        'status' => 'scheduled',
    ]);
    $firstSession = LectureSession::query()->create([
        'academic_term_id' => $first->id,
        'subject_id' => $shared->id,
        'subject_section_id' => $firstSection->id,
        'lecturer_id' => $lecturer->id,
        'hall_id' => $hall->id,
        'session_date' => today(),
        'start_time' => '23:00',
        'end_time' => '23:30',
        'status' => 'scheduled',
    ]);
    $summerAttendance = Attendance::query()->create([
        'lecture_session_id' => $summerSession->id,
        'student_id' => $student->id,
        'attendance_time' => now(),
        'attendance_method' => 'admin',
        'attendance_status' => 'present',
    ]);
    $firstAttendance = Attendance::query()->create([
        'lecture_session_id' => $firstSession->id,
        'student_id' => $student->id,
        'attendance_time' => now(),
        'attendance_method' => 'admin',
        'attendance_status' => 'present',
    ]);

    $summerBatch = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'context-summer-batch'),
        'import_type' => ImportBatch::TYPE_WEEKLY_SCHEDULE,
        'source_filename' => 'summer.xlsx',
        'status' => ImportBatch::STATUS_COMPLETED,
        'completed_at' => now()->subDay(),
    ]);
    $summerBatch->academicTerms()->attach($summer->id, ['row_count' => 1]);
    $firstBatch = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'context-first-batch'),
        'import_type' => ImportBatch::TYPE_WEEKLY_SCHEDULE,
        'source_filename' => 'first.xlsx',
        'status' => ImportBatch::STATUS_COMPLETED,
        'completed_at' => now(),
    ]);
    $firstBatch->academicTerms()->attach($first->id, ['row_count' => 1]);
    $summerSlot = SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $summerBatch->id,
        'academic_term_id' => $summer->id,
        'subject_id' => $shared->id,
        'subject_section_id' => $summerSection->id,
        'weekday' => 1,
        'start_time' => '08:00',
        'end_time' => '09:00',
    ]);
    $firstSlot = SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $firstBatch->id,
        'academic_term_id' => $first->id,
        'subject_id' => $shared->id,
        'subject_section_id' => $firstSection->id,
        'weekday' => 2,
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]);

    return compact(
        'summer', 'first', 'faculty', 'department', 'lecturer', 'hall',
        'shared', 'summerOnly', 'firstOnly', 'summerSection', 'firstSection',
        'student', 'summerEnrollment', 'firstEnrollment', 'summerSession',
        'firstSession', 'summerAttendance', 'firstAttendance', 'summerBatch',
        'firstBatch', 'summerSlot', 'firstSlot',
    );
}

it('switches daily subject section session and attendance views between two terms', function (): void {
    $fixture = globalContextFixture();
    $admin = globalContextAdmin();

    Livewire::actingAs($admin)
        ->test(ListSubjects::class)
        ->assertCanSeeTableRecords([$fixture['shared'], $fixture['summerOnly']])
        ->assertCanNotSeeTableRecords([$fixture['firstOnly']]);

    Livewire::actingAs($admin)
        ->test(SubjectSectionsRelationManager::class, [
            'ownerRecord' => $fixture['shared'],
            'pageClass' => EditSubject::class,
        ])
        ->assertCanSeeTableRecords([$fixture['summerSection']])
        ->assertCanNotSeeTableRecords([$fixture['firstSection']]);

    Livewire::actingAs($admin)
        ->test(ListLectureSessions::class)
        ->assertCanSeeTableRecords([$fixture['summerSession']])
        ->assertCanNotSeeTableRecords([$fixture['firstSession']]);

    $this->actingAs($admin);
    expect(AttendanceResource::getEloquentQuery()->pluck('attendances.id')->all())
        ->toBe([$fixture['summerAttendance']->id]);

    AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, (string) $fixture['first']->id);

    Livewire::actingAs($admin)
        ->test(ListSubjects::class)
        ->assertCanSeeTableRecords([$fixture['shared'], $fixture['firstOnly']])
        ->assertCanNotSeeTableRecords([$fixture['summerOnly']]);

    expect(SubjectSectionsRelationManager::getBadge($fixture['shared'], EditSubject::class))->toBe('1')
        ->and(StudentsRelationManager::getBadge($fixture['shared'], EditSubject::class))->toBe('1');
});

it('syncs student subjects only inside the current term and creates term-bound enrollments', function (): void {
    $fixture = globalContextFixture();
    $admin = globalContextAdmin();
    AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, (string) $fixture['first']->id);

    Livewire::actingAs($admin)
        ->test(EditStudent::class, ['record' => $fixture['student']->getRouteKey()])
        ->fillForm([
            'name' => $fixture['student']->name,
            'student_number' => $fixture['student']->student_number,
            'faculty_id' => $fixture['faculty']->id,
            'department_id' => $fixture['department']->id,
            'subject_ids' => [$fixture['firstOnly']->id],
            'is_active' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Enrollment::query()->whereKey($fixture['summerEnrollment']->id)->exists())->toBeTrue()
        ->and(Enrollment::query()->whereKey($fixture['firstEnrollment']->id)->exists())->toBeFalse()
        ->and(Enrollment::query()
            ->where('academic_term_id', $fixture['first']->id)
            ->where('student_id', $fixture['student']->id)
            ->where('subject_id', $fixture['firstOnly']->id)
            ->exists())->toBeTrue();

    Livewire::actingAs($admin)
        ->test(CreateStudent::class)
        ->fillForm([
            'name' => 'New Context Student',
            'student_number' => 'CTX-NEW',
            'faculty_id' => $fixture['faculty']->id,
            'department_id' => $fixture['department']->id,
            'subject_ids' => [$fixture['firstOnly']->id],
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Enrollment::query()
        ->where('academic_term_id', $fixture['first']->id)
        ->whereHas('student', fn ($query) => $query->where('student_number', 'CTX-NEW'))
        ->where('subject_id', $fixture['firstOnly']->id)
        ->exists())->toBeTrue();
});

it('creates subject sections in the current term without exposing a term choice', function (): void {
    $fixture = globalContextFixture();
    $admin = globalContextAdmin();
    AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, (string) $fixture['first']->id);

    Livewire::actingAs($admin)
        ->test(SubjectSectionsRelationManager::class, [
            'ownerRecord' => $fixture['shared'],
            'pageClass' => EditSubject::class,
        ])
        ->assertTableActionEnabled('create')
        ->callTableAction('create', data: [
            'section_type' => Subject::TYPE_THEORETICAL,
            'code' => 'T3',
        ])
        ->assertHasNoTableActionErrors();

    expect(SubjectSection::query()
        ->where('academic_term_id', $fixture['first']->id)
        ->where('subject_id', $fixture['shared']->id)
        ->pluck('code')
        ->all())->toContain('T3');
});

it('keeps student attendance and historical reports isolated by academic term', function (): void {
    $fixture = globalContextFixture();
    $admin = globalContextAdmin();
    $report = app(StudentAttendanceReport::class);

    expect($report->rows($fixture['student'], academicTermId: $fixture['summer']->id)->pluck('id')->all())
        ->toBe([$fixture['summerSession']->id])
        ->and($report->rows($fixture['student'], academicTermId: $fixture['first']->id)->pluck('id')->all())
        ->toBe([$fixture['firstSession']->id]);

    Livewire::actingAs($admin)
        ->test(ViewStudent::class, ['record' => $fixture['student']->getRouteKey()])
        ->assertCanSeeTableRecords([$fixture['summerAttendance']])
        ->assertCanNotSeeTableRecords([$fixture['firstAttendance']]);
});

it('uses current term for schedule navigation and explicit batch term when supplied', function (): void {
    $fixture = globalContextFixture();
    $admin = globalContextAdmin();
    AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, (string) $fixture['first']->id);

    Livewire::actingAs($admin)
        ->test(WeeklySchedule::class)
        ->assertSet('resolvedAcademicTermId', $fixture['first']->id)
        ->assertCanSeeTableRecords([$fixture['firstSlot']])
        ->assertCanNotSeeTableRecords([$fixture['summerSlot']]);

    Livewire::withQueryParams(['batch' => $fixture['summerBatch']->uuid])
        ->actingAs($admin)
        ->test(WeeklySchedule::class)
        ->assertSet('resolvedAcademicTermId', $fixture['summer']->id)
        ->assertCanSeeTableRecords([$fixture['summerSlot']])
        ->assertCanNotSeeTableRecords([$fixture['firstSlot']]);

    Livewire::withQueryParams(['batch' => $fixture['summerBatch']->id])
        ->actingAs($admin)
        ->test(ScheduleImportIssues::class)
        ->assertSet('academicTermId', $fixture['summer']->id)
        ->assertSet('importBatchId', $fixture['summerBatch']->id);
});

it('keeps run and historical data unchanged across summer first summer switching', function (): void {
    $fixture = globalContextFixture();
    $admin = globalContextAdmin();
    $run = LectureSessionGenerationRun::query()->create([
        'academic_term_id' => $fixture['summer']->id,
        'teaching_start_date' => today(),
        'teaching_end_date' => today()->addWeek(),
        'status' => 'completed',
        'started_at' => now(),
        'completed_at' => now(),
    ]);
    $before = [
        'enrollments' => Enrollment::query()->count(),
        'sections' => SubjectSection::query()->count(),
        'sessions' => LectureSession::query()->count(),
        'attendances' => Attendance::query()->count(),
        'slots' => SubjectSectionScheduleSlot::query()->count(),
    ];

    app(AcademicTermActivationService::class)->activate($fixture['first'], $admin);
    expect(app(AcademicTermContext::class)->currentId())->toBe($fixture['first']->id);

    app(AcademicTermActivationService::class)->activate($fixture['summer'], $admin);

    expect(app(AcademicTermContext::class)->currentId())->toBe($fixture['summer']->id)
        ->and($fixture['summer']->fresh()->is_archived)->toBeFalse()
        ->and($fixture['first']->fresh()->is_archived)->toBeTrue()
        ->and($run->fresh()->academic_term_id)->toBe($fixture['summer']->id)
        ->and([
            'enrollments' => Enrollment::query()->count(),
            'sections' => SubjectSection::query()->count(),
            'sessions' => LectureSession::query()->count(),
            'attendances' => Attendance::query()->count(),
            'slots' => SubjectSectionScheduleSlot::query()->count(),
        ])->toBe($before);
});

it('scopes teacher and manager dashboard academic counters to the current term', function (): void {
    $fixture = globalContextFixture();
    $otherLecturer = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);
    $otherLecturer->assignRole('course_lecturer');

    $this->actingAs($fixture['lecturer']);
    $teacherData = app(TeacherDashboardController::class)->index()->getData();

    expect($teacherData['sessionsCount'])->toBe(1)
        ->and($teacherData['todayAttendance'])->toBe(1);

    $this->actingAs(globalContextAdmin());
    $managerData = app(ManagerDashboardController::class)->index()->getData();

    expect($managerData['todayAttendance'])->toBe(1);
});

it('renders a translated current term indicator and a safe missing-term state', function (): void {
    $fixture = globalContextFixture();

    app()->setLocale('en');
    expect(view('filament.components.current-academic-term-indicator', [
        'academicTerm' => $fixture['summer'],
        'url' => '/admin/current-academic-term',
    ])->render())->toContain('Current Academic Term')->toContain('Summer 2025/2026');

    AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, null);

    expect(app(AcademicTermContext::class)->hasCurrent())->toBeFalse()
        ->and(view('filament.components.current-academic-term-indicator', [
            'academicTerm' => null,
            'url' => '/admin/current-academic-term',
        ])->render())->toContain('No current academic term');
});
