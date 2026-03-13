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
                ->required()
                ->numeric(),
            TextInput::make('capacity')
                ->label(__('hall.capacity'))
                ->required()
                ->numeric(),
            Toggle::make('has_projector')
                ->label(__('hall.has_projector'))
                ->required(),
            Toggle::make('has_computer')
                ->label(__('hall.has_computer'))
                ->required(),
            TextInput::make('network_ssid')
                ->label(__('hall.network_ssid'))
                ->default(null),
            TextInput::make('ip_range_start')
                ->label(__('hall.ip_range_start'))
                ->default(null),
            TextInput::make('ip_range_end')
                ->label(__('hall.ip_range_end'))
                ->default(null),
            Toggle::make('is_active')
                ->label(__('hall.is_active'))
                ->required(),
        ]);
    }
}
