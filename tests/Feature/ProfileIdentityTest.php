<?php

use App\Models\Lecturer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['manager', 'course_lecturer', 'student'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

it('displays the login username and not an authentication email on each existing profile page', function (string $role, string $routeName, string $username): void {
    $user = profileIdentityUser($role, $username);

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertSuccessful()
        ->assertSee('اسم المستخدم: '.$username)
        ->assertDontSee('email', false);
})->with([
    'student profile' => ['student', 'student.profile.edit', 'student_profile'],
    'manager profile' => ['manager', 'manager.profile', 'manager_profile'],
    'lecturer profile' => ['course_lecturer', 'teacher.profile', 'lecturer_profile'],
]);

it('updates profile details without querying or saving a removed user email column', function (string $role, string $routeName, string $username): void {
    $user = profileIdentityUser($role, $username);
    $queries = [];

    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->actingAs($user)
        ->put(route($routeName), ['name' => 'Updated Profile', 'phone' => '0999999999'])
        ->assertRedirect();

    expect($user->fresh()->name)->toBe('Updated Profile')
        ->and($user->fresh()->phone)->toBe('0999999999')
        ->and(collect($queries)->contains(fn (string $query): bool => str_contains(strtolower($query), 'email')))->toBeFalse();
})->with([
    'student profile update' => ['student', 'student.profile.update', 'student_profile_update'],
    'manager profile update' => ['manager', 'manager.profile.update', 'manager_profile_update'],
    'lecturer profile update' => ['course_lecturer', 'teacher.profile.update', 'lecturer_profile_update'],
]);

it('keeps lecturer contact email on the separate lecturer model', function (): void {
    $user = profileIdentityUser('course_lecturer', 'lecturer_contact');
    $lecturer = Lecturer::query()->create([
        'user_id' => $user->id,
        'lecturer_id' => 'LEC-CONTACT-001',
        'name' => 'Lecturer Contact',
        'email' => 'lecturer.contact@example.test',
        'is_active' => true,
    ]);

    expect($lecturer->fresh()->email)->toBe('lecturer.contact@example.test')
        ->and($lecturer->user->login_username)->toBe('lecturer_contact');
});

function profileIdentityUser(string $role, string $loginUsername): User
{
    $user = User::factory()->create([
        'login_username' => $loginUsername,
        'role' => $role,
        'phone' => '0900000000',
        'status' => 'active',
    ]);
    $user->assignRole($role);

    return $user;
}
