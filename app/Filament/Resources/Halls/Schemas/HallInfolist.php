<?php

namespace App\Filament\Resources\Halls\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class HallInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
    ->components([
        TextEntry::make('code')
            ->label(__('hall.code')),
        TextEntry::make('name')
            ->label(__('hall.name')),
        TextEntry::make('floor')
            ->label(__('hall.floor'))
            ->numeric(),
        TextEntry::make('capacity')
            ->label(__('hall.capacity'))
            ->numeric(),
        IconEntry::make('has_projector')
            ->label(__('hall.has_projector'))
            ->boolean(),
        IconEntry::make('has_computer')
            ->label(__('hall.has_computer'))
            ->boolean(),
        TextEntry::make('network_ssid')
            ->label(__('hall.network_ssid'))
            ->placeholder(__('hall.not_available')),
        TextEntry::make('ip_range_start')
            ->label(__('hall.ip_range_start'))
            ->placeholder(__('hall.not_available')),
        TextEntry::make('ip_range_end')
            ->label(__('hall.ip_range_end'))
            ->placeholder(__('hall.not_available')),
        IconEntry::make('is_active')
            ->label(__('hall.is_active'))
            ->boolean(),
        TextEntry::make('created_at')
            ->label(__('hall.created_at'))
            ->dateTime()
            ->placeholder(__('hall.not_available')),
        TextEntry::make('updated_at')
            ->label(__('hall.updated_at'))
            ->dateTime()
            ->placeholder(__('hall.not_available')),
    ]);
    }
}
