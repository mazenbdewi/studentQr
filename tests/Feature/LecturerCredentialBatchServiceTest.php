<?php

use App\Models\User;
use App\Services\LecturerCredentialBatchService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    config()->set('services.lecturer_credentials.key', str_repeat('k', 48));
    config()->set('services.lecturer_credentials.key_version', 'test-v1');
    Storage::fake('local');
});

it('stores encrypted workbooks, decrypts them, and keeps audit metadata safe', function (): void {
    $actor = User::factory()->create(['role' => 'admin', 'type' => 'admin', 'status' => 'active', 'is_active' => true]);
    $service = app(LecturerCredentialBatchService::class);
    $batch = $service->create('initial_accounts', [['lecturer_name' => 'ندى', 'login_username' => 'nada187', 'temporary_password' => 'Secret123!']], null, $actor);

    expect(Storage::disk('local')->exists($batch->encrypted_file_path))->toBeTrue()
        ->and(substr(Storage::disk('local')->get($batch->encrypted_file_path), 0, 2))->not->toBe('PK')
        ->and(substr($service->decryptedContents($batch), 0, 2))->toBe('PK');

    $service->audit($batch, 'download_prepared', $actor, ['password' => 'Secret123!', 'filename' => 'safe.xlsx', 'path' => '/tmp/plain.xlsx']);
    $action = $batch->actions()->sole();
    expect($action->safe_metadata)->toBe(['filename' => 'safe.xlsx']);
});

it('safely deletes only the selected encrypted batch and is idempotent', function (): void {
    $actor = User::factory()->create(['role' => 'admin', 'type' => 'admin', 'status' => 'active', 'is_active' => true]);
    $service = app(LecturerCredentialBatchService::class);
    $first = $service->create('initial_accounts', [], null, $actor);
    $second = $service->create('initial_accounts', [], null, $actor);
    $secondPath = $second->encrypted_file_path;
    $service->delete($first, $actor);
    $service->delete($first->fresh(), $actor);
    expect($first->fresh()->status)->toBe('deleted')->and($first->fresh()->encrypted_file_path)->toBeNull()->and(Storage::disk('local')->exists($secondPath))->toBeTrue()->and($first->actions()->where('action', 'deleted')->count())->toBe(1);
});

it('seeds the credential permission matrix idempotently', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);
    $names = ['view lecturer credential batches', 'download lecturer credential batches', 'delete lecturer credential batches', 'reset lecturer passwords'];
    expect(Permission::whereIn('name', $names)->count())->toBe(4)
        ->and(Role::findByName('admin')->hasPermissionTo('view lecturer credential batches'))->toBeTrue()
        ->and(Role::findByName('admin')->hasPermissionTo('delete lecturer credential batches'))->toBeFalse()
        ->and(Role::findByName('super-admin')->hasPermissionTo('delete lecturer credential batches'))->toBeTrue()
        ->and(Role::findByName('manager')->hasPermissionTo('view lecturer credential batches'))->toBeFalse();
});
