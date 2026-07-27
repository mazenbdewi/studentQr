<?php

use App\Imports\SubjectsImport;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\Hall;
use App\Models\LectureSession;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('creates a theoretical subject with T sections and suggests the next code', function (): void {
    $subject = subjectTypeSectionsSubject(Subject::TYPE_THEORETICAL, 'THEORY-101');

    $subject->sections()->create(['code' => 'T1']);
    $subject->sections()->create(['code' => 't2']);

    expect($subject->sections()->pluck('code')->all())->toBe(['T1', 'T2'])
        ->and(SubjectSection::nextCodeForSubject($subject->refresh()))->toBe('T3');
});

it('creates a practical subject with P sections and suggests the next code', function (): void {
    $subject = subjectTypeSectionsSubject(Subject::TYPE_PRACTICAL, 'PRACTICAL-101');

    $subject->sections()->create(['code' => 'P1']);

    expect($subject->sections()->pluck('code')->all())->toBe(['P1'])
        ->and(SubjectSection::nextCodeForSubject($subject->refresh()))->toBe('P2');
});

it('allows practical sections on a subject whose legacy type is theoretical', function (): void {
    $subject = subjectTypeSectionsSubject(Subject::TYPE_THEORETICAL, 'THEORY-102');

    $section = $subject->sections()->create(['code' => 'P1']);

    expect($section->section_type)->toBe(Subject::TYPE_PRACTICAL)
        ->and($section->code)->toBe('P1');
});

it('allows theoretical sections on a subject whose legacy type is practical', function (): void {
    $subject = subjectTypeSectionsSubject(Subject::TYPE_PRACTICAL, 'PRACTICAL-102');

    $section = $subject->sections()->create(['code' => 'T1']);

    expect($section->section_type)->toBe(Subject::TYPE_THEORETICAL)
        ->and($section->code)->toBe('T1');
});

it('requires subject type outside the UI', function (): void {
    expect(fn () => Subject::query()->create([
        'code' => 'NO-TYPE-101',
        'name' => 'No Type Subject',
        'is_active' => true,
    ]))->toThrow(ValidationException::class);
});

it('accepts mixed section codes during subjects Excel import', function (): void {
    $path = subjectTypeSectionsWorkbookPath([
        [
            'college' => 'Faculty',
            'department' => 'Department',
            'subject_code' => 'IMPORT-101',
            'subject_name' => 'Imported Theory',
            'subject_type' => 'theoretical',
            'sections' => 'T1,P1',
            'lecturer_name' => null,
            'year' => 1,
            'is_active' => 'true',
        ],
    ]);

    try {
        Excel::import(new SubjectsImport, $path);
    } finally {
        @unlink($path);
    }

    $subject = Subject::query()->where('code', 'IMPORT-101')->firstOrFail();

    expect($subject->sections()->orderBy('code')->pluck('section_type', 'code')->all())->toBe([
        'P1' => Subject::TYPE_PRACTICAL,
        'T1' => Subject::TYPE_THEORETICAL,
    ]);
});

it('renders subject type and section code in attendance reports', function (): void {
    $fixture = subjectTypeSectionsAttendanceFixture();

    $html = view('exports.attendance', [
        'records' => Attendance::query()
            ->with(['student', 'lectureSession.subject', 'lectureSession.subjectSection', 'lectureSession.hall'])
            ->whereKey($fixture['attendance']->id)
            ->get(),
    ])->render();

    expect($html)->toContain('REPORT-101')
        ->toContain('Report Subject')
        ->toContain(__('subjects.theory'))
        ->toContain('T1')
        ->toContain('Report Hall')
        ->toContain('Report Student');
});

function subjectTypeSectionsSubject(string $subjectType, string $code): Subject
{
    return Subject::query()->create([
        'code' => $code,
        'name' => $code,
        'subject_type' => $subjectType,
        'is_active' => true,
    ]);
}

function subjectTypeSectionsWorkbookPath(array $rows): string
{
    subjectTypeSectionsDepartmentFixture();

    $headings = [
        'college',
        'department',
        'subject_code',
        'subject_name',
        'subject_type',
        'sections',
        'lecturer_name',
        'year',
        'is_active',
    ];

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray($headings, null, 'A1');

    foreach (array_values($rows) as $index => $row) {
        $sheet->fromArray(array_map(fn (string $heading): mixed => $row[$heading] ?? null, $headings), null, 'A'.($index + 2));
    }

    $path = sys_get_temp_dir().'/subjects_import_'.Str::uuid().'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

function subjectTypeSectionsDepartmentFixture(): void
{
    $faculty = Faculty::query()->firstOrCreate(
        ['name' => 'Faculty'],
        ['name_en' => 'Faculty', 'is_active' => true],
    );

    $faculty->departments()->firstOrCreate(
        ['name' => 'Department'],
        ['name_en' => 'Department', 'code' => 'DEPT', 'is_active' => true],
    );
}

function subjectTypeSectionsAttendanceFixture(): array
{
    $faculty = Faculty::query()->create([
        'name' => 'Report Faculty',
        'name_en' => 'Report Faculty',
        'is_active' => true,
    ]);

    $department = $faculty->departments()->create([
        'name' => 'Report Department',
        'name_en' => 'Report Department',
        'code' => 'RPT',
        'is_active' => true,
    ]);

    $lecturer = User::query()->create([
        'name' => 'Report Lecturer',
        'login_username' => 'report_lecturer',
        'password' => 'password',
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'faculty_id' => $faculty->id,
        'department_id' => $department->id,
        'title' => 'lecturer',
        'is_active' => true,
    ]);

    $subject = Subject::query()->create([
        'code' => 'REPORT-101',
        'name' => 'Report Subject',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'lecturer_id' => $lecturer->id,
        'department_id' => $department->id,
        'is_active' => true,
    ]);

    $section = $subject->sections()->create(['code' => 'T1']);

    $hall = Hall::query()->create([
        'code' => 'R-H1',
        'name' => 'Report Hall',
        'floor' => 1,
        'is_active' => true,
    ]);

    $student = Student::query()->create([
        'name' => 'Report Student',
        'faculty_id' => $faculty->id,
        'department_id' => $department->id,
        'year' => 1,
        'status' => 'active',
        'student_number' => 'R2026001',
        'national_number' => '98765432101',
        'is_active' => true,
    ]);

    Enrollment::query()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'semester' => Subject::SEMESTER_FIRST,
        'year' => 1,
        'status' => Enrollment::STATUS_ENROLLED,
    ]);

    $session = LectureSession::query()->create([
        'subject_id' => $subject->id,
        'subject_section_id' => $section->id,
        'lecturer_id' => $lecturer->id,
        'hall_id' => $hall->id,
        'session_date' => '2026-05-16',
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'status' => 'completed',
        'attendance_mode' => 'qr_otp',
    ]);

    $attendance = Attendance::query()->create([
        'lecture_session_id' => $session->id,
        'student_id' => $student->id,
        'attendance_time' => '2026-05-16 08:05:00',
        'attendance_method' => 'admin',
        'attendance_status' => 'present',
    ]);

    return [
        'attendance' => $attendance,
        'section' => $section,
        'session' => $session,
        'subject' => $subject,
    ];
}
