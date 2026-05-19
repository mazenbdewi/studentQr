<?php

use App\Filament\Resources\LectureSessions\LectureSessionResource;
use App\Filament\Resources\LectureSessions\Pages\CreateLectureSession;
use App\Filament\Resources\LectureSessions\Pages\EditLectureSession;
use App\Models\AppSetting;
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
        'subject_type' => Subject::TYPE_THEORETICAL,
        'lecturer_id' => $lecturer->id,
        'credit_hours' => 3,
        'level' => 1,
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
    AppSetting::putBoolean(AppSetting::LECTURER_CAN_EDIT_LECTURE_SESSIONS_KEY, true);

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

it('uses the selected subject section lecturer when creating a lecture session', function (): void {
    $defaultLecturer = lectureSessionLecturer('default-section-lecturer@example.com');
    $sectionLecturer = lectureSessionLecturer('practical-section-lecturer@example.com');
    $subject = lectureSessionSubject($defaultLecturer, 'SEC101');
    $section = $subject->sections()->create([
        'code' => 'P1',
        'lecturer_id' => $sectionLecturer->id,
    ]);
    $hall = lectureSessionHall();
    $admin = lectureSessionSuperAdmin();

    Livewire::actingAs($admin)
        ->test(CreateLectureSession::class)
        ->fillForm(array_merge(lectureSessionFormData($subject, $hall), [
            'subject_section_id' => $section->id,
            'lecturer_id' => $defaultLecturer->id,
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $session = LectureSession::query()->first();

    expect($session?->subject_section_id)->toBe($section->id)
        ->and($session?->lecturer_id)->toBe($sectionLecturer->id);
});

it('allows a lecturer to create sessions for sections assigned to them', function (): void {
    $defaultLecturer = lectureSessionLecturer('default-owner@example.com');
    $sectionLecturer = lectureSessionLecturer('section-owner@example.com');
    $subject = lectureSessionSubject($defaultLecturer, 'SEC102');
    $section = $subject->sections()->create([
        'code' => 'T1',
        'lecturer_id' => $sectionLecturer->id,
    ]);
    $hall = lectureSessionHall();

    $this->actingAs($sectionLecturer);

    expect(LectureSessionResource::scopeSubjectQueryForCurrentUser(Subject::query())->pluck('id')->all())
        ->toBe([$subject->id])
        ->and(LectureSessionResource::getSectionOptionsForSubject($subject->id))
        ->toBe([$section->id => 'T1']);

    Livewire::actingAs($sectionLecturer)
        ->test(CreateLectureSession::class)
        ->fillForm(array_merge(lectureSessionFormData($subject, $hall), [
            'subject_section_id' => $section->id,
            'lecturer_id' => $sectionLecturer->id,
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $session = LectureSession::query()->first();

    expect($session?->lecturer_id)->toBe($sectionLecturer->id);
});

it('does not default a lecture session lecturer to the authenticated admin when the subject has no lecturer', function (): void {
    $admin = lectureSessionSuperAdmin();
    $hall = lectureSessionHall();
    $subject = Subject::query()->create([
        'code' => 'NOLECT101',
        'name' => 'Subject Without Lecturer',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'lecturer_id' => null,
        'credit_hours' => 3,
        'level' => 1,
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(CreateLectureSession::class)
        ->fillForm(array_merge(lectureSessionFormData($subject, $hall), [
            'lecturer_id' => $admin->id,
        ]))
        ->call('create')
        ->assertHasErrors(['lecturer_id']);

    expect(LectureSession::query()->count())->toBe(0);
});
