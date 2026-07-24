<?php

use App\Models\Lecturer;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('defaults to a zero-write lecturer password reset preview', function (): void {
    Role::firstOrCreate(['name' => 'course_lecturer', 'guard_name' => 'web']);
    $user = User::factory()->create(['login_username' => 'nada187', 'role' => 'course_lecturer', 'type' => 'lecturer', 'status' => 'active', 'is_active' => true]);
    $user->assignRole('course_lecturer');
    Lecturer::query()->create(['name' => 'ندى محمد محمود', 'canonical_name' => 'ندى محمد محمود', 'user_id' => $user->id, 'is_active' => true]);
    $before = User::findOrFail($user->id)->password;

    $this->artisan('lecturers:reset-passwords')
        ->expectsOutputToContain('هذه معاينة فقط')
        ->assertExitCode(0);

    expect($user->fresh()->password)->toBe($before);
});

it('refuses execution without the explicit confirmation phrase', function (): void {
    $this->artisan('lecturers:reset-passwords', ['--execute' => true])
        ->expectsOutput('Execution requires --confirm=RESET_LECTURER_PASSWORDS.')
        ->assertExitCode(2);
});
