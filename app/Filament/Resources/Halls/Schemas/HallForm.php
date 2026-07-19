<?php

namespace App\Filament\Resources\Halls\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class HallForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label(__('hall.code'))
                    ->required(),

                TextInput::make('name')
                    ->label(__('hall.name'))
                    ->required(),

                TextInput::make('floor')
                    ->label(__('hall.floor'))
                    ->numeric()
                    ->placeholder(__('hall.not_specified')),

                Toggle::make('is_active')
                    ->label(__('hall.is_active'))
                    ->default(true)
                    ->required(),
            ]);
    }
}
