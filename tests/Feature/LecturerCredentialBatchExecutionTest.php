<?php

use App\Filament\Pages\LecturerCredentialBatches;
use App\Models\LecturerCredentialBatch;
use App\Models\User;
use App\Services\LecturerCredentialBatchService;
use Filament\Facades\Filament;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    config()->set('services.lecturer_credentials.key', str_repeat('x', 48));
    config()->set('services.lecturer_credentials.key_version', 'exec-v1');
    Storage::fake('local');

    foreach (['admin', 'super-admin', 'manager', 'course_lecturer'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    foreach (['view lecturer credential batches', 'download lecturer credential batches', 'delete lecturer credential batches'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function credentialExecutionUser(string $role, array $permissions = []): User
{
    $user = User::factory()->create([
        'role' => $role,
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);
    $user->assignRole($role);
    $user->givePermissionTo($permissions);

    return $user;
}

function credentialExecutionBatch(User $actor): LecturerCredentialBatch
{
    return app(LecturerCredentialBatchService::class)->create('initial_accounts', [[
        'lecturer_name' => 'ندى',
        'login_username' => 'nada187',
        'temporary_password' => 'Secret123!',
    ]], null, $actor);
}

function credentialExecutionPage(): LecturerCredentialBatches
{
    return app(LecturerCredentialBatches::class);
}

function expectCredentialDownloadFailure(LecturerCredentialBatch $batch, User $user): void
{
    $ciphertext = $batch->encrypted_file_path
        ? Storage::disk('local')->get($batch->encrypted_file_path)
        : null;

    test()->actingAs($user);

    try {
        credentialExecutionPage()->download($batch->id, app(LecturerCredentialBatchService::class));
        test()->fail('The credential download should have been rejected.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(422)
            ->and($exception->getMessage())->toBe('تعذر تحضير ملف بيانات الدخول بأمان.')
            ->and($exception->getMessage())->not->toContain('lecturer-credentials')
            ->and($exception->getMessage())->not->toContain('aes')
            ->and($batch->fresh()->downloaded_count)->toBe(0)
            ->and($batch->fresh()->last_downloaded_at)->toBeNull()
            ->and($batch->actions()->where('action', 'download_failed')->count())->toBe(1);
    }

    if ($batch->encrypted_file_path !== null) {
        expect(Storage::disk('local')->get($batch->encrypted_file_path))->toBe($ciphertext);
    }

    expect(Storage::disk('local')->allFiles('lecturer-credentials'))
        ->each->toEndWith('.enc');
}

it('executes a secure page download, returns an xlsx stream, and audits it', function (): void {
    $admin = credentialExecutionUser('admin', [
        'view lecturer credential batches',
        'download lecturer credential batches',
    ]);
    $batch = credentialExecutionBatch($admin);
    $ciphertext = Storage::disk('local')->get($batch->encrypted_file_path);

    $this->actingAs($admin);
    $response = credentialExecutionPage()->download($batch->id, app(LecturerCredentialBatchService::class));
    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    expect($response->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($response->headers->get('content-disposition'))
        ->toContain("filename*=utf-8''".rawurlencode($batch->original_filename))
        ->and(substr($body, 0, 2))->toBe('PK')
        ->and(Storage::disk('local')->get($batch->encrypted_file_path))->toBe($ciphertext)
        ->and($batch->fresh()->downloaded_count)->toBe(1)
        ->and($batch->fresh()->last_downloaded_at)->not->toBeNull()
        ->and($batch->actions()->where('action', 'download_prepared')->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles('lecturer-credentials'))->each->toEndWith('.enc');
});

it('rejects independently corrupted, missing, mismatched, deleted, and unavailable-key downloads', function (string $failure): void {
    $admin = credentialExecutionUser('admin', [
        'view lecturer credential batches',
        'download lecturer credential batches',
    ]);
    $batch = credentialExecutionBatch($admin);

    match ($failure) {
        'missing_file' => Storage::disk('local')->delete($batch->encrypted_file_path),
        'sha_mismatch' => $batch->forceFill(['sha256' => str_repeat('0', 64)])->save(),
        'corrupt_ciphertext' => Storage::disk('local')->put($batch->encrypted_file_path, 'corrupt'),
        'wrong_key_version' => $batch->forceFill(['encryption_key_version' => 'retired-v0'])->save(),
        'missing_dedicated_key' => config()->set('services.lecturer_credentials.key', null),
        'missing_active_path' => $batch->forceFill(['encrypted_file_path' => null])->save(),
        'deleted' => $batch->forceFill(['status' => 'deleted'])->save(),
    };

    expectCredentialDownloadFailure($batch->fresh(), $admin);
})->with([
    'missing encrypted file' => 'missing_file',
    'sha256 mismatch' => 'sha_mismatch',
    'corrupted ciphertext' => 'corrupt_ciphertext',
    'wrong key version' => 'wrong_key_version',
    'missing dedicated key' => 'missing_dedicated_key',
    'missing active encrypted path' => 'missing_active_path',
    'deleted batch' => 'deleted',
]);

it('executes selected safe delete without touching another batch and is idempotent', function (): void {
    $superAdmin = credentialExecutionUser('super-admin', [
        'view lecturer credential batches',
        'download lecturer credential batches',
        'delete lecturer credential batches',
    ]);
    $selected = credentialExecutionBatch($superAdmin);
    $other = credentialExecutionBatch($superAdmin);
    $selectedPath = $selected->encrypted_file_path;
    $otherPath = $other->encrypted_file_path;

    $this->actingAs($superAdmin);
    credentialExecutionPage()->secureDelete($selected->id, app(LecturerCredentialBatchService::class));
    credentialExecutionPage()->secureDelete($selected->id, app(LecturerCredentialBatchService::class));

    expect(Storage::disk('local')->exists($selectedPath))->toBeFalse()
        ->and(Storage::disk('local')->exists($otherPath))->toBeTrue()
        ->and($selected->fresh()->status)->toBe('deleted')
        ->and($selected->fresh()->encrypted_file_path)->toBeNull()
        ->and($selected->fresh()->deleted_at)->not->toBeNull()
        ->and($selected->fresh()->deleted_by)->toBe($superAdmin->id)
        ->and($selected->fresh()->original_filename)->not->toBeEmpty()
        ->and($selected->actions()->where('action', 'deleted')->count())->toBe(1);

    expectCredentialDownloadFailure($selected->fresh(), $superAdmin);
});

it('rejects traversal and outside paths without changing either batch', function (string $unsafePath): void {
    $superAdmin = credentialExecutionUser('super-admin', ['delete lecturer credential batches']);
    $selected = credentialExecutionBatch($superAdmin);
    $other = credentialExecutionBatch($superAdmin);
    $otherPath = $other->encrypted_file_path;
    $selected->forceFill(['encrypted_file_path' => $unsafePath])->save();

    $this->actingAs($superAdmin);
    expect(fn () => credentialExecutionPage()->secureDelete($selected->id, app(LecturerCredentialBatchService::class)))
        ->toThrow(HttpException::class);

    expect($selected->fresh()->status)->toBe('available')
        ->and($selected->fresh()->encrypted_file_path)->toBe($unsafePath)
        ->and($selected->actions()->where('action', 'delete_failed')->count())->toBe(1)
        ->and(Storage::disk('local')->exists($otherPath))->toBeTrue();
})->with([
    'outside credentials directory' => 'private/other-batch.enc',
    'path traversal' => 'lecturer-credentials/../other-batch.enc',
]);

it('does not mark a batch deleted when storage deletion fails', function (): void {
    $superAdmin = credentialExecutionUser('super-admin', ['delete lecturer credential batches']);
    $selected = credentialExecutionBatch($superAdmin);
    $other = credentialExecutionBatch($superAdmin);
    $selectedPath = $selected->encrypted_file_path;
    $otherPath = $other->encrypted_file_path;
    $storage = Storage::getFacadeRoot();
    $disk = \Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('exists')->once()->with($selectedPath)->andReturnTrue();
    $disk->shouldReceive('delete')->once()->with($selectedPath)->andReturnFalse();
    $manager = \Mockery::mock(FilesystemManager::class);
    $manager->shouldReceive('disk')->with('local')->andReturn($disk);

    Storage::swap($manager);
    $this->actingAs($superAdmin);

    try {
        expect(fn () => credentialExecutionPage()->secureDelete($selected->id, app(LecturerCredentialBatchService::class)))
            ->toThrow(HttpException::class);
    } finally {
        Storage::swap($storage);
    }

    expect($selected->fresh()->status)->toBe('available')
        ->and($selected->fresh()->encrypted_file_path)->toBe($selectedPath)
        ->and($selected->actions()->where('action', 'delete_failed')->count())->toBe(1)
        ->and(Storage::disk('local')->exists($otherPath))->toBeTrue();
});

it('keeps account and schedule data unchanged while deleting a batch', function (): void {
    $superAdmin = credentialExecutionUser('super-admin', ['delete lecturer credential batches']);
    $lecturer = User::factory()->create([
        'login_username' => 'nada187',
        'must_change_password' => true,
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);
    $lecturer->assignRole('course_lecturer');
    $passwordHash = $lecturer->password;
    $batch = credentialExecutionBatch($superAdmin);

    $this->actingAs($superAdmin);
    credentialExecutionPage()->secureDelete($batch->id, app(LecturerCredentialBatchService::class));

    expect($lecturer->fresh()->login_username)->toBe('nada187')
        ->and($lecturer->fresh()->password)->toBe($passwordHash)
        ->and($lecturer->fresh()->must_change_password)->toBeTrue()
        ->and($lecturer->fresh()->hasRole('course_lecturer'))->toBeTrue();
});

it('renders the approved Arabic safe-delete confirmation on the real page', function (): void {
    $superAdmin = credentialExecutionUser('super-admin', ['view lecturer credential batches', 'delete lecturer credential batches']);
    credentialExecutionBatch($superAdmin);

    $this->actingAs($superAdmin)
        ->get('/admin/lecturer-credential-batches')
        ->assertOk()
        ->assertSee('سيتم حذف الملف المشفر نهائيًا مع الاحتفاظ ببيانات التدقيق')
        ->assertSee('لن تتأثر حسابات المحاضرين أو كلمات مرورهم');
});
