<?php

use App\Filament\Pages\Auth\Login as FilamentLogin;
use App\Filament\Resources\Users\UserResource;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\PinLoginService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'course_lecturer', 'guard_name' => 'web']);

    AppSetting::putBoolean(PinLoginService::SETTING_KEY, false);
});

function pinSecurityUser(string $role = 'super_admin', ?string $pin = null): User
{
    $user = User::factory()->create([
        'login_username' => strtolower(fake()->unique()->bothify('pin####??')),
        'student_number' => fake()->unique()->numerify('########'),
        'password' => Hash::make('password'),
        'role' => $role,
        'type' => $role === 'super_admin' ? 'admin' : 'lecturer',
        'status' => 'active',
        'is_active' => true,
        'pin_code' => $pin ? Hash::make($pin) : null,
        'pin_enabled' => true,
    ]);

    $user->assignRole(User::mapDatabaseRoleToSpatieRole($role));

    return $user;
}

it('serves the real filament admin login page', function (): void {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('اسم المستخدم');
});

it('logs in without pin when pin login is disabled', function (): void {
    $user = pinSecurityUser();

    $this->post('/login', [
        'login' => $user->login_username,
        'password' => 'password',
    ])
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);
});

it('requires pin when pin login is enabled', function (): void {
    AppSetting::putBoolean(PinLoginService::SETTING_KEY, true);
    $user = pinSecurityUser(pin: '123456');

    $this->post('/login', [
        'login' => $user->login_username,
        'password' => 'password',
    ])
        ->assertRedirect(route('pin.verify.form'));

    $this->assertAuthenticatedAs($user);
});

it('requires users without a pin to set one when pin login is enabled', function (): void {
    AppSetting::putBoolean(PinLoginService::SETTING_KEY, true);
    $user = pinSecurityUser();

    $this->post('/login', [
        'login' => $user->login_username,
        'password' => 'password',
    ])
        ->assertRedirect(route('pin.set.form'));

    $this->assertAuthenticatedAs($user);

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect(route('pin.set.form'));
});

it('requires pin when a stored pin exists even if the legacy enabled flag is false', function (): void {
    AppSetting::putBoolean(PinLoginService::SETTING_KEY, true);
    $user = pinSecurityUser(pin: '123456');
    $user->forceFill(['pin_enabled' => false])->saveQuietly();

    $this->post('/login', [
        'login' => $user->login_username,
        'password' => 'password',
    ])
        ->assertRedirect(route('pin.verify.form'));

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect(route('pin.verify.form'));
});

it('redirects from the real filament admin login to pin verification when required', function (): void {
    AppSetting::putBoolean(PinLoginService::SETTING_KEY, true);
    $user = pinSecurityUser(pin: '123456');

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(FilamentLogin::class)
        ->set('data.login_username', $user->login_username)
        ->set('data.password', 'password')
        ->call('authenticate')
        ->assertRedirect(route('pin.verify.form'));

    $this->assertAuthenticatedAs($user);
    expect(session(PinLoginService::SESSION_VERIFIED))->not->toBeTrue();
});

it('redirects from the real filament admin login to pin setup when no pin exists', function (): void {
    AppSetting::putBoolean(PinLoginService::SETTING_KEY, true);
    $user = pinSecurityUser();

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(FilamentLogin::class)
        ->set('data.login_username', $user->login_username)
        ->set('data.password', 'password')
        ->call('authenticate')
        ->assertRedirect(route('pin.set.form'));

    $this->assertAuthenticatedAs($user);
    expect(session(PinLoginService::SESSION_VERIFIED))->not->toBeTrue();
});

it('blocks direct filament admin pages until pin verification succeeds', function (): void {
    AppSetting::putBoolean(PinLoginService::SETTING_KEY, true);
    $user = pinSecurityUser(pin: '123456');

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect(route('pin.verify.form'));

    $this->actingAs($user)
        ->get('/admin/users')
        ->assertRedirect(route('pin.verify.form'));

    $this->actingAs($user)
        ->get('/admin/lecture-sessions')
        ->assertRedirect(route('pin.verify.form'));

    $this->actingAs($user)
        ->get('/admin/dashboard')
        ->assertRedirect(route('pin.verify.form'));
});

it('fails login with an incorrect pin', function (): void {
    AppSetting::putBoolean(PinLoginService::SETTING_KEY, true);
    $user = pinSecurityUser(pin: '123456');

    $this->post('/login', [
        'login' => $user->login_username,
        'password' => 'password',
    ])
        ->assertRedirect(route('pin.verify.form'));

    $this->post(route('pin.verify'), [
        'pin_code' => '654321',
    ])
        ->assertSessionHasErrors('pin_code');

    $this->assertAuthenticatedAs($user);
    expect(session(PinLoginService::SESSION_VERIFIED))->not->toBeTrue();
});

it('logs in with the correct pin when pin login is enabled', function (): void {
    AppSetting::putBoolean(PinLoginService::SETTING_KEY, true);
    $user = pinSecurityUser(pin: '123456');

    $this->post('/login', [
        'login' => $user->login_username,
        'password' => 'password',
    ])
        ->assertRedirect(route('pin.verify.form'));

    $this->post(route('pin.verify'), [
        'pin_code' => '123456',
    ])
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);
    expect(session(PinLoginService::SESSION_VERIFIED))->toBeTrue()
        ->and(session(PinLoginService::SESSION_VERIFIED_AT))->not->toBeNull();
});

it('redirects direct dashboard access to pin verification when pin is not verified', function (): void {
    AppSetting::putBoolean(PinLoginService::SETTING_KEY, true);
    $user = pinSecurityUser(pin: '123456');

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect(route('pin.verify.form'));
});

it('allows dashboard access after pin verification', function (): void {
    AppSetting::putBoolean(PinLoginService::SETTING_KEY, true);
    $user = pinSecurityUser(pin: '123456');

    $this->actingAs($user)
        ->post(route('pin.verify'), [
            'pin_code' => '123456',
        ])
        ->assertRedirect('/admin');

    $this->get('/admin')->assertOk();
});

it('sets a missing pin and allows dashboard access when global pin login is enabled', function (): void {
    AppSetting::putBoolean(PinLoginService::SETTING_KEY, true);
    $user = pinSecurityUser();

    $this->post('/login', [
        'login' => $user->login_username,
        'password' => 'password',
    ])
        ->assertRedirect(route('pin.set.form'));

    $this->assertAuthenticatedAs($user);

    $this->post(route('pin.set'), [
        'new_pin' => '112233',
        'new_pin_confirmation' => '112233',
    ])
        ->assertRedirect('/admin');

    $user->refresh();

    expect($user->hasPinCode())->toBeTrue()
        ->and(Hash::check('112233', $user->pin_code))->toBeTrue()
        ->and(session(PinLoginService::SESSION_VERIFIED))->toBeTrue()
        ->and(session(PinLoginService::SESSION_VERIFIED_AT))->not->toBeNull();

    $this->get('/admin')->assertOk();
});

it('does not redirect to the livewire update endpoint after setting pin', function (): void {
    AppSetting::putBoolean(PinLoginService::SETTING_KEY, true);
    $user = pinSecurityUser();

    $this->actingAs($user)
        ->withSession(['url.intended' => url('/livewire/update')])
        ->post(route('pin.set'), [
            'new_pin' => '112233',
            'new_pin_confirmation' => '112233',
        ])
        ->assertRedirect('/admin');
});

it('does not redirect to the livewire update endpoint after verifying pin', function (): void {
    AppSetting::putBoolean(PinLoginService::SETTING_KEY, true);
    $user = pinSecurityUser(pin: '123456');

    $this->actingAs($user)
        ->withSession(['url.intended' => url('/livewire/update')])
        ->post(route('pin.verify'), [
            'pin_code' => '123456',
        ])
        ->assertRedirect('/admin');
});

it('changes password independently from pin', function (): void {
    $user = pinSecurityUser('course_lecturer', '123456');
    $oldPinHash = $user->pin_code;

    $this->actingAs($user)
        ->put(route('teacher.profile.password.update'), [
            'current_password' => 'password',
            'new_password' => 'new-password',
            'new_password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect(Hash::check('new-password', $user->password))->toBeTrue()
        ->and($user->pin_code)->toBe($oldPinHash);
});

it('changes pin independently from password and stores it hashed', function (): void {
    $user = pinSecurityUser('course_lecturer', '123456');
    $oldPasswordHash = $user->password;

    $this->actingAs($user)
        ->put(route('teacher.profile.pin.update'), [
            'current_password' => 'password',
            'old_pin' => '123456',
            'new_pin' => '112233',
            'new_pin_confirmation' => '112233',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->password)->toBe($oldPasswordHash)
        ->and($user->pin_code)->not->toBe('112233')
        ->and(Hash::check('112233', $user->pin_code))->toBeTrue();
});

it('requires a fresh pin verification after pin changes in an active session', function (): void {
    AppSetting::putBoolean(PinLoginService::SETTING_KEY, true);
    $user = pinSecurityUser('course_lecturer', '123456');

    $this->actingAs($user)
        ->post(route('pin.verify'), [
            'pin_code' => '123456',
        ])
        ->assertRedirect('/admin');

    $this->put(route('teacher.profile.pin.update'), [
        'current_password' => 'password',
        'old_pin' => '123456',
        'new_pin' => '112233',
        'new_pin_confirmation' => '112233',
    ])
        ->assertSessionHasNoErrors();

    $this->get('/admin')
        ->assertRedirect(route('pin.verify.form'));
});

it('does not allow non super admins to manage other users pins', function (): void {
    $superAdmin = pinSecurityUser();
    $lecturer = pinSecurityUser('course_lecturer');

    expect(UserResource::canManagePins($superAdmin))->toBeTrue()
        ->and(UserResource::canManagePins($lecturer))->toBeFalse();
});
