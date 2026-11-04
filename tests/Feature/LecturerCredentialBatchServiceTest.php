<?php

use App\Models\LecturerCredentialBatch;
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

it('stores only recipient IDs and regenerates the workbook from current state', function (): void {
    $actor = User::factory()->create(['role' => 'admin', 'type' => 'admin', 'status' => 'active', 'is_active' => true]);
    $recipient = User::factory()->create(['login_username' => 'nada187', 'must_change_password' => false]);
    $service = app(LecturerCredentialBatchService::class);
    $batch = $service->create('password_reset', [$recipient->id], null, $actor);

    expect($batch->encrypted_file_path)->toBeNull()
        ->and($batch->recipient_user_ids)->toBe([$recipient->id])
        ->and(substr($service->decryptedContents($batch), 0, 2))->toBe('PK');
    expect(Storage::disk('local')->allFiles('lecturer-credentials'))->toBe([]);

    $service->audit($batch, 'download_prepared', $actor, ['password' => 'Secret123!', 'filename' => 'safe.xlsx', 'path' => '/tmp/plain.xlsx']);
    $action = $batch->actions()->sole();
    expect($action->safe_metadata)->toBe(['filename' => 'safe.xlsx']);
});

it('safely deletes only the selected metadata batch and is idempotent', function (): void {
    $actor = User::factory()->create(['role' => 'admin', 'type' => 'admin', 'status' => 'active', 'is_active' => true]);
    $service = app(LecturerCredentialBatchService::class);
    $first = $service->create('initial_accounts', [], null, $actor);
    $second = $service->create('initial_accounts', [], null, $actor);
    $service->delete($first, $actor);
    $service->delete($first->fresh(), $actor);
    expect($first->fresh()->status)->toBe('deleted')->and($first->fresh()->encrypted_file_path)->toBeNull()->and($second->fresh()->status)->toBe('available')->and($first->actions()->where('action', 'deleted')->count())->toBe(1);
});

it('refuses to download a historical workbook without recipient IDs', function (): void {
    $actor = User::factory()->create();
    Storage::disk('local')->put('lecturer-credentials/legacy.enc', 'old encrypted workbook');
    $batch = LecturerCredentialBatch::query()->create([
        'batch_type' => 'password_reset', 'original_filename' => 'legacy.xlsx',
        'encrypted_file_path' => 'lecturer-credentials/legacy.enc', 'sha256' => hash('sha256', 'legacy'),
        'encryption_key_version' => 'test-v1', 'record_count' => 1,
        'generated_by' => $actor->id, 'generated_at' => now(), 'status' => 'available',
    ]);

    expect(fn () => app(LecturerCredentialBatchService::class)->decryptedContents($batch))->toThrow(RuntimeException::class)
        ->and(Storage::disk('local')->exists('lecturer-credentials/legacy.enc'))->toBeTrue();
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
