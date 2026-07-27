<?php

use App\Livewire\Filament\Profile\UpdatePassword;
use App\Livewire\Filament\Profile\UsernamePersonalInfo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['admin', 'course_lecturer'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    Permission::findOrCreate('manage hall metadata', 'web');
});

it('opens the username-only profile page for administrative users without accessing a user email', function (): void {
    $user = profileUser('admin', 'profile_admin');
    $queries = [];

    DB::listen(function ($query) use (&$queries): void {
        $queries[] = strtolower($query->sql);
    });

    $this->actingAs($user)
        ->get('/admin/my-profile')
        ->assertOk()
        ->assertSee('المعلومات الشخصية')
        ->assertSee('الاسم الكامل')
        ->assertSee('اسم المستخدم')
        ->assertSee('profile_admin')
        ->assertDontSee('email', false);

    expect(collect($queries)->contains(fn (string $query): bool => str_contains($query, 'email')))->toBeFalse();
});

it('opens the username-only profile page for course lecturers', function (): void {
    $lecturer = profileUser('course_lecturer', 'profile_lecturer');

    $this->actingAs($lecturer)
        ->get('/admin/my-profile')
        ->assertOk()
        ->assertSee('profile_lecturer')
        ->assertDontSee('email', false);
});

it('updates only the authenticated user name and preserves the login username', function (): void {
    $user = profileUser('admin', 'profile_editor');
    $otherUser = profileUser('admin', 'profile_other');
    $queries = [];

    DB::listen(function ($query) use (&$queries): void {
        $queries[] = strtolower($query->sql);
    });

    Livewire::actingAs($user)
        ->test(UsernamePersonalInfo::class)
        ->fillForm(['name' => 'Updated Profile Name'])
        ->call('submit')
        ->assertHasNoFormErrors();

    expect($user->fresh()->name)->toBe('Updated Profile Name')
        ->and($user->fresh()->login_username)->toBe('profile_editor')
        ->and($otherUser->fresh()->name)->not->toBe('Updated Profile Name')
        ->and(collect($queries)->contains(fn (string $query): bool => str_contains($query, 'email')))->toBeFalse();
});

it('keeps the profile password-change component working without an email field', function (): void {
    $user = profileUser('admin', 'profile_password', 'current-password');

    Livewire::actingAs($user)
        ->test(UpdatePassword::class)
        ->fillForm([
            'current_password' => 'current-password',
            'new_password' => 'new-secure-password',
            'new_password_confirmation' => 'new-secure-password',
        ])
        ->call('submit')
        ->assertHasNoFormErrors();

    expect(Hash::check('new-secure-password', (string) $user->fresh()->password))->toBeTrue();
});

function profileUser(string $role, string $loginUsername, string $password = 'password'): User
{
    $user = User::factory()->create([
        'name' => 'Profile '.$loginUsername,
        'login_username' => $loginUsername,
        'password' => Hash::make($password),
        'role' => $role,
        'must_change_password' => false,
        'status' => 'active',
        'is_active' => true,
    ]);
    $user->assignRole($role);

    return $user;
}
