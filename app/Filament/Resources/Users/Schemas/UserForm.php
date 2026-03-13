<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('user.name'))
                    ->required(),
                TextInput::make('email')
                    ->label(__('user.email'))
                    ->email()
                    ->required(),

                TextInput::make('password')
                    ->label(__('user.password'))
                    ->password()
                    ->confirmed()
                    ->dehydrateStateUsing(fn($state) => Hash::make($state))
                    ->dehydrated(fn($state) => filled($state))
                    ->required(fn(string $context): bool => $context === 'create'),
                TextInput::make('password_confirmation')
                    ->label(__('user.password_confirmation'))
                    ->required(fn(string $context): bool => $context === 'create')
                    ->password()
                    ->dehydrated(false),

                Select::make('roles')
                    ->label(__('user.roles'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->multiple()
                    ->relationship('roles', 'name'),
            ]);
    }
}
