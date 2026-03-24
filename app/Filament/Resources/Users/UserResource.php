<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::User;

    // protected static ?string $recordTitleAttribute = 'name';

    // protected static string|UnitEnum|null $navigationGroup = 'Administration';
    public static function getNavigationGroup(): ?string
    {
        return __('user.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('user.users');
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

    public static function getRecordTitle($record): ?string
    {
        return $record->name ?? __('user.record_title') . ' #' . $record->id;
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


    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['super-admin']);
    }
}
