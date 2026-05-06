<?php

use App\Filament\Pages\DatabaseBackups;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

    config(['filesystems.disks.database_backups.root' => databaseBackupTestDirectory()]);

    File::ensureDirectoryExists(databaseBackupTestDirectory(), 0750);
    File::cleanDirectory(databaseBackupTestDirectory());
});

function databaseBackupTestDirectory(): string
{
    return storage_path('framework/testing/backups/database');
}

function createDatabaseBackupSuperAdmin(): User
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

function createDatabaseBackupManager(): User
{
    $user = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'manager',
        'status' => 'active',
    ]);

    $user->assignRole('manager');

    return $user;
}

function createPrivateDatabaseBackupFile(): string
{
    $fileName = 'database-backup-2026-05-07-14-30.zip';

    File::put(databaseBackupTestDirectory().DIRECTORY_SEPARATOR.$fileName, 'database backup contents');

    return $fileName;
}

it('loads the database backups page for the super admin', function () {
    $fileName = createPrivateDatabaseBackupFile();
    $user = createDatabaseBackupSuperAdmin();

    $this->actingAs($user)
        ->get('/admin/database-backups')
        ->assertOk()
        ->assertSee(__('database-backups.title'))
        ->assertSee($fileName);
});

it('blocks non super admin users from the database backups page', function () {
    $user = createDatabaseBackupManager();

    $this->actingAs($user)
        ->get('/admin/database-backups')
        ->assertForbidden();
});

it('downloads database backup files only through the protected admin route', function () {
    $fileName = createPrivateDatabaseBackupFile();
    $superAdmin = createDatabaseBackupSuperAdmin();
    $manager = createDatabaseBackupManager();

    $this->actingAs($manager)
        ->get(route('admin.database-backups.download', $fileName))
        ->assertForbidden();

    $this->actingAs($superAdmin)
        ->get(route('admin.database-backups.download', $fileName))
        ->assertOk()
        ->assertDownload($fileName);
});

it('deletes a backup from the protected page action', function () {
    $fileName = createPrivateDatabaseBackupFile();
    $user = createDatabaseBackupSuperAdmin();

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($user)
        ->test(DatabaseBackups::class)
        ->call('deleteBackup', $fileName);

    expect(File::exists(databaseBackupTestDirectory().DIRECTORY_SEPARATOR.$fileName))->toBeFalse();
});
