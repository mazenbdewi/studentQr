<?php

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Hall;
use App\Models\LectureSession;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

function createLectureSessionForQrTests(User $lecturer): LectureSession
{
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

    return LectureSession::create([
        'subject_id' => $subject->id,
        'lecturer_id' => $lecturer->id,
        'hall_id' => $hall->id,
        'session_date' => now()->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'status' => 'active',
        'attendance_mode' => 'qr_otp',
        'qr_refresh_rate' => 120,
        'qr_expired' => false,
    ]);
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-05-19 09:30:00'));

    Role::create(['name' => 'course_lecturer', 'guard_name' => 'web']);
    Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('shows the qr action only for the owning lecturer during the lecture window', function () {
    $lecturer = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'title' => 'lecturer',
    ]);
    $lecturer->assignRole('course_lecturer');

    $otherLecturer = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'title' => 'lecturer',
    ]);
    $otherLecturer->assignRole('course_lecturer');

    $session = createLectureSessionForQrTests($lecturer);

    expect($session->shouldShowQrAction($lecturer))->toBeTrue()
        ->and($session->shouldShowQrAction($otherLecturer))->toBeFalse();

    $session->update(['qr_expired' => true]);

    expect($session->fresh()->shouldShowQrAction($lecturer))->toBeTrue();

    $session->update(['status' => 'completed']);

    expect($session->fresh()->shouldShowQrAction($lecturer))->toBeFalse();
});

it('allows the owning lecturer to open the qr page', function () {
    $lecturer = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'title' => 'lecturer',
    ]);
    $lecturer->assignRole('course_lecturer');

    $session = createLectureSessionForQrTests($lecturer);

    $response = $this->actingAs($lecturer)->get(route('teacher.lecture-session.qr', $session));

    $response->assertOk();
    $response->assertViewIs('teacher.lecture-session-qr');
    $response->assertViewHas('session', fn (LectureSession $viewSession) => $viewSession->is($session));
    $response->assertViewHas('expired', false);
});

it('lets the owning lecturer open qr five minutes before start and activates the session', function () {
    $lecturer = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'title' => 'lecturer',
    ]);
    $lecturer->assignRole('course_lecturer');

    $session = createLectureSessionForQrTests($lecturer);
    $session->update([
        'status' => 'scheduled',
        'start_time' => now()->addMinutes(5)->format('H:i:s'),
        'end_time' => now()->addHour()->format('H:i:s'),
    ]);

    expect($session->fresh()->shouldShowQrAction($lecturer))->toBeTrue();

    $response = $this->actingAs($lecturer)->get(route('teacher.lecture-session.qr', $session));

    $response->assertOk();
    $response->assertViewHas('expired', false);

    $session->refresh();

    expect($session->status)->toBe('active')
        ->and($session->actual_start)->not->toBeNull();
});

it('lets the owning lecturer reopen qr after a previous qr display expired', function () {
    $lecturer = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'title' => 'lecturer',
    ]);
    $lecturer->assignRole('course_lecturer');

    $session = createLectureSessionForQrTests($lecturer);
    $session->update([
        'qr_expired' => true,
        'qr_expires_at' => now()->subMinute(),
        'actual_end' => now()->subMinute(),
    ]);

    $response = $this->actingAs($lecturer)->get(route('teacher.lecture-session.qr', $session));

    $response->assertOk();
    $response->assertViewHas('expired', false);

    $session->refresh();

    expect($session->status)->toBe('active')
        ->and($session->qr_expired)->toBeFalse()
        ->and($session->actual_end)->toBeNull()
        ->and($session->qr_expires_at)->toBeGreaterThan(now());
});

it('does not automatically end the lecture when only the qr display duration expires', function () {
    $lecturer = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'title' => 'lecturer',
    ]);
    $lecturer->assignRole('course_lecturer');

    $session = createLectureSessionForQrTests($lecturer);
    $session->update([
        'qr_expired' => false,
        'qr_expires_at' => now()->subMinute(),
        'actual_end' => null,
    ]);

    $session->syncLifecycleState();
    $session->refresh();

    expect($session->status)->toBe('active')
        ->and($session->qr_expired)->toBeFalse()
        ->and($session->actual_end)->toBeNull();
});

it('allows the super admin to open the qr page for any lecture session', function () {
    $lecturer = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'title' => 'lecturer',
    ]);
    $lecturer->assignRole('course_lecturer');

    $superAdmin = User::factory()->create([
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
        'title' => 'lecturer',
        'email' => 'root@example.com',
    ]);
    $superAdmin->assignRole('super-admin');

    $session = createLectureSessionForQrTests($lecturer);

    expect($session->shouldShowQrAction($superAdmin))->toBeTrue();

    $response = $this->actingAs($superAdmin)->get(route('teacher.lecture-session.qr', $session));

    $response->assertOk();
    $response->assertViewIs('teacher.lecture-session-qr');
    $response->assertViewHas('expired', false);
});

it('automatically ends a session when the scheduled end time has passed', function () {
    $lecturer = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'title' => 'lecturer',
    ]);
    $lecturer->assignRole('course_lecturer');

    $session = createLectureSessionForQrTests($lecturer);
    $session->update([
        'session_date' => now()->toDateString(),
        'start_time' => now()->subHour()->format('H:i:s'),
        'end_time' => now()->subMinute()->format('H:i:s'),
        'qr_expired' => false,
        'qr_expires_at' => now()->addMinutes(5),
        'actual_end' => null,
    ]);

    $response = $this->actingAs($lecturer)->get(route('teacher.lecture-session.qr', $session));

    $response->assertOk();
    $response->assertViewHas('expired', true);

    $session->refresh();

    expect($session->status)->toBe('completed')
        ->and($session->qr_expired)->toBeTrue()
        ->and($session->actual_end)->not->toBeNull();
});

it('blocks non-lecturer users from accessing the lecturer qr page directly', function () {
    $lecturer = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'title' => 'lecturer',
    ]);
    $lecturer->assignRole('course_lecturer');

    $session = createLectureSessionForQrTests($lecturer);
    $studentLikeUser = User::factory()->create([
        'role' => 'attendance_monitor',
        'status' => 'active',
    ]);

    $this->actingAs($studentLikeUser)
        ->get(route('teacher.lecture-session.qr', $session))
        ->assertForbidden();
});
