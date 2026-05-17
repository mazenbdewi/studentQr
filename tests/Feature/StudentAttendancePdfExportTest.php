<?php

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Hall;
use App\Models\LectureSession;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\StudentAttendancePdfExporter;
use App\Support\StudentAttendanceReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

it('builds student attendance rows and summary for present and absent lectures', function () {
    ['student' => $student, 'subjects' => $subjects, 'sessions' => $sessions] = createStudentAttendanceFixture();

    $rows = app(StudentAttendanceReport::class)->rows($student);
    $summary = app(StudentAttendanceReport::class)->summaryFromRows($rows);

    expect($rows)->toHaveCount(4)
        ->and($rows->pluck('id')->all())->toBe([
            $sessions['missing']->id,
            $sessions['present']->id,
            $sessions['late']->id,
            $sessions['secondary_present']->id,
        ])
        ->and($rows->pluck('report_status')->all())->toBe([
            'absent',
            'present',
            'present',
            'present',
        ])
        ->and($rows->pluck('subject_id')->all())->toBe([
            $subjects['primary']->id,
            $subjects['primary']->id,
            $subjects['primary']->id,
            $subjects['secondary']->id,
        ])
        ->and($summary['total_lectures'])->toBe(4)
        ->and($summary['total_present'])->toBe(3)
        ->and($summary['total_absent'])->toBe(1)
        ->and($summary['attendance_percentage'])->toBe(75.0);
});

it('filters student attendance rows and summary to the selected enrolled subject only', function () {
    ['student' => $student, 'subjects' => $subjects, 'sessions' => $sessions] = createStudentAttendanceFixture();

    $rows = app(StudentAttendanceReport::class)->rows($student, $subjects['primary']->id);
    $summary = app(StudentAttendanceReport::class)->summaryFromRows($rows);

    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('id')->all())->toBe([
            $sessions['missing']->id,
            $sessions['present']->id,
            $sessions['late']->id,
        ])
        ->and($rows->pluck('subject_id')->unique()->all())->toBe([$subjects['primary']->id])
        ->and($summary['total_lectures'])->toBe(3)
        ->and($summary['total_present'])->toBe(2)
        ->and($summary['total_absent'])->toBe(1)
        ->and($summary['attendance_percentage'])->toBe(66.7);
});

it('calculates student attendance from the enrollment registration date', function () {
    ['student' => $student, 'subjects' => $subjects, 'sessions' => $sessions] = createStudentAttendanceFixture();

    DB::table('enrollments')
        ->where('student_id', $student->id)
        ->where('subject_id', $subjects['primary']->id)
        ->update(['registration_date' => '2026-04-15']);

    $rows = app(StudentAttendanceReport::class)->rows($student, $subjects['primary']->id);
    $summary = app(StudentAttendanceReport::class)->summaryFromRows($rows);

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('id')->all())->toBe([
            $sessions['missing']->id,
            $sessions['present']->id,
        ])
        ->and($rows->pluck('id')->contains($sessions['late']->id))->toBeFalse()
        ->and($summary['total_lectures'])->toBe(2)
        ->and($summary['total_present'])->toBe(1)
        ->and($summary['total_absent'])->toBe(1)
        ->and($summary['attendance_percentage'])->toBe(50.0);
});

it('counts only lecture sessions for the sections assigned to the student enrollment', function () {
    ['student' => $student, 'subjects' => $subjects] = createStudentAttendanceFixture();

    $subject = $subjects['primary'];
    $theorySection = $subject->sections()->create([
        'code' => 'T1',
        'section_type' => Subject::TYPE_THEORETICAL,
    ]);
    $otherTheorySection = $subject->sections()->create([
        'code' => 'T2',
        'section_type' => Subject::TYPE_THEORETICAL,
    ]);

    DB::table('enrollments')
        ->where('student_id', $student->id)
        ->where('subject_id', $subject->id)
        ->update([
            'theoretical_section_id' => $theorySection->id,
            'registration_date' => '2026-04-01',
        ]);

    $hall = Hall::query()->firstOrFail();
    $lecturerId = $subject->lecturer_id;

    $assignedSession = LectureSession::query()->create([
        'subject_id' => $subject->id,
        'subject_section_id' => $theorySection->id,
        'lecturer_id' => $lecturerId,
        'hall_id' => $hall->id,
        'session_date' => '2026-04-17',
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'status' => 'completed',
        'attendance_mode' => 'qr_otp',
    ]);

    $otherSectionSession = LectureSession::query()->create([
        'subject_id' => $subject->id,
        'subject_section_id' => $otherTheorySection->id,
        'lecturer_id' => $lecturerId,
        'hall_id' => $hall->id,
        'session_date' => '2026-04-18',
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'status' => 'completed',
        'attendance_mode' => 'qr_otp',
    ]);

    $rows = app(StudentAttendanceReport::class)->rows($student, $subject->id);

    expect($rows->pluck('id')->contains($assignedSession->id))->toBeTrue()
        ->and($rows->pluck('id')->contains($otherSectionSession->id))->toBeFalse();
});

it('renders the attendance pdf template with rtl layout and the uploaded university logo', function () {
    ['student' => $student, 'subjects' => $subjects] = createStudentAttendanceFixture();

    app()->setLocale('ar');

    $rows = app(StudentAttendanceReport::class)->rows($student, $subjects['primary']->id);
    $logoPath = public_path('images/logo.png');

    expect($logoPath)->toBeFile();

    $html = view('exports.student-attendance-report-pdf', [
        'student' => $student->loadMissing(['department', 'faculty']),
        'selectedSubject' => $subjects['primary'],
        'rows' => $rows,
        'summary' => app(StudentAttendanceReport::class)->summaryFromRows($rows),
        'subjectLabels' => [$subjects['primary']->name],
        'generatedAt' => Carbon::parse('2026-04-17 10:00:00'),
        'isRtl' => true,
        'logoDataUri' => 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)),
    ])->render();

    expect($html)->toContain('dir="rtl"')
        ->toContain('جامعة المنارة')
        ->toContain('هندسة البرمجيات')
        ->toContain('class="header-logo"')
        ->toContain('data:image/png;base64,');
});

it('streams a subject-specific student attendance report as a pdf download', function () {
    ['student' => $student, 'subjects' => $subjects] = createStudentAttendanceFixture();

    app()->setLocale('ar');

    $response = app(StudentAttendancePdfExporter::class)->download($student, $subjects['secondary']->id);

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('student_attendance_report_2024001_db302.pdf');

    ob_start();
    $response->sendContent();
    $pdf = ob_get_clean();

    expect($pdf)->toStartWith('%PDF');
});

function createStudentAttendanceFixture(): array
{
    $faculty = Faculty::query()->create([
        'name' => 'كلية الهندسة',
        'name_en' => 'Faculty of Engineering',
        'is_active' => true,
    ]);

    $department = Department::query()->create([
        'faculty_id' => $faculty->id,
        'name' => 'هندسة المعلوماتية',
        'name_en' => 'Informatics Engineering',
        'code' => 'INF',
        'is_active' => true,
    ]);

    $lecturer = User::query()->create([
        'name' => 'د. أحمد علي',
        'email' => 'lecturer@example.com',
        'password' => 'password',
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'faculty_id' => $faculty->id,
        'department_id' => $department->id,
        'title' => 'lecturer',
        'is_active' => true,
    ]);

    $hall = Hall::query()->create([
        'code' => 'H-101',
        'name' => 'القاعة 101',
        'floor' => 1,
        'is_active' => true,
    ]);

    $primarySubject = Subject::query()->create([
        'code' => 'INF301',
        'name' => 'هندسة البرمجيات',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'lecturer_id' => $lecturer->id,
        'department_id' => $department->id,
        'credit_hours' => 3,
        'level' => 3,
        'is_active' => true,
    ]);

    $secondarySubject = Subject::query()->create([
        'code' => 'DB302',
        'name' => 'قواعد البيانات',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'lecturer_id' => $lecturer->id,
        'department_id' => $department->id,
        'credit_hours' => 3,
        'level' => 3,
        'is_active' => true,
    ]);

    $student = Student::query()->create([
        'name' => 'محمد خالد',
        'faculty_id' => $faculty->id,
        'department_id' => $department->id,
        'year' => 3,
        'status' => 'active',
        'student_number' => '2024001',
        'national_number' => '12345678901',
        'is_active' => true,
    ]);

    DB::table('enrollments')->insert([
        'student_id' => $student->id,
        'subject_id' => $primarySubject->id,
        'semester' => 2,
        'year' => 3,
        'status' => 'enrolled',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('enrollments')->insert([
        'student_id' => $student->id,
        'subject_id' => $secondarySubject->id,
        'semester' => 2,
        'year' => 3,
        'status' => 'enrolled',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $sessions = [
        'missing' => LectureSession::query()->create([
            'subject_id' => $primarySubject->id,
            'lecturer_id' => $lecturer->id,
            'hall_id' => $hall->id,
            'session_date' => '2026-04-16',
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
            'status' => 'completed',
            'attendance_mode' => 'qr_otp',
        ]),
        'present' => LectureSession::query()->create([
            'subject_id' => $primarySubject->id,
            'lecturer_id' => $lecturer->id,
            'hall_id' => $hall->id,
            'session_date' => '2026-04-15',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => 'completed',
            'attendance_mode' => 'qr_otp',
        ]),
        'late' => LectureSession::query()->create([
            'subject_id' => $primarySubject->id,
            'lecturer_id' => $lecturer->id,
            'hall_id' => $hall->id,
            'session_date' => '2026-04-14',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'status' => 'completed',
            'attendance_mode' => 'qr_otp',
        ]),
        'secondary_present' => LectureSession::query()->create([
            'subject_id' => $secondarySubject->id,
            'lecturer_id' => $lecturer->id,
            'hall_id' => $hall->id,
            'session_date' => '2026-04-13',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'status' => 'completed',
            'attendance_mode' => 'qr_otp',
        ]),
    ];

    DB::table('attendances')->insert([
        [
            'lecture_session_id' => $sessions['present']->id,
            'student_id' => $student->id,
            'attendance_token_id' => null,
            'attendance_time' => '2026-04-15 09:01:00',
            'attendance_method' => 'admin',
            'attendance_status' => 'present',
            'ip_address' => null,
            'device_fingerprint' => null,
            'location_data' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'lecture_session_id' => $sessions['late']->id,
            'student_id' => $student->id,
            'attendance_token_id' => null,
            'attendance_time' => '2026-04-14 08:10:00',
            'attendance_method' => 'admin',
            'attendance_status' => 'late',
            'ip_address' => null,
            'device_fingerprint' => null,
            'location_data' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'lecture_session_id' => $sessions['secondary_present']->id,
            'student_id' => $student->id,
            'attendance_token_id' => null,
            'attendance_time' => '2026-04-13 10:05:00',
            'attendance_method' => 'admin',
            'attendance_status' => 'present',
            'ip_address' => null,
            'device_fingerprint' => null,
            'location_data' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    return [
        'student' => $student,
        'subjects' => [
            'primary' => $primarySubject,
            'secondary' => $secondarySubject,
        ],
        'sessions' => $sessions,
    ];
}
