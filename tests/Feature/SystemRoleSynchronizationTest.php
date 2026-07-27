<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['super-admin', 'admin', 'manager', 'course_lecturer', 'student', 'optional-role'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

it('maps supported classifications to their seeded Spatie roles', function (string $classification, string $spatieRole): void {
    $user = User::factory()->create(['role' => $classification]);

    expect($user->hasRole($spatieRole))->toBeFalse();

    $user->syncSystemRole($classification);

    expect($user->fresh()->hasRole($spatieRole))->toBeTrue();
})->with([
    'super admin' => ['super_admin', 'super-admin'],
    'admin' => ['admin', 'admin'],
    'manager' => ['manager', 'manager'],
    'attendance monitor' => ['attendance_monitor', 'manager'],
    'course lecturer' => ['course_lecturer', 'course_lecturer'],
    'student' => ['student', 'student'],
]);

it('replaces a previous system role while preserving unrelated roles', function (): void {
    $user = User::factory()->create(['role' => 'super_admin']);
    $user->assignRole(['super-admin', 'optional-role']);

    $user->syncSystemRole('admin');

    expect($user->fresh()->hasRole('super-admin'))->toBeFalse()
        ->and($user->fresh()->hasRole('admin'))->toBeTrue()
        ->and($user->fresh()->hasRole('optional-role'))->toBeTrue();
});

it('does not grant authorization from a classification alone and rejects unsupported classifications', function (): void {
    $user = User::factory()->create(['role' => 'super_admin']);

    expect($user->hasRole('super-admin'))->toBeFalse()
        ->and($user->isSuperAdmin())->toBeFalse()
        ->and($user->isAdmin())->toBeFalse()
        ->and(fn () => $user->syncSystemRole('unknown'))->toThrow(InvalidArgumentException::class);

    $user->syncSystemRole('super_admin');

    expect($user->fresh()->hasRole('super-admin'))->toBeTrue()
        ->and($user->fresh()->isSuperAdmin())->toBeTrue();
});

it('preserves existing roles when a new classification is unsupported', function (): void {
    $user = User::factory()->create(['role' => 'manager']);
    $user->assignRole(['manager', 'optional-role']);

    expect(fn () => $user->syncSystemRole('unknown'))->toThrow(InvalidArgumentException::class)
        ->and($user->fresh()->hasRole('manager'))->toBeTrue()
        ->and($user->fresh()->hasRole('optional-role'))->toBeTrue();
});

it('maps students to the non-administrative student role', function (): void {
    $user = User::factory()->create(['role' => 'student']);

    $user->syncSystemRole('student');

    expect($user->fresh()->hasRole('student'))->toBeTrue()
        ->and($user->fresh()->canAccessFilament())->toBeFalse()
        ->and($user->fresh()->isAdmin())->toBeFalse()
        ->and($user->fresh()->isSuperAdmin())->toBeFalse();
});
