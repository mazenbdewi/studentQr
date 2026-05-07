<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Users\UserResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('user.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('user.email'))
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('password')
                    ->label(__('user.password'))
                    ->password()
                    ->autocomplete('new-password')
                    ->confirmed()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create')
                    ->helperText(__('user.password_help')),
                TextInput::make('password_confirmation')
                    ->label(__('user.password_confirmation'))
                    ->required(fn (string $context): bool => $context === 'create')
                    ->password()
                    ->autocomplete('new-password')
                    ->dehydrated(false),

                Select::make('role')
                    ->label(__('user.role'))
                    ->options(UserResource::getAssignableRoles())
                    ->required()
                    ->helperText(__('user.role_permissions_help'))
                    ->native(false),
                Section::make(__('user.pin_section'))
                    ->description(__('user.pin_section_help'))
                    ->schema([
                        TextInput::make('pin_code_plain')
                            ->label(__('user.new_pin_code'))
                            ->password()
                            ->autocomplete('new-password')
                            ->nullable()
                            ->rule('digits:6')
                            ->confirmed()
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText(__('user.pin_code_help')),
                        TextInput::make('pin_code_plain_confirmation')
                            ->label(__('user.new_pin_code_confirmation'))
                            ->password()
                            ->autocomplete('new-password')
                            ->dehydrated(false),
                    ])
                    ->visible(fn (): bool => UserResource::canManagePins()),
            ]);
    }
}
