<?php

use App\Models\AcademicTerm;
use App\Models\Lecturer;
use App\Models\LecturerCredentialBatch;
use App\Models\LecturerPasswordResetOperation;
use App\Models\User;
use App\Services\LecturerBulkPasswordResetService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Storage::fake('local');
    config()->set('services.lecturer_credentials.key', str_repeat('r', 48));
    config()->set('services.lecturer_credentials.key_version', 'reset-v1');
    foreach (['admin', 'super-admin', 'manager', 'course_lecturer'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
    Permission::findOrCreate('reset lecturer passwords', 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function passwordResetActor(string $role = 'admin', bool $permitted = true): User
{
    $user = User::factory()->create(['role' => $role, 'type' => 'admin', 'status' => 'active', 'is_active' => true]);
    $user->assignRole($role);
    if ($permitted) {
        $user->givePermissionTo('reset lecturer passwords');
    }

    return $user;
}

function resettableLecturer(string $username, array $overrides = []): array
{
    $user = User::factory()->create([
        'name' => 'ندى محمد محمود',
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
        'login_username' => $username,
        'password' => Hash::make('OldPassword!234'),
        ...$overrides,
    ]);
    $user->assignRole('course_lecturer');
    $lecturer = Lecturer::query()->create(['name' => 'ندى محمد محمود', 'canonical_name' => 'ندى محمد محمود', 'user_id' => $user->id, 'is_active' => true]);

    return compact('user', 'lecturer');
}

it('previews without writes and precisely excludes non lecturer accounts', function (): void {
    $eligible = resettableLecturer('nada187');
    $inactive = resettableLecturer('inactive188', ['is_active' => false]);
    $noRole = resettableLecturer('norole189');
    $noRole['user']->removeRole('course_lecturer');
    $noUsername = resettableLecturer('', ['login_username' => null]);
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'is_active' => true]);
    $student = User::factory()->create(['role' => 'student', 'type' => 'student', 'status' => 'active', 'is_active' => true]);
    $before = [User::count(), LecturerCredentialBatch::count(), LecturerPasswordResetOperation::count()];

    $preview = app(LecturerBulkPasswordResetService::class)->preview([
        $eligible['user']->id, $inactive['user']->id, $noRole['user']->id, $noUsername['user']->id, $admin->id, $student->id, 'broken', 999999,
    ]);

    expect($preview['eligible_count'])->toBe(1)
        ->and($preview['excluded_count'])->toBe(7)
        ->and(collect($preview['rows'])->pluck('reason')->filter()->all())->toContain('الحساب غير فعال', 'الحساب لا يملك صلاحية مدرس مقرر', 'اسم الدخول غير جاهز', 'لا يوجد ارتباط بهوية محاضر', 'الحساب لم يعد موجودًا')
        ->and([User::count(), LecturerCredentialBatch::count(), LecturerPasswordResetOperation::count()])->toBe($before)
        ->and($preview['proposed_batch_filename'])->toContain('إعادة-ضبط');
});

it('has a deterministic preview fingerprint that is invalidated by account changes', function (): void {
    $fixture = resettableLecturer('nada187');
    $service = app(LecturerBulkPasswordResetService::class);
    $first = $service->preview([$fixture['user']->id]);
    $second = $service->preview([$fixture['user']->id]);
    $fixture['user']->forceFill(['login_username' => 'nada188'])->save();
    $changed = $service->preview([$fixture['user']->id]);

    expect($first['fingerprint'])->toBe($second['fingerprint'])
        ->and($changed['fingerprint'])->not->toBe($first['fingerprint']);
});

it('resets only eligible lecturers into one encrypted password reset batch', function (): void {
    $first = resettableLecturer('nada187');
    $second = resettableLecturer('sara188');
    $excluded = User::factory()->create(['role' => 'admin', 'status' => 'active', 'is_active' => true]);
    $actor = passwordResetActor();
    $oldFirst = $first['user']->password;
    $oldSecond = $second['user']->password;
    $term = AcademicTerm::query()->create(['display_name' => 'الفصل الصيفي 2025/2026', 'canonical_name' => 'summer']);
    $service = app(LecturerBulkPasswordResetService::class);
    $preview = $service->preview([$first['user']->id, $second['user']->id, $excluded->id], $term);
    $result = $service->execute($preview, $preview['fingerprint'], $actor, $term);
    $batch = $result['batch']->fresh();

    expect($result['reset_count'])->toBe(2)
        ->and($batch->batch_type)->toBe('password_reset')
        ->and($batch->record_count)->toBe(2)
        ->and($batch->original_filename)->toContain('إعادة-ضبط')
        ->and(Storage::disk('local')->exists($batch->encrypted_file_path))->toBeTrue()
        ->and(Storage::disk('local')->get($batch->encrypted_file_path))->not->toStartWith('PK')
        ->and(app(\App\Services\LecturerCredentialBatchService::class)->decryptedContents($batch))->toStartWith('PK')
        ->and($first['user']->fresh()->password)->not->toBe($oldFirst)
        ->and($second['user']->fresh()->password)->not->toBe($oldSecond)
        ->and($first['user']->fresh()->must_change_password)->toBeTrue()
        ->and($second['user']->fresh()->must_change_password)->toBeTrue()
        ->and($excluded->fresh()->password)->toBe($excluded->password)
        ->and($batch->actions()->whereIn('action', ['reset_batch_created', 'generated'])->count())->toBe(2)
        ->and(LecturerPasswordResetOperation::query()->where('fingerprint', $preview['fingerprint'])->exists())->toBeTrue()
        ->and(Storage::disk('local')->allFiles('lecturer-credentials'))->each->toEndWith('.enc');
});

it('rejects unauthorized, stale, and reused reset previews without a second batch', function (): void {
    $fixture = resettableLecturer('nada187');
    $service = app(LecturerBulkPasswordResetService::class);
    $preview = $service->preview([$fixture['user']->id]);
    $original = $fixture['user']->password;

    expect(fn () => $service->execute($preview, $preview['fingerprint'], passwordResetActor('manager', false)))->toThrow(RuntimeException::class);
    expect($fixture['user']->fresh()->password)->toBe($original);

    $fixture['user']->forceFill(['login_username' => 'changed188'])->save();
    expect(fn () => $service->execute($preview, $preview['fingerprint'], passwordResetActor()))->toThrow(RuntimeException::class);
    expect(LecturerCredentialBatch::count())->toBe(0);

    $fresh = $service->preview([$fixture['user']->id]);
    $actor = passwordResetActor();
    $service->execute($fresh, $fresh['fingerprint'], $actor);
    expect(fn () => $service->execute($fresh, $fresh['fingerprint'], $actor))->toThrow(RuntimeException::class)
        ->and(LecturerCredentialBatch::count())->toBe(1);
});

it('does not change hashes or create files when encryption cannot be configured', function (): void {
    $fixture = resettableLecturer('nada187');
    $actor = passwordResetActor();
    $service = app(LecturerBulkPasswordResetService::class);
    $preview = $service->preview([$fixture['user']->id]);
    $oldHash = $fixture['user']->password;
    config()->set('services.lecturer_credentials.key', null);

    expect(fn () => $service->execute($preview, $preview['fingerprint'], $actor))->toThrow(RuntimeException::class)
        ->and($fixture['user']->fresh()->password)->toBe($oldHash)
        ->and(LecturerCredentialBatch::count())->toBe(0)
        ->and(LecturerPasswordResetOperation::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});
