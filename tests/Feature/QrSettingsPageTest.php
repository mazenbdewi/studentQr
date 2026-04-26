<?php

use App\Filament\Pages\PortalSettings;
use App\Models\AppSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
});

function createSuperAdminForQrSettings(): User
{
    $user = User::factory()->create([
        'email' => 'super@admin.com',
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
    ]);

    $user->assignRole('super-admin');

    return $user;
}

it('loads the QR settings page for the super admin', function () {
    $user = createSuperAdminForQrSettings();

    $this->actingAs($user)
        ->get('/admin/qr-settings')
        ->assertOk()
        ->assertSee(__('settings.qr_base_url'));
});

it('saves qr_base_url into app_settings from the settings page', function () {
    $user = createSuperAdminForQrSettings();

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($user)
        ->test(PortalSettings::class)
        ->set('data.qr_base_url', 'http://192.168.1.103:8089')
        ->call('save');

    expect(AppSetting::value('qr_base_url'))->toBe('http://192.168.1.103:8089');
});

it('blocks non super admin users from the QR settings page', function () {
    $user = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get('/admin/qr-settings')
        ->assertForbidden();
});
