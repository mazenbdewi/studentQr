<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['super-admin', 'admin', 'manager', 'course_lecturer', 'student'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('renders a visible searchable and sortable username column without email', function (): void {
    $actor = userResourceActor();
    $user = User::factory()->create(['login_username' => 'table_username']);

    Livewire::actingAs($actor)
        ->test(ListUsers::class)
        ->assertCanSeeTableRecords([$user])
        ->assertTableColumnExists('login_username', fn (TextColumn $column): bool => $column->getLabel() === 'اسم المستخدم'
            && $column->isSearchable()
            && $column->isSortable())
        ->assertTableColumnVisible('login_username')
        ->assertTableColumnDoesNotExist('email');
});

it('renders username-only create and edit forms', function (): void {
    $actor = userResourceActor();
    $user = User::factory()->create(['login_username' => 'editable_username']);

    Livewire::actingAs($actor)
        ->test(CreateUser::class)
        ->assertFormFieldExists('login_username', fn (TextInput $field): bool => $field->getLabel() === 'اسم المستخدم'
            && $field->isRequired())
        ->assertFormFieldDoesNotExist('email');

    Livewire::actingAs($actor)
        ->test(EditUser::class, ['record' => $user->getRouteKey()])
        ->assertFormFieldExists('login_username')
        ->assertFormFieldDoesNotExist('email');
});

it('validates and normalizes usernames while creating users through the Filament form', function (): void {
    $actor = userResourceActor();

    Livewire::actingAs($actor)
        ->test(CreateUser::class)
        ->fillForm(userResourceFormData('  NEW_ADMIN  '))
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::query()->where('login_username', 'new_admin')->firstOrFail();

    expect($created->login_username)->toBe('new_admin')
        ->and($created->role)->toBe('admin')
        ->and($created->hasRole('admin'))->toBeTrue();

    Livewire::actingAs($actor)
        ->test(CreateUser::class)
        ->fillForm(userResourceFormData('new_admin', 'Different User'))
        ->call('create')
        ->assertHasFormErrors(['login_username']);

    Livewire::actingAs($actor)
        ->test(CreateUser::class)
        ->fillForm(userResourceFormData('invalid username'))
        ->call('create')
        ->assertHasFormErrors(['login_username']);

    Livewire::actingAs($actor)
        ->test(CreateUser::class)
        ->fillForm(userResourceFormData('invalid@username'))
        ->call('create')
        ->assertHasFormErrors(['login_username']);
});

it('ignores the current user during username uniqueness validation and synchronizes a changed classification', function (): void {
    $actor = userResourceActor();
    $user = User::factory()->create([
        'login_username' => 'existing_admin',
        'role' => 'admin',
        'type' => 'admin',
    ]);
    $user->assignRole('admin');

    Livewire::actingAs($actor)
        ->test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm([
            'name' => $user->name,
            'login_username' => '  EXISTING_ADMIN  ',
            'role' => 'manager',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->login_username)->toBe('existing_admin')
        ->and($user->fresh()->role)->toBe('manager')
        ->and($user->fresh()->hasRole('manager'))->toBeTrue()
        ->and($user->fresh()->hasRole('admin'))->toBeFalse();
});

it('requires an explicit Spatie role to access the protected user resource', function (): void {
    $rawClassificationUser = User::factory()->create([
        'login_username' => 'raw_classification',
        'role' => 'super_admin',
        'type' => 'admin',
    ]);
    $authorizedUser = userResourceActor();

    $this->actingAs($rawClassificationUser)
        ->get(UserResource::getUrl())
        ->assertForbidden();

    $this->actingAs($authorizedUser)
        ->get(UserResource::getUrl())
        ->assertSuccessful();
});

function userResourceActor(): User
{
    $user = User::factory()->create([
        'login_username' => 'resource_super_admin_'.User::query()->count(),
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
    ]);
    $user->assignRole('super-admin');

    return $user;
}

function userResourceFormData(string $loginUsername, string $name = 'New Admin'): array
{
    return [
        'name' => $name,
        'login_username' => $loginUsername,
        'password' => 'temporary-password',
        'password_confirmation' => 'temporary-password',
        'role' => 'admin',
    ];
}
