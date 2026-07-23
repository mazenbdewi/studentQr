<?php

use App\Filament\Resources\LectureSessions\LectureSessionResource;
use App\Filament\Resources\LectureSessions\Pages\CreateLectureSession;
use App\Filament\Resources\LectureSessions\Pages\EditLectureSession;
use App\Filament\Resources\LectureSessions\Pages\ListLectureSessions;
use App\Models\AcademicTerm;
use App\Models\AppSetting;
use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\Lecturer;
use App\Models\LectureSession;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Services\LectureSessionCalendarService;
use App\Services\SubjectSectionLecturerSynchronizationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
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

function lectureSessionScheduleBatch(AcademicTerm $term): ImportBatch
{
    $batch = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'lecture-session-schedule-batch-'.uniqid()),
        'import_type' => ImportBatch::TYPE_WEEKLY_SCHEDULE,
        'status' => ImportBatch::STATUS_COMPLETED,
        'imported_rows' => 1,
        'total_rows' => 1,
        'completed_at' => now(),
    ]);
    $batch->academicTerms()->attach($term->id, ['row_count' => 1]);

    return $batch;
}

function lectureSessionFormData(Subject $subject, Hall $hall): array
{
    $term = AcademicTerm::query()->first() ?? lectureSessionAcademicTerm();
    $section = SubjectSection::query()->firstOrCreate([
        'subject_id' => $subject->id,
        'academic_term_id' => $term->id,
        'code' => 'T1',
    ], [
        'lecturer_id' => $subject->lecturer_id,
    ]);

    return [
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'subject_section_id' => $section->id,
        'lecturer_id' => $section->lecturer_id,
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
    $term = lectureSessionAcademicTerm();
    $subjectA->sections()->create(['academic_term_id' => $term->id, 'code' => 'T1', 'lecturer_id' => $lecturerA->id]);
    $subjectB->sections()->create(['academic_term_id' => $term->id, 'code' => 'T1', 'lecturer_id' => $lecturerB->id]);

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

    $component = Livewire::actingAs($admin)
        ->test(ListLectureSessions::class)
        ->assertActionVisible('create')
        ->assertActionVisible('create_recurring')
        ->assertSee('إضافة محاضرة');

    expect(collect($component->instance()->getCachedHeaderActions())->map->getName()->take(2)->values()->all())
        ->toBe(['create', 'create_recurring']);

    expect(LectureSession::query()->count())->toBe(0);
});

it('does not show the manual lecture creation header action to course lecturers without explicit permission', function (): void {
    $lecturer = lectureSessionLecturer('manual-unpermitted-lecturer@example.com');

    $this->actingAs($lecturer);

    expect($lecturer->can('create manual lecture sessions'))->toBeFalse()
        ->and(LectureSessionResource::canCreate())->toBeFalse();

    Livewire::actingAs($lecturer)
        ->test(ListLectureSessions::class)
        ->assertActionHidden('create')
        ->assertActionHidden('create_recurring');

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

it('previews recurring manual lecture candidates without writing and creates only ready dated manual sessions', function (): void {
    Carbon::setTestNow('2026-07-10 09:00:00');

    $selectedLecturer = lectureSessionLecturer('recurring-selected@example.com');
    $otherLecturer = lectureSessionLecturer('recurring-other@example.com');
    $subject = lectureSessionSubject($selectedLecturer, 'REC101');
    $otherSubject = lectureSessionSubject($otherLecturer, 'REC102');
    $term = AcademicTerm::query()->create([
        'display_name' => 'الفصل الصيفي للاختبار',
        'canonical_name' => 'recurring-term-'.uniqid(),
        'teaching_start_date' => '2026-07-22',
        'teaching_end_date' => '2026-08-31',
    ]);
    $section = $subject->sections()->create([
        'academic_term_id' => $term->id,
        'code' => 'T1',
        'lecturer_id' => $selectedLecturer->id,
    ]);
    $otherSection = $otherSubject->sections()->create([
        'academic_term_id' => $term->id,
        'code' => 'T2',
        'lecturer_id' => $otherLecturer->id,
    ]);
    $hall = lectureSessionHall();
    $otherHall = lectureSessionHall();
    $baseSession = [
        'academic_term_id' => $term->id,
        'attendance_mode' => 'qr_otp',
        'qr_refresh_rate' => 120,
        'status' => 'scheduled',
    ];

    LectureSession::query()->create([
        ...$baseSession,
        'subject_id' => $subject->id,
        'subject_section_id' => $section->id,
        'lecturer_id' => $selectedLecturer->id,
        'hall_id' => $hall->id,
        'session_date' => '2026-07-22',
        'start_time' => '08:00',
        'end_time' => '09:00',
    ]);
    LectureSession::query()->create([
        ...$baseSession,
        'subject_id' => $otherSubject->id,
        'subject_section_id' => $otherSection->id,
        'lecturer_id' => $selectedLecturer->id,
        'hall_id' => $otherHall->id,
        'session_date' => '2026-07-23',
        'start_time' => '08:30',
        'end_time' => '09:30',
    ]);
    LectureSession::query()->create([
        ...$baseSession,
        'subject_id' => $otherSubject->id,
        'subject_section_id' => $otherSection->id,
        'lecturer_id' => $otherLecturer->id,
        'hall_id' => $hall->id,
        'session_date' => '2026-07-24',
        'start_time' => '08:30',
        'end_time' => '09:30',
    ]);
    LectureSession::query()->create([
        ...$baseSession,
        'subject_id' => $subject->id,
        'subject_section_id' => $section->id,
        'lecturer_id' => $otherLecturer->id,
        'hall_id' => $otherHall->id,
        'session_date' => '2026-07-29',
        'start_time' => '08:30',
        'end_time' => '09:30',
    ]);

    $data = [
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'subject_section_id' => $section->id,
        'hall_id' => $hall->id,
        'date_from' => '2026-07-15',
        'date_to' => '2026-07-31',
        'weekdays' => [Carbon::WEDNESDAY, Carbon::THURSDAY, Carbon::FRIDAY],
        'start_time' => '08:00',
        'end_time' => '09:00',
        'status' => 'scheduled',
        'notes' => 'محاضرات مجدولة يدويًا',
    ];
    $beforePreviewCount = LectureSession::query()->count();

    $preview = app(LectureSessionCalendarService::class)->previewRecurring($data);

    expect(LectureSession::query()->count())->toBe($beforePreviewCount)
        ->and(collect($preview['rows'])->pluck('date')->all())->toBe([
            '2026-07-15',
            '2026-07-16',
            '2026-07-17',
            '2026-07-22',
            '2026-07-23',
            '2026-07-24',
            '2026-07-29',
            '2026-07-30',
            '2026-07-31',
        ])
        ->and(collect($preview['rows'])->pluck('result')->all())->toBe([
            'outside_teaching_period',
            'outside_teaching_period',
            'outside_teaching_period',
            'existing',
            'lecturer_conflict',
            'hall_conflict',
            'section_conflict',
            'ready',
            'ready',
        ])
        ->and($preview['ready_count'])->toBe(2)
        ->and($preview['skipped_count'])->toBe(7);

    $result = app(LectureSessionCalendarService::class)->createRecurring($data);
    $createdSessions = LectureSession::query()
        ->whereIn('id', $result['created_ids'])
        ->orderBy('session_date')
        ->get();

    expect($result['created'])->toBe(2)
        ->and($result['skipped'])->toBe(7)
        ->and($createdSessions->pluck('session_date')->map->toDateString()->all())->toBe(['2026-07-30', '2026-07-31'])
        ->and($createdSessions->pluck('academic_term_id')->unique()->all())->toBe([$term->id])
        ->and($createdSessions->pluck('subject_id')->unique()->all())->toBe([$subject->id])
        ->and($createdSessions->pluck('subject_section_id')->unique()->all())->toBe([$section->id])
        ->and($createdSessions->pluck('lecturer_id')->unique()->all())->toBe([$selectedLecturer->id])
        ->and($createdSessions->pluck('hall_id')->unique()->all())->toBe([$hall->id]);

    foreach ($createdSessions as $session) {
        expect($session->subject_section_schedule_slot_id)->toBeNull()
            ->and($session->lecture_session_generation_run_id)->toBeNull()
            ->and($session->generated_from_weekly_schedule_at)->toBeNull();
    }

    Carbon::setTestNow();
});

it('synchronizes the imported weekly schedule lecturer into the canonical subject section assignment', function (): void {
    $admin = lectureSessionSuperAdmin();
    $lecturerUser = lectureSessionLecturer('weekly-identity-manual@example.com');
    $subject = Subject::query()->create([
        'code' => 'WEEKLY101',
        'name' => 'Weekly Lecturer Subject',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'lecturer_id' => null,
        'credit_hours' => 3,
        'level' => 1,
        'is_active' => true,
    ]);
    $term = lectureSessionAcademicTerm();
    $section = $subject->sections()->create([
        'academic_term_id' => $term->id,
        'code' => 'T1',
        'lecturer_id' => null,
    ]);
    $hall = lectureSessionHall();
    $identity = Lecturer::query()->create([
        'user_id' => $lecturerUser->id,
        'name' => 'هوية مدرس الجدول',
        'canonical_name' => 'هوية مدرس الجدول',
        'is_active' => true,
    ]);
    SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => lectureSessionScheduleBatch($term)->id,
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'subject_section_id' => $section->id,
        'lecturer_id' => $identity->id,
        'hall_id' => $hall->id,
        'weekday' => Carbon::MONDAY,
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
    ]);
    $preview = app(SubjectSectionLecturerSynchronizationService::class)->previewSections([$section->id]);

    expect($preview['unique_lecturer_count'])->toBe(1)
        ->and($section->fresh()->lecturer_id)->toBeNull();

    app(SubjectSectionLecturerSynchronizationService::class)->synchronizeSections([$section->id]);

    $this->actingAs($admin);

    expect(LectureSessionResource::manualLecturerOptions($term->id, $subject->id, $section->id))
        ->toBe([$lecturerUser->id => $lecturerUser->name])
        ->and(LectureSessionResource::resolveLecturerIdForSubjectAndSection($subject->id, $section->id, $term->id))
        ->toBe($lecturerUser->id)
        ->and(LectureSessionResource::shouldShowMissingLecturerWarning($subject->id, $section->id, $term->id))
        ->toBeFalse();

    Livewire::actingAs($admin)
        ->test(CreateLectureSession::class)
        ->fillForm([
            ...lectureSessionFormData($subject, $hall),
            'academic_term_id' => $term->id,
            'subject_section_id' => $section->id,
            'lecturer_id' => $lecturerUser->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $session = LectureSession::query()->firstOrFail();

    expect($session->lecturer_id)->toBe($lecturerUser->id)
        ->and($session->subject_section_schedule_slot_id)->toBeNull()
        ->and($session->lecture_session_generation_run_id)->toBeNull()
        ->and($session->generated_from_weekly_schedule_at)->toBeNull();
});

it('uses the synchronized subject section lecturer for recurring manual sessions', function (): void {
    $lecturerUser = lectureSessionLecturer('weekly-identity-recurring@example.com');
    $subject = Subject::query()->create([
        'code' => 'WEEKLY102',
        'name' => 'Weekly Recurring Subject',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'lecturer_id' => null,
        'credit_hours' => 3,
        'level' => 1,
        'is_active' => true,
    ]);
    $term = AcademicTerm::query()->create([
        'display_name' => 'اختبار جدول أسبوعي',
        'canonical_name' => 'weekly-recurring-term-'.uniqid(),
        'teaching_start_date' => '2026-07-20',
        'teaching_end_date' => '2026-07-31',
    ]);
    $section = $subject->sections()->create([
        'academic_term_id' => $term->id,
        'code' => 'T1',
        'lecturer_id' => null,
    ]);
    $hall = lectureSessionHall();
    $identity = Lecturer::query()->create([
        'user_id' => $lecturerUser->id,
        'name' => 'هوية مدرس متكرر',
        'canonical_name' => 'هوية مدرس متكرر',
        'is_active' => true,
    ]);
    SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => lectureSessionScheduleBatch($term)->id,
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'subject_section_id' => $section->id,
        'lecturer_id' => $identity->id,
        'hall_id' => $hall->id,
        'weekday' => Carbon::MONDAY,
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
    ]);
    app(SubjectSectionLecturerSynchronizationService::class)->synchronizeSections([$section->id]);

    $result = app(LectureSessionCalendarService::class)->createRecurring([
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'subject_section_id' => $section->id,
        'lecturer_id' => $lecturerUser->id,
        'hall_id' => $hall->id,
        'date_from' => '2026-07-20',
        'date_to' => '2026-07-27',
        'weekdays' => [Carbon::MONDAY],
        'start_time' => '08:00',
        'end_time' => '09:00',
        'status' => 'scheduled',
    ]);

    expect($result['created'])->toBe(2)
        ->and(LectureSession::query()->pluck('lecturer_id')->unique()->all())->toBe([$lecturerUser->id])
        ->and(LectureSession::query()->whereNull('subject_section_schedule_slot_id')->count())->toBe(2)
        ->and(LectureSession::query()->whereNull('lecture_session_generation_run_id')->count())->toBe(2)
        ->and(LectureSession::query()->whereNull('generated_from_weekly_schedule_at')->count())->toBe(2);
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
            'lecturer_id' => $sectionLecturer->id,
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

it('synchronizes only the affected term section, clears ambiguity, and is idempotent without changing slots or sessions', function (): void {
    $firstUser = lectureSessionLecturer('sync-first@example.com');
    $secondUser = lectureSessionLecturer('sync-second@example.com');
    $subject = lectureSessionSubject($firstUser, 'SYNC101');
    $term = lectureSessionAcademicTerm();
    $otherTerm = lectureSessionAcademicTerm();
    $section = $subject->sections()->create(['academic_term_id' => $term->id, 'code' => 'T1', 'lecturer_id' => null]);
    $otherSection = $subject->sections()->create(['academic_term_id' => $otherTerm->id, 'code' => 'T1', 'lecturer_id' => $secondUser->id]);
    $firstIdentity = Lecturer::query()->create(['user_id' => $firstUser->id, 'name' => 'هوية أولى', 'canonical_name' => 'هوية أولى', 'is_active' => true]);
    $secondIdentity = Lecturer::query()->create(['user_id' => $secondUser->id, 'name' => 'هوية ثانية', 'canonical_name' => 'هوية ثانية', 'is_active' => true]);
    $batch = lectureSessionScheduleBatch($term);
    $hall = lectureSessionHall();

    $firstSlot = SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $batch->id, 'academic_term_id' => $term->id, 'subject_id' => $subject->id,
        'subject_section_id' => $section->id, 'lecturer_id' => $firstIdentity->id, 'hall_id' => $hall->id,
        'weekday' => Carbon::MONDAY, 'start_time' => '08:00:00', 'end_time' => '09:00:00',
    ]);
    $sameLecturerSlot = SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $batch->id, 'academic_term_id' => $term->id, 'subject_id' => $subject->id,
        'subject_section_id' => $section->id, 'lecturer_id' => $firstIdentity->id, 'hall_id' => $hall->id,
        'weekday' => Carbon::TUESDAY, 'start_time' => '08:00:00', 'end_time' => '09:00:00',
    ]);
    $otherTermSlot = SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $batch->id, 'academic_term_id' => $otherTerm->id, 'subject_id' => $subject->id,
        'subject_section_id' => $otherSection->id, 'lecturer_id' => $secondIdentity->id, 'hall_id' => $hall->id,
        'weekday' => Carbon::MONDAY, 'start_time' => '10:00:00', 'end_time' => '11:00:00',
    ]);
    $service = app(SubjectSectionLecturerSynchronizationService::class);

    $first = $service->synchronizeSections([$section->id]);

    expect($first['unique_lecturer_count'])->toBe(1)
        ->and($section->fresh()->lecturer_id)->toBe($firstUser->id)
        ->and($otherSection->fresh()->lecturer_id)->toBe($secondUser->id)
        ->and($firstSlot->fresh()->lecturer_id)->toBe($firstIdentity->id)
        ->and($sameLecturerSlot->fresh()->lecturer_id)->toBe($firstIdentity->id)
        ->and($otherTermSlot->fresh()->lecturer_id)->toBe($secondIdentity->id)
        ->and(LectureSession::query()->count())->toBe(0);

    SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $batch->id, 'academic_term_id' => $term->id, 'subject_id' => $subject->id,
        'subject_section_id' => $section->id, 'lecturer_id' => $secondIdentity->id, 'hall_id' => $hall->id,
        'weekday' => Carbon::WEDNESDAY, 'start_time' => '08:00:00', 'end_time' => '09:00:00',
    ]);

    $ambiguous = $service->synchronizeSections([$section->id]);
    $again = $service->synchronizeSections([$section->id]);

    expect($ambiguous['multiple_lecturers_count'])->toBe(1)
        ->and($ambiguous['sections'][0]['warning'])->toContain('أكثر من محاضر')
        ->and($section->fresh()->lecturer_id)->toBeNull()
        ->and($again['unchanged_count'])->toBe(1)
        ->and(LectureSession::query()->count())->toBe(0);
});

it('does not use the subject lecturer as a fallback for either manual form', function (): void {
    $subjectLecturer = lectureSessionLecturer('subject-only@example.com');
    $subject = lectureSessionSubject($subjectLecturer, 'NOFALLBACK101');
    $term = lectureSessionAcademicTerm();
    $section = $subject->sections()->create(['academic_term_id' => $term->id, 'code' => 'T1', 'lecturer_id' => null]);

    expect(LectureSessionResource::manualLecturerOptions($term->id, $subject->id, $section->id))->toBe([])
        ->and(LectureSessionResource::shouldShowMissingLecturerWarning($subject->id, $section->id, $term->id))->toBeTrue();
});

it('offers every ready weekly-slot lecturer for an ambiguous section without showing the missing lecturer warning', function (): void {
    $firstUser = lectureSessionLecturer('multiple-first@example.com');
    $secondUser = lectureSessionLecturer('multiple-second@example.com');
    $subject = lectureSessionSubject($firstUser, 'MULTI101');
    $term = lectureSessionAcademicTerm();
    $section = $subject->sections()->create(['academic_term_id' => $term->id, 'code' => 'T1', 'lecturer_id' => null]);
    $firstIdentity = Lecturer::query()->create(['user_id' => $firstUser->id, 'name' => 'هوية أولى', 'canonical_name' => 'هوية أولى', 'is_active' => true]);
    $secondIdentity = Lecturer::query()->create(['user_id' => $secondUser->id, 'name' => 'هوية ثانية', 'canonical_name' => 'هوية ثانية', 'is_active' => true]);
    $hall = lectureSessionHall();
    $batch = lectureSessionScheduleBatch($term);

    foreach ([$firstIdentity, $secondIdentity] as $offset => $identity) {
        SubjectSectionScheduleSlot::query()->create([
            'import_batch_id' => $batch->id, 'academic_term_id' => $term->id, 'subject_id' => $subject->id,
            'subject_section_id' => $section->id, 'lecturer_id' => $identity->id, 'hall_id' => $hall->id,
            'weekday' => Carbon::MONDAY + $offset, 'start_time' => '08:00:00', 'end_time' => '09:00:00',
        ]);
    }

    $resolution = app(\App\Services\LectureSessionLecturerResolver::class)->resolve($term->id, $subject->id, $section->id);

    expect($section->fresh()->lecturer_id)->toBeNull()
        ->and($resolution['status'])->toBe('multiple')
        ->and($resolution['users']->pluck('id')->sort()->values()->all())->toBe(collect([$firstUser->id, $secondUser->id])->sort()->values()->all())
        ->and($resolution['source_slot_ids'])->toHaveCount(2)
        ->and(collect(LectureSessionResource::manualLecturerOptions($term->id, $subject->id, $section->id))->keys()->sort()->values()->all())
        ->toBe(collect([$firstUser->id, $secondUser->id])->sort()->values()->all())
        ->and(LectureSessionResource::shouldShowMissingLecturerWarning($subject->id, $section->id, $term->id))->toBeFalse();

    $admin = lectureSessionSuperAdmin();
    Livewire::actingAs($admin)
        ->test(CreateLectureSession::class)
        ->fillForm([
            ...lectureSessionFormData($subject, $hall),
            'academic_term_id' => $term->id,
            'subject_section_id' => $section->id,
            'lecturer_id' => $secondUser->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(LectureSession::query()->sole()->lecturer_id)->toBe($secondUser->id);
});
