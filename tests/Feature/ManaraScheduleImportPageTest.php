<?php

use App\Filament\Pages\ManaraScheduleImport;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('shows direct upload progress and no academic term selector', function (): void {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $user = User::factory()->create([
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
    ]);
    $user->assignRole('super-admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($user)
        ->test(ManaraScheduleImport::class)
        ->call('import')
        ->assertHasFormErrors(['file'])
        ->assertSee(__('manara-schedule-import.upload_loading'))
        ->assertSee(__('manara-schedule-import.import_loading'))
        ->assertDontSee('academic_term_id');
});
