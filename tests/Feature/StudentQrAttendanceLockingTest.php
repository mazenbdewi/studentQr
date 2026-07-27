<?php

use App\Models\AcademicTerm;
use App\Models\AppSetting;
use App\Models\Attendance;
use App\Models\AttendanceToken;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\Hall;
use App\Models\LectureSession;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\User;

function createQrAttendanceScenario(): array
{
    $term = AcademicTerm::query()->create([
        'display_name' => 'فصل حضور QR',
        'canonical_name' => 'qr-attendance-'.str()->uuid(),
        'teaching_start_date' => now()->subDay()->toDateString(),
        'teaching_end_date' => now()->addMonth()->toDateString(),
    ]);
    AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, (string) $term->id);

    $faculty = Faculty::create([
        'name' => 'Engineering',
        'name_en' => 'Engineering',
        'is_active' => true,
    ]);

    $department = Department::create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Science',
        'code' => 'CS',
        'name_en' => 'Computer Science',
        'is_active' => true,
    ]);

    $lecturer = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'title' => 'lecturer',
    ]);

    $hall = Hall::create([
        'code' => 'H1',
        'name' => 'Hall 1',
        'floor' => 1,
        'is_active' => true,
    ]);

    $subject = Subject::unguarded(fn () => Subject::create([
        'code' => 'CS101',
        'name' => 'Programming 1',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'department_id' => $department->id,
        'lecturer_id' => $lecturer->id,
        'credit_hours' => 3,
        'level' => 1,
        'is_active' => true,
    ]));

    $student = Student::create([
        'name' => 'Test Student',
        'faculty_id' => $faculty->id,
        'department_id' => $department->id,
        'year' => 1,
        'type' => 'student',
        'status' => 'active',
        'student_number' => '2024001',
        'national_number' => '12345678901',
        'is_active' => true,
    ]);

    $section = SubjectSection::query()->create([
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'lecturer_id' => $lecturer->id,
        'section_type' => Subject::TYPE_THEORETICAL,
        'code' => 'T1',
    ]);

    $secondStudent = Student::create([
        'name' => 'Second Student',
        'faculty_id' => $faculty->id,
        'department_id' => $department->id,
        'year' => 1,
        'type' => 'student',
        'status' => 'active',
        'student_number' => '2024002',
        'national_number' => '12345678902',
        'is_active' => true,
    ]);

    Enrollment::create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'academic_term_id' => $term->id,
        'theoretical_section_id' => $section->id,
        'semester' => 1,
        'year' => 1,
        'status' => 'enrolled',
    ]);

    Enrollment::create([
        'student_id' => $secondStudent->id,
        'subject_id' => $subject->id,
        'academic_term_id' => $term->id,
        'theoretical_section_id' => $section->id,
        'semester' => 1,
        'year' => 1,
        'status' => 'enrolled',
    ]);

    $session = LectureSession::create([
        'subject_id' => $subject->id,
        'academic_term_id' => $term->id,
        'subject_section_id' => $section->id,
        'lecturer_id' => $lecturer->id,
        'hall_id' => $hall->id,
        'session_date' => now()->addDay()->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'status' => 'active',
        'attendance_mode' => 'qr_otp',
        'qr_refresh_rate' => 300,
        'session_otp' => '123456',
        'qr_expired' => false,
        'qr_started_at' => now(),
        'qr_expires_at' => now()->addMinutes(5),
    ]);

    $token = AttendanceToken::create([
        'lecture_session_id' => $session->id,
        'token_type' => 'qr',
        'token_value' => 'qr-token-'.$session->id,
        'expires_at' => now()->addMinutes(5),
        'is_used' => false,
    ]);

    return compact('session', 'student', 'secondStudent', 'token');
}

it('locks the QR attendance page after a successful submission, even after refresh and reopening the qr link', function () {
    ['session' => $session, 'student' => $student, 'token' => $token] = createQrAttendanceScenario();

    $verifyResponse = $this->get(route('student.attendance.verify.token', ['token' => $token->token_value]));

    $verifyResponse->assertOk();
    $verifyResponse->assertViewIs('student.attendance-fast');
    $verifyResponse->assertViewHas('attendanceCompleted', false);

    preg_match('/name="submission_token" value="([^"]+)"/', $verifyResponse->getContent(), $matches);

    expect($matches[1] ?? null)->not->toBeNull();

    $submissionToken = $matches[1];

    $submitResponse = $this->postJson(route('student.attendance.store.sync', ['session' => $session->id]), [
        'student_number' => $student->student_number,
        'otp' => '123456',
        'submission_token' => $submissionToken,
    ]);

    $submitResponse
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => __('student.attendance_recorded'),
        ]);

    $completedCookie = collect($submitResponse->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === 'attendance_completed_'.$session->id);

    expect($completedCookie)->not->toBeNull();

    expect(Attendance::query()
        ->where('lecture_session_id', $session->id)
        ->where('student_id', $student->id)
        ->count())->toBe(1);

    $refreshResponse = $this
        ->withUnencryptedCookie($completedCookie->getName(), $completedCookie->getValue())
        ->get(route('student.attendance.verify.form', ['session' => $session->id]));

    $refreshResponse->assertOk();
    $refreshResponse->assertViewIs('student.attendance');
    $refreshResponse->assertViewHas('attendanceCompleted', true);
    $refreshResponse->assertViewHas('successMessage', __('student.attendance_already_submitted'));
    $refreshResponse->assertViewHas('studentNumberValue', $student->student_number);
    $refreshResponse->assertSee('id="submitBtn" disabled', false);

    $reopenQrResponse = $this
        ->withUnencryptedCookie($completedCookie->getName(), $completedCookie->getValue())
        ->get(route('student.attendance.verify.token', ['token' => $token->token_value]));

    $reopenQrResponse->assertOk();
    $reopenQrResponse->assertViewIs('student.attendance');
    $reopenQrResponse->assertViewHas('attendanceCompleted', true);
    $reopenQrResponse->assertViewHas('successMessage', __('student.attendance_already_submitted'));
    $reopenQrResponse->assertSee('id="submitBtn" disabled', false);
});

it('blocks recording attendance for another student from the same device in the same session', function () {
    ['session' => $session, 'student' => $student, 'secondStudent' => $secondStudent, 'token' => $token] = createQrAttendanceScenario();

    $verifyResponse = $this->get(route('student.attendance.verify.token', ['token' => $token->token_value]));

    preg_match('/name="submission_token" value="([^"]+)"/', $verifyResponse->getContent(), $matches);

    $submissionToken = $matches[1] ?? null;

    expect($submissionToken)->not->toBeNull();

    $deviceFingerprint = 'test-device-fingerprint';

    $this
        ->withHeader('X-Device-Fingerprint', $deviceFingerprint)
        ->postJson(route('student.attendance.store.sync', ['session' => $session->id]), [
            'student_number' => $student->student_number,
            'otp' => '123456',
            'submission_token' => $submissionToken,
            'device_fingerprint' => $deviceFingerprint,
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => __('student.attendance_recorded'),
        ]);

    $this
        ->withHeader('X-Device-Fingerprint', $deviceFingerprint)
        ->postJson(route('student.attendance.store.sync', ['session' => $session->id]), [
            'student_number' => $secondStudent->student_number,
            'otp' => '123456',
            'submission_token' => $submissionToken,
            'device_fingerprint' => $deviceFingerprint,
        ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => __('student.device_already_used_for_attendance'),
        ]);

    expect(Attendance::query()->where('lecture_session_id', $session->id)->count())->toBe(1)
        ->and(Attendance::query()
            ->where('lecture_session_id', $session->id)
            ->where('student_id', $secondStudent->id)
            ->exists())->toBeFalse();
});
