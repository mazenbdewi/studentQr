<?php

use App\Filament\Pages\AcademicTermManagement;
use App\Models\AcademicTerm;
use App\Models\AppSetting;
use App\Models\User;
use App\Support\AcademicTermContext;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function academicTermManagementAdmin(): User
{
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Role::findOrCreate('super-admin', 'web');

    $user = User::factory()->create([
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);
    $user->assignRole('super-admin');

    return $user;
}

function managedAcademicTerm(string $name): AcademicTerm
{
    return AcademicTerm::query()->create([
        'display_name' => $name,
        'canonical_name' => str($name)->slug().'-'.str()->uuid(),
    ]);
}

it('manages current-term selection and teaching dates from the academic-term table', function (): void {
    $admin = academicTermManagementAdmin();
    $first = managedAcademicTerm('الفصل الصيفي 2026');
    $second = managedAcademicTerm('الفصل الخريفي 2026');
    AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, (string) $first->id);

    $component = Livewire::actingAs($admin)
        ->test(AcademicTermManagement::class)
        ->assertCanSeeTableRecords([$first, $second])
        ->callTableAction('edit_teaching_dates', $first, [
            'teaching_start_date' => '2026-07-01',
            'teaching_end_date' => '2026-09-30',
        ])
        ->assertNotified('تم حفظ تواريخ الفصل الدراسي');

    expect($first->fresh()->teaching_start_date?->format('d/m/Y'))->toBe('01/07/2026')
        ->and($first->fresh()->teaching_end_date?->format('d/m/Y'))->toBe('30/09/2026');

    $component
        ->callTableAction('toggle_current_term', $second)
        ->assertNotified('تم تحديد الفصل الدراسي الحالي');

    expect(app(AcademicTermContext::class)->currentId())->toBe($second->id)
        ->and(app(AcademicTermContext::class)->isCurrent($first->fresh()))->toBeFalse()
        ->and(app(AcademicTermContext::class)->isCurrent($second->fresh()))->toBeTrue();

    $component
        ->callTableAction('toggle_current_term', $second)
        ->assertNotified('تم إلغاء تحديد الفصل الدراسي الحالي');

    expect(app(AcademicTermContext::class)->currentOrNull())->toBeNull();
});
