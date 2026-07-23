<?php

use App\Filament\Resources\LectureSessions\LectureSessionResource;
use App\Filament\Resources\LectureSessions\Pages\CreateLectureSession;
use App\Filament\Resources\LectureSessions\Pages\EditLectureSession;
use App\Filament\Resources\LectureSessions\Pages\ListLectureSessions;
use App\Models\AcademicTerm;
use App\Models\AppSetting;
use App\Models\Hall;
use App\Models\LectureSession;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
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

function lectureSessionAcademicTerm(): AcademicTerm
{
    return AcademicTerm::query()->create([
        'display_name' => 'اختبار الفصل الصيفي',
        'canonical_name' => 'manual-session-term-'.uniqid(),
        'teaching_start_date' => now()->subWeek()->toDateString(),
        'teaching_end_date' => now()->addWeeks(4)->toDateString(),
    ]);
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

function lectureSessionAdmin(): User
{
    $user = User::factory()->create([
        'email' => 'lecture-session-ordinary-admin@example.com',
        'role' => 'admin',
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);

    $user->assignRole('admin');

    return $user;
}

function grantManualLectureSessionCreation(User $user): void
{
    Permission::findOrCreate('create manual lecture sessions', 'web');

    $user->givePermissionTo('create manual lecture sessions');
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
        'code' => 'H-'.fake()->unique()->numerify('###'),
        'name' => 'Main Hall',
        'floor' => 1,
        'is_active' => true,
    ]);
}

function lectureSessionFormData(Subject $subject, Hall $hall): array
{
    $term = AcademicTerm::query()->first() ?? lectureSessionAcademicTerm();

    return [
        'academic_term_id' => $term->id,
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

it('prevents an explicitly permitted lecturer from creating a lecture session for another lecturer subject', function (): void {
    $lecturerA = lectureSessionLecturer('lecturer-a@example.com');
    grantManualLectureSessionCreation($lecturerA);
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

it('keeps the manual lecture creation header action available alongside generation actions', function (): void {
    $admin = lectureSessionSuperAdmin();

    Livewire::actingAs($admin)
        ->test(ListLectureSessions::class)
        ->assertSee('إضافة محاضرة')
        ->assertSee('تحديد تاريخ بداية الأسبوع الأول ونهاية الأسبوع الأخير')
        ->assertSee('تهيئة حسابات المدرسين')
        ->assertSee('توليد الجلسات الجاهزة')
        ->assertSee('تقرير العمليات الناجحة')
        ->assertSee('تقرير الأخطاء والحالات المستبعدة');

    expect(LectureSession::query()->count())->toBe(0);
});

it('shows the manual lecture creation header action to ordinary administrators with the explicit manual permission', function (): void {
    $admin = lectureSessionAdmin();
    grantManualLectureSessionCreation($admin);

    $this->actingAs($admin);

    expect($admin->can('create', LectureSession::class))->toBeFalse()
        ->and($admin->can('create manual lecture sessions'))->toBeTrue()
        ->and(LectureSessionResource::canCreate())->toBeTrue();

    Livewire::actingAs($admin)
        ->test(ListLectureSessions::class)
        ->assertActionVisible('create')
        ->assertSee('إضافة محاضرة');

    expect(LectureSession::query()->count())->toBe(0);
});

it('does not show the manual lecture creation header action to course lecturers without explicit permission', function (): void {
    $lecturer = lectureSessionLecturer('manual-unpermitted-lecturer@example.com');

    $this->actingAs($lecturer);

    expect($lecturer->can('create manual lecture sessions'))->toBeFalse()
        ->and(LectureSessionResource::canCreate())->toBeFalse();

    Livewire::actingAs($lecturer)
        ->test(ListLectureSessions::class)
        ->assertActionHidden('create');

    expect(LectureSession::query()->count())->toBe(0);
});

it('seeds manual lecture creation permission only for administrators', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Permission::query()->where('name', 'create manual lecture sessions')->exists())->toBeTrue()
        ->and(Role::findByName('admin', 'web')->hasPermissionTo('create manual lecture sessions'))->toBeTrue()
        ->and(Role::findByName('super-admin', 'web')->hasPermissionTo('create manual lecture sessions'))->toBeTrue()
        ->and(Role::findByName('manager', 'web')->hasPermissionTo('create manual lecture sessions'))->toBeFalse()
        ->and(Role::findByName('course_lecturer', 'web')->hasPermissionTo('create manual lecture sessions'))->toBeFalse();
});

it('marks manually created sessions as not generated from the weekly schedule', function (): void {
    $lecturer = lectureSessionLecturer('manual-marker@example.com');
    $subject = lectureSessionSubject($lecturer, 'MAN101');
    $hall = lectureSessionHall();
    $admin = lectureSessionSuperAdmin();

    Livewire::actingAs($admin)
        ->test(CreateLectureSession::class)
        ->fillForm(lectureSessionFormData($subject, $hall))
        ->call('create')
        ->assertHasNoFormErrors();

    $session = LectureSession::query()->firstOrFail();

    expect($session->subject_section_schedule_slot_id)->toBeNull()
        ->and($session->lecture_session_generation_run_id)->toBeNull()
        ->and($session->generated_from_weekly_schedule_at)->toBeNull();
});

it('validates manual session term section lecturer hall date and time rules', function (): void {
    $lecturer = lectureSessionLecturer('manual-valid@example.com');
    $inactiveLecturer = lectureSessionLecturer('manual-inactive@example.com');
    $inactiveLecturer->update(['is_active' => false]);
    $subject = lectureSessionSubject($lecturer, 'MAN102');
    $term = lectureSessionAcademicTerm();
    $otherTerm = lectureSessionAcademicTerm();
    $section = $subject->sections()->create([
        'academic_term_id' => $otherTerm->id,
        'code' => 'T1',
        'lecturer_id' => $lecturer->id,
    ]);
    $hall = lectureSessionHall();
    $inactiveHall = lectureSessionHall();
    $inactiveHall->update(['is_active' => false]);
    $admin = lectureSessionAdmin();
    grantManualLectureSessionCreation($admin);
    $base = lectureSessionFormData($subject, $hall);
    $base['academic_term_id'] = $term->id;

    Livewire::actingAs($admin)
        ->test(CreateLectureSession::class)
        ->fillForm([...$base, 'subject_section_id' => $section->id])
        ->call('create')
        ->assertHasFormErrors(['subject_section_id']);

    Livewire::actingAs($admin)
        ->test(CreateLectureSession::class)
        ->fillForm([...$base, 'lecturer_id' => $inactiveLecturer->id])
        ->call('create')
        ->assertHasFormErrors(['lecturer_id']);

    Livewire::actingAs($admin)
        ->test(CreateLectureSession::class)
        ->fillForm([...$base, 'hall_id' => $inactiveHall->id])
        ->call('create')
        ->assertHasFormErrors(['hall_id']);

    Livewire::actingAs($admin)
        ->test(CreateLectureSession::class)
        ->fillForm([...$base, 'start_time' => '10:00', 'end_time' => '09:00'])
        ->call('create')
        ->assertHasErrors(['end_time']);

    Livewire::actingAs($admin)
        ->test(CreateLectureSession::class)
        ->fillForm([...$base, 'session_date' => now()->addYear()->toDateString()])
        ->call('create')
        ->assertHasErrors(['session_date']);

    expect(LectureSession::query()->count())->toBe(0);
});

it('allows teaching-period override only with explicit permission and written reason', function (): void {
    Permission::findOrCreate('override lecture session teaching period', 'web');

    $admin = lectureSessionAdmin();
    grantManualLectureSessionCreation($admin);
    $admin->givePermissionTo('override lecture session teaching period');
    $lecturer = lectureSessionLecturer('manual-override@example.com');
    $subject = lectureSessionSubject($lecturer, 'MAN103');
    $hall = lectureSessionHall();
    $data = lectureSessionFormData($subject, $hall);
    $data['session_date'] = now()->addYear()->toDateString();

    Livewire::actingAs($admin)
        ->test(CreateLectureSession::class)
        ->fillForm($data)
        ->call('create')
        ->assertHasErrors(['session_date']);

    Livewire::actingAs($admin)
        ->test(CreateLectureSession::class)
        ->fillForm([...$data, 'teaching_period_override_reason' => 'اعتماد إداري لمحاضرة تعويضية.'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(LectureSession::query()->count())->toBe(1);
});

it('uses the selected subject section lecturer when creating a lecture session', function (): void {
    $defaultLecturer = lectureSessionLecturer('default-section-lecturer@example.com');
    $sectionLecturer = lectureSessionLecturer('practical-section-lecturer@example.com');
    $subject = lectureSessionSubject($defaultLecturer, 'SEC101');
    $term = lectureSessionAcademicTerm();
    $section = $subject->sections()->create([
        'academic_term_id' => $term->id,
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

it('allows an explicitly permitted lecturer to create sessions for sections assigned to them', function (): void {
    $defaultLecturer = lectureSessionLecturer('default-owner@example.com');
    $sectionLecturer = lectureSessionLecturer('section-owner@example.com');
    grantManualLectureSessionCreation($sectionLecturer);
    $subject = lectureSessionSubject($defaultLecturer, 'SEC102');
    $term = lectureSessionAcademicTerm();
    $section = $subject->sections()->create([
        'academic_term_id' => $term->id,
        'code' => 'T1',
        'lecturer_id' => $sectionLecturer->id,
    ]);
    $hall = lectureSessionHall();

    $this->actingAs($sectionLecturer);

    expect(LectureSessionResource::scopeSubjectQueryForCurrentUser(Subject::query())->pluck('id')->all())
        ->toBe([$subject->id])
        ->and(LectureSessionResource::getSectionOptionsForSubject($subject->id, $term->id))
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
        ->assertHasFormErrors(['lecturer_id']);

    expect(LectureSession::query()->count())->toBe(0);
});
