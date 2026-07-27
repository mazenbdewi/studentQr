<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['super-admin', 'admin'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('validates and normalizes login usernames through UserForm', function (): void {
    $actor = User::factory()->create(['login_username' => 'user_form_admin', 'role' => 'super_admin']);
    $actor->assignRole('super-admin');

    Livewire::actingAs($actor)
        ->test(CreateUser::class)
        ->assertFormFieldExists('login_username', fn (TextInput $field): bool => $field->isRequired())
        ->assertFormFieldDoesNotExist('email')
        ->fillForm([
            'name' => 'Form User',
            'login_username' => '  FORM_USER  ',
            'password' => 'temporary-password',
            'password_confirmation' => 'temporary-password',
            'role' => 'admin',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('login_username', 'form_user')->exists())->toBeTrue();
});
