<?php

use App\Filament\Pages\LecturerAccountPreparation;
use App\Models\Lecturer;
use App\Models\LectureSession;
use App\Models\User;
use App\Services\LecturerAccountPreparationService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function lecturerAccountPreparationAdmin(): User
{
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'course_lecturer', 'guard_name' => 'web']);

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

function arabicLecturerIdentity(array $overrides = []): Lecturer
{
    return Lecturer::query()->create([
        'name' => 'د. أحمد الخطيب',
        'canonical_name' => 'د. أحمد الخطيب',
        'is_active' => true,
        ...$overrides,
    ]);
}

it('prefills the account form with the Arabic lecturer name and does not generate an email', function (): void {
    $lecturer = arabicLecturerIdentity();

    Livewire::actingAs(lecturerAccountPreparationAdmin())
        ->test(LecturerAccountPreparation::class)
        ->mountTableAction('create-login-account', $lecturer)
        ->assertTableActionDataSet([
            'name' => 'د. أحمد الخطيب',
            'email' => null,
        ]);
});

it('requires a real unique email for new lecturer accounts', function (): void {
    $lecturer = arabicLecturerIdentity();

    Livewire::actingAs(lecturerAccountPreparationAdmin())
        ->test(LecturerAccountPreparation::class)
        ->callTableAction('create-login-account', $lecturer, [
            'password' => 'temporary-password',
            'password_confirmation' => 'temporary-password',
        ])
        ->assertHasTableActionErrors(['email' => 'required']);

    User::factory()->create([
        'email' => 'existing@example.test',
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
    ]);

    Livewire::actingAs(lecturerAccountPreparationAdmin())
        ->test(LecturerAccountPreparation::class)
        ->callTableAction('create-login-account', $lecturer, [
            'email' => 'existing@example.test',
            'password' => 'temporary-password',
            'password_confirmation' => 'temporary-password',
        ])
        ->assertHasTableActionErrors(['email' => 'unique']);
});

it('creates a linked course lecturer account with Arabic name preserved and a hashed password', function (): void {
    $lecturer = arabicLecturerIdentity();
    $sessionsBefore = LectureSession::query()->count();

    Livewire::actingAs(lecturerAccountPreparationAdmin())
        ->test(LecturerAccountPreparation::class)
        ->callTableAction('create-login-account', $lecturer, [
            'email' => 'ahmad.khatib@example.test',
            'password' => 'temporary-password',
            'password_confirmation' => 'temporary-password',
        ])
        ->assertHasNoTableActionErrors();

    $user = User::query()->where('email', 'ahmad.khatib@example.test')->firstOrFail();

    expect($user->name)->toBe('د. أحمد الخطيب')
        ->and($user->email)->toBe('ahmad.khatib@example.test')
        ->and($user->password)->not->toBe('temporary-password')
        ->and(Hash::check('temporary-password', $user->password))->toBeTrue()
        ->and($user->hasRole('course_lecturer'))->toBeTrue()
        ->and($lecturer->fresh()->user_id)->toBe($user->id)
        ->and(LectureSession::query()->count())->toBe($sessionsBefore);
});

it('rejects duplicate lecturer links and prevents one user from being linked to multiple lecturers', function (): void {
    $service = app(LecturerAccountPreparationService::class);
    $firstLecturer = arabicLecturerIdentity(['name' => 'د. سارة منصور', 'canonical_name' => 'د. سارة منصور']);
    $secondLecturer = arabicLecturerIdentity(['name' => 'د. ليلى حسن', 'canonical_name' => 'د. ليلى حسن']);
    $user = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);

    lecturerAccountPreparationAdmin();

    $service->linkExistingAccount($firstLecturer, $user);

    expect(fn () => $service->linkExistingAccount($firstLecturer->fresh(), User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
    ])))->toThrow(ValidationException::class)
        ->and(fn () => $service->linkExistingAccount($secondLecturer, $user))->toThrow(ValidationException::class);
});

it('grants the course lecturer role to a linked account', function (): void {
    $lecturer = arabicLecturerIdentity();
    $user = User::factory()->create([
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);
    lecturerAccountPreparationAdmin();
    app(LecturerAccountPreparationService::class)->linkExistingAccount($lecturer, $user);

    expect($user->fresh()->hasRole('course_lecturer'))->toBeFalse();

    app(LecturerAccountPreparationService::class)->grantCourseLecturerRole($lecturer->fresh());

    expect($user->fresh()->hasRole('course_lecturer'))->toBeTrue()
        ->and($user->fresh()->role)->toBe('course_lecturer')
        ->and($user->fresh()->type)->toBe('lecturer');
});
