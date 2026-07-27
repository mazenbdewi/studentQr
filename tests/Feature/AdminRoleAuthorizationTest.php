<?php

use App\Models\User;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    config(['filesystems.disks.database_backups.root' => adminRoleBackupTestDirectory()]);

    File::ensureDirectoryExists(adminRoleBackupTestDirectory(), 0750);
    File::cleanDirectory(adminRoleBackupTestDirectory());
});

function adminRoleBackupTestDirectory(): string
{
    return storage_path('framework/testing/backups/admin-role');
}

function createRoleSuperAdmin(): User
{
    $user = User::factory()->create([
        'login_username' => 'role-super-admin',
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);

    $user->assignRole('super-admin');

    return $user;
}

function createRoleAdmin(): User
{
    $user = User::factory()->create([
        'login_username' => 'role-admin',
        'role' => 'admin',
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);

    $user->assignRole('admin');

    return $user;
}

function createAdminRoleBackupFile(): string
{
    $fileName = 'database-backup-2026-05-07-18-30.zip';

    File::put(adminRoleBackupTestDirectory().DIRECTORY_SEPARATOR.$fileName, 'database backup contents');

    return $fileName;
}

it('allows super admins to access sensitive and operational admin pages', function (): void {
    $user = createRoleSuperAdmin();
    $fileName = createAdminRoleBackupFile();

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk()
        ->assertSee(__('filament-dashboard.users'))
        ->assertSee(__('database-backups.title'))
        ->assertSee(__('filament-dashboard.activity_logs'));

    $this->actingAs($user)->get('/admin/users')->assertOk();
    $this->actingAs($user)->get('/admin/users/create')->assertOk();
    $this->actingAs($user)->get('/admin/database-backups')->assertOk();
    $this->actingAs($user)->get(route('admin.database-backups.download', $fileName))->assertOk();
    $this->actingAs($user)->get('/admin/audit-logs')->assertOk();
    $this->actingAs($user)->get('/admin/faculties')->assertOk();
    $this->actingAs($user)->get('/admin/departments')->assertOk();
    $this->actingAs($user)->get('/admin/students')->assertOk();
    $this->actingAs($user)->get('/admin/subjects')->assertOk();
    $this->actingAs($user)->get('/admin/halls')->assertOk();
    $this->actingAs($user)->get('/admin/lecture-sessions')->assertOk();
});

it('blocks admins from sensitive pages and keeps operational pages available', function (): void {
    $user = createRoleAdmin();
    $fileName = createAdminRoleBackupFile();

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk()
        ->assertDontSee(__('filament-dashboard.users'))
        ->assertDontSee(__('database-backups.title'))
        ->assertDontSee(__('filament-dashboard.activity_logs'));

    $this->actingAs($user)->get('/admin/users')->assertForbidden();
    $this->actingAs($user)->get('/admin/users/create')->assertForbidden();
    $this->actingAs($user)->get('/admin/database-backups')->assertForbidden();
    $this->actingAs($user)->get(route('admin.database-backups.download', $fileName))->assertForbidden();
    $this->actingAs($user)->get('/admin/audit-logs')->assertForbidden();

    $this->actingAs($user)->get('/admin/faculties')->assertOk();
    $this->actingAs($user)->get('/admin/departments')->assertOk();
    $this->actingAs($user)->get('/admin/students')->assertOk();
    $this->actingAs($user)->get('/admin/subjects')->assertOk();
    $this->actingAs($user)->get('/admin/halls')->assertOk();
    $this->actingAs($user)->get('/admin/lecture-sessions')->assertOk();
});
