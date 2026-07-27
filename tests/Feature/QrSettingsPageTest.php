<?php

use App\Filament\Pages\PortalSettings;
use App\Filament\Resources\LectureSessions\LectureSessionResource;
use App\Models\AppSetting;
use App\Models\LectureSession;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'course_lecturer', 'guard_name' => 'web']);
});

function createSuperAdminForQrSettings(): User
{
    $user = User::factory()->create([
        'login_username' => 'qr_super_admin',
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
        ->assertSee(__('settings.qr_base_url'))
        ->assertSee(__('settings.lecturer_can_edit_lecture_sessions'))
        ->assertSee(__('settings.lecturer_can_delete_lecture_sessions'));
});

it('saves qr_base_url into app_settings from the settings page', function () {
    $user = createSuperAdminForQrSettings();

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($user)
        ->test(PortalSettings::class)
        ->set('data.qr_base_url', 'http://192.168.1.103:8089')
        ->set('data.lecturer_can_edit_lecture_sessions', true)
        ->set('data.lecturer_can_delete_lecture_sessions', true)
        ->call('save');

    expect(AppSetting::value('qr_base_url'))->toBe('http://192.168.1.103:8089')
        ->and(AppSetting::lecturerCanEditLectureSessions())->toBeTrue()
        ->and(AppSetting::lecturerCanDeleteLectureSessions())->toBeTrue();
});

it('uses the QR settings toggles for course lecturer session edit and delete permissions', function () {
    $lecturer = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
    ]);
    $lecturer->assignRole('course_lecturer');

    $session = new LectureSession(['lecturer_id' => $lecturer->id]);

    $this->actingAs($lecturer);

    expect(LectureSessionResource::canCurrentUserEditLectureSession($session))->toBeFalse()
        ->and(LectureSessionResource::canCurrentUserDeleteLectureSession($session))->toBeFalse();

    AppSetting::putBoolean(AppSetting::LECTURER_CAN_EDIT_LECTURE_SESSIONS_KEY, true);
    AppSetting::putBoolean(AppSetting::LECTURER_CAN_DELETE_LECTURE_SESSIONS_KEY, true);

    expect(LectureSessionResource::canCurrentUserEditLectureSession($session))->toBeTrue()
        ->and(LectureSessionResource::canCurrentUserDeleteLectureSession($session))->toBeTrue();
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
