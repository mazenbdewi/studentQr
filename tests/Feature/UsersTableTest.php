<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('super-admin', 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('renders the UsersTable login username column by default without an email column', function (): void {
    $actor = User::factory()->create(['login_username' => 'users_table_admin', 'role' => 'super_admin']);
    $actor->assignRole('super-admin');
    $record = User::factory()->create(['login_username' => 'users_table_record']);

    Livewire::actingAs($actor)
        ->test(ListUsers::class)
        ->assertCanSeeTableRecords([$record])
        ->assertTableColumnExists('login_username', fn (TextColumn $column): bool => $column->getLabel() === 'اسم المستخدم'
            && $column->isSearchable()
            && $column->isSortable())
        ->assertTableColumnVisible('login_username')
        ->assertTableColumnDoesNotExist('email');
});
