<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::User;

    // protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('filament-dashboard.navigation.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-dashboard.users');
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }

    public static function getModelLabel(): string
    {
        return __('user.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('user.plural');
    }

    public static function getCreatePageTitle(): string
    {
        return __('user.create_title');
    }

    public static function getCreateActionLabel(): string
    {
        return __('user.create');
    }

    public static function getAssignableRoles(): array
    {
        return [
            'super_admin' => __('user.super_admin'),
            'admin' => __('user.admin'),
            'manager' => 'مدير',
            'attendance_monitor' => 'مراقب الحضور',
            'course_lecturer' => __('user.course_lecturer'),
            'student' => 'طالب',
        ];
    }

    public static function canManagePins(?User $user = null): bool
    {
        $user ??= Filament::auth()->user();

        return (bool) $user?->isSuperAdmin();
    }

    public static function getDetectedSystemRoleNames(): array
    {
        return Role::query()
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public static function getImportableRoles(): array
    {
        $detectedRoleNames = collect(static::getDetectedSystemRoleNames());

        return collect(static::getAssignableRoles())
            ->filter(
                fn (string $label, string $databaseRole): bool => $detectedRoleNames->contains(
                    User::mapDatabaseRoleToSpatieRole($databaseRole)
                )
            )
            ->all();
    }

    public static function getImportableRoleValues(): array
    {
        return array_keys(static::getImportableRoles());
    }

    public static function getImportRoleDescriptions(): array
    {
        return collect(static::getImportableRoles())
            ->map(
                fn (string $label, string $databaseRole): string => __(
                    'user.import_role_option',
                    [
                        'value' => $databaseRole,
                        'label' => $label,
                        'system_role' => User::mapDatabaseRoleToSpatieRole($databaseRole),
                    ]
                )
            )
            ->values()
            ->all();
    }

    public static function getRecordTitle($record): ?string
    {
        return $record->name ?? __('user.record_title').' #'.$record->id;
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('role', array_keys(static::getAssignableRoles()))
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return Filament::auth()->user()?->canManageUsers() ?? false;
    }

    public static function canCreate(): bool
    {
        return Filament::auth()->user()?->canManageUsers() ?? false;
    }

    public static function canEdit($record): bool
    {
        return Filament::auth()->user()?->canManageUsers() ?? false;
    }

    public static function canDelete($record): bool
    {
        return Filament::auth()->user()?->canManageUsers() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return Filament::auth()->user()?->canManageUsers() ?? false;
    }

    public static function canForceDelete($record): bool
    {
        return Filament::auth()->user()?->canManageUsers() ?? false;
    }

    public static function canForceDeleteAny(): bool
    {
        return Filament::auth()->user()?->canManageUsers() ?? false;
    }

    public static function canRestore($record): bool
    {
        return Filament::auth()->user()?->canManageUsers() ?? false;
    }

    public static function canRestoreAny(): bool
    {
        return Filament::auth()->user()?->canManageUsers() ?? false;
    }
}
