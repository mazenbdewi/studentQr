<?php

use App\Filament\Pages\LecturerCredentialBatches;
use App\Models\LecturerCredentialBatch;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['admin', 'super-admin', 'manager', 'course_lecturer'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
    foreach (['view lecturer credential batches', 'download lecturer credential batches', 'delete lecturer credential batches'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function credentialPageUser(string $role, array $permissions = []): User
{
    $user = User::factory()->create(['role' => $role, 'type' => $role, 'status' => 'active', 'is_active' => true]);
    $user->assignRole($role);
    if ($permissions) {
        $user->givePermissionTo($permissions);
    }

return $user;
}
function credentialPageBatch(array $extra = []): LecturerCredentialBatch
{
    return LecturerCredentialBatch::query()->create([...['batch_type' => 'initial_accounts', 'original_filename' => 'بيانات-دخول.xlsx', 'encrypted_file_path' => 'lecturer-credentials/test.enc', 'sha256' => str_repeat('a', 64), 'encryption_key_version' => 'test-v1', 'record_count' => 1, 'generated_at' => now(), 'status' => 'available'], ...$extra]);
}

it('protects the direct credentials-batch page route by permission', function (): void {
    $admin = credentialPageUser('admin', ['view lecturer credential batches']);
    $super = credentialPageUser('super-admin');
    $manager = credentialPageUser('manager');
    $lecturer = credentialPageUser('course_lecturer');
    $plain = credentialPageUser('admin');
    $this->actingAs($admin)->get('/admin/lecturer-credential-batches')->assertOk();
    $this->actingAs($super)->get('/admin/lecturer-credential-batches')->assertOk();
    $this->actingAs($manager)->get('/admin/lecturer-credential-batches')->assertForbidden();
    $this->actingAs($lecturer)->get('/admin/lecturer-credential-batches')->assertForbidden();
    $this->actingAs($plain)->get('/admin/lecturer-credential-batches')->assertForbidden();
});

it('renders only authorized safe batch actions and metadata', function (): void {
    $batch = credentialPageBatch();
    $admin = credentialPageUser('admin', ['view lecturer credential batches', 'download lecturer credential batches']);
    $super = credentialPageUser('super-admin', ['view lecturer credential batches', 'download lecturer credential batches', 'delete lecturer credential batches']);
    Livewire::actingAs($admin)->test(LecturerCredentialBatches::class)->assertSee('تنزيل')->assertDontSee('حذف آمن')->assertSee('بيانات-دخول.xlsx')->assertSee('إصدار مفتاح التشفير')->assertDontSee('lecturer-credentials/test.enc');
    Livewire::actingAs($super)->test(LecturerCredentialBatches::class)->assertSee('تنزيل')->assertSee('حذف آمن');
    $batch->update(['status' => 'deleted', 'encrypted_file_path' => null]);
    expect($batch->fresh()->encrypted_file_path)->toBeNull();
});

it('keeps page authorization server-side for forged users', function (): void {
    $batch = credentialPageBatch();
    $admin = credentialPageUser('admin', ['view lecturer credential batches', 'download lecturer credential batches']);
    $manager = credentialPageUser('manager');
    expect(LecturerCredentialBatches::canAccess())->toBeFalse();
    $this->actingAs($manager)->get('/admin/lecturer-credential-batches')->assertForbidden();
    expect($batch->fresh()->status)->toBe('available');
});
