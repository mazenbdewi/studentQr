<?php

use App\Filament\Resources\LectureSessions\LectureSessionResource;
use App\Filament\Resources\LectureSessions\Pages\CreateLectureSession;
use App\Filament\Resources\LectureSessions\Pages\EditLectureSession;
use App\Models\Hall;
use App\Models\LectureSession;
use App\Models\Subject;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'course_lecturer', 'guard_name' => 'web']);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function lectureSessionLecturer(string $email): User
{
    $user = User::factory()->create([
        'email' => $email,
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);

    $user->assignRole('course_lecturer');

    return $user;
}

function lectureSessionSuperAdmin(): User
{
    $user = User::factory()->create([
        'email' => 'lecture-session-admin@example.com',
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);

    $user->assignRole('super-admin');

    return $user;
}

function lectureSessionSubject(User $lecturer, string $code): Subject
{
    return Subject::query()->create([
        'code' => $code,
        'name' => "Subject {$code}",
        'lecturer_id' => $lecturer->id,
        'credit_hours' => 3,
        'level' => 1,
        'semester' => Subject::SEMESTER_FIRST,
        'is_active' => true,
    ]);
}

function lectureSessionHall(): Hall
{
    return Hall::query()->create([
        'code' => 'H-' . fake()->unique()->numerify('###'),
        'name' => 'Main Hall',
        'floor' => 1,
        'is_active' => true,
    ]);
}

function lectureSessionFormData(Subject $subject, Hall $hall): array
{
    return [
        'subject_id' => $subject->id,
        'lecturer_id' => $subject->lecturer_id,
        'hall_id' => $hall->id,
        'session_date' => now()->addDay()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '09:00',
        'status' => 'scheduled',
        'attendance_mode' => 'qr_otp',
        'qr_refresh_rate' => 120,
        'notes' => null,
    ];
}

it('limits the lecture session subject query to the authenticated lecturer subjects', function (): void {
    $lecturerA = lectureSessionLecturer('lecturer-a@example.com');
    $lecturerB = lectureSessionLecturer('lecturer-b@example.com');
    $subjectA = lectureSessionSubject($lecturerA, 'A101');
    $subjectB = lectureSessionSubject($lecturerB, 'B101');

    $this->actingAs($lecturerA);

    expect(LectureSessionResource::scopeSubjectQueryForCurrentUser(Subject::query())->pluck('id')->all())
        ->toBe([$subjectA->id]);

    $this->actingAs($lecturerB);

    expect(LectureSessionResource::scopeSubjectQueryForCurrentUser(Subject::query())->pluck('id')->all())
        ->toBe([$subjectB->id]);
});

it('allows super admins to see all lecture session subjects', function (): void {
    $lecturerA = lectureSessionLecturer('lecturer-a@example.com');
    $lecturerB = lectureSessionLecturer('lecturer-b@example.com');
    $subjectA = lectureSessionSubject($lecturerA, 'A102');
    $subjectB = lectureSessionSubject($lecturerB, 'B102');
    $admin = lectureSessionSuperAdmin();

    $this->actingAs($admin);

    expect(LectureSessionResource::scopeSubjectQueryForCurrentUser(Subject::query())->pluck('id')->sort()->values()->all())
        ->toBe(collect([$subjectA->id, $subjectB->id])->sort()->values()->all());
});

it('prevents a lecturer from creating a lecture session for another lecturer subject', function (): void {
    $lecturerA = lectureSessionLecturer('lecturer-a@example.com');
    $lecturerB = lectureSessionLecturer('lecturer-b@example.com');
    $subjectB = lectureSessionSubject($lecturerB, 'B103');
    $hall = lectureSessionHall();

    Livewire::actingAs($lecturerA)
        ->test(CreateLectureSession::class)
        ->fillForm(lectureSessionFormData($subjectB, $hall))
        ->call('create')
        ->assertHasFormErrors(['subject_id']);

    expect(LectureSession::query()->count())->toBe(0);
});

it('prevents a lecturer from editing a lecture session to another lecturer subject', function (): void {
    $lecturerA = lectureSessionLecturer('lecturer-a@example.com');
    $lecturerB = lectureSessionLecturer('lecturer-b@example.com');
    $subjectA = lectureSessionSubject($lecturerA, 'A104');
    $subjectB = lectureSessionSubject($lecturerB, 'B104');
    $hall = lectureSessionHall();
    $session = LectureSession::query()->create(lectureSessionFormData($subjectA, $hall));

    Livewire::actingAs($lecturerA)
        ->test(EditLectureSession::class, ['record' => $session->getRouteKey()])
        ->fillForm(lectureSessionFormData($subjectB, $hall))
        ->call('save')
        ->assertHasFormErrors(['subject_id']);

    expect($session->refresh()->subject_id)->toBe($subjectA->id);
});

it('allows super admins to create lecture sessions for any subject', function (): void {
    $lecturer = lectureSessionLecturer('lecturer-a@example.com');
    $subject = lectureSessionSubject($lecturer, 'A105');
    $hall = lectureSessionHall();
    $admin = lectureSessionSuperAdmin();

    Livewire::actingAs($admin)
        ->test(CreateLectureSession::class)
        ->fillForm(lectureSessionFormData($subject, $hall))
        ->call('create')
        ->assertHasNoFormErrors();

    $session = LectureSession::query()->first();

    expect($session?->subject_id)->toBe($subject->id)
        ->and($session?->lecturer_id)->toBe($lecturer->id);
});
