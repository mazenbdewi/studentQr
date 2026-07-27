<?php

namespace App\Filament\Resources\Halls\Schemas;

use App\Models\Hall;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

class HallInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $components = [
            TextEntry::make('code')
                ->label(__('hall.code')),
            TextEntry::make('name')
                ->label(__('hall.name')),
        ];

        if (DatabaseSchema::hasColumn('halls', 'capacity')) {
            $components[] = TextEntry::make('capacity')
                ->label(__('hall.capacity'))
                ->numeric()
                ->placeholder(__('hall.not_specified'));
        }

        if (DatabaseSchema::hasColumn('halls', 'hall_type')) {
            $components[] = TextEntry::make('hall_type')
                ->label(__('hall.hall_type'))
                ->formatStateUsing(fn (?string $state): string => $state ? (Hall::hallTypeOptions()[$state] ?? $state) : __('hall.not_specified'))
                ->placeholder(__('hall.not_specified'));
        }

        if (DatabaseSchema::hasColumn('halls', 'building_name')) {
            $components[] = TextEntry::make('building_name')
                ->label(__('hall.building_name'))
                ->placeholder(__('hall.not_specified'));
        }

        if (DatabaseSchema::hasColumn('halls', 'faculty_id')) {
            $components[] = TextEntry::make('faculty.name')
                ->label(__('hall.faculty'))
                ->placeholder(__('hall.not_specified'));
        }

        $components[] = TextEntry::make('floor')
            ->label(__('hall.floor'))
            ->numeric()
            ->placeholder(__('hall.not_specified'));

        if (DatabaseSchema::hasColumn('halls', 'is_active')) {
            $components[] = IconEntry::make('is_active')
                ->label(__('hall.is_active'))
                ->boolean();
        }

        if (DatabaseSchema::hasColumn('halls', 'notes')) {
            $components[] = TextEntry::make('notes')
                ->label(__('hall.notes'))
                ->placeholder(__('hall.not_available'))
                ->columnSpanFull();
        }

        $components[] = TextEntry::make('created_at')
            ->label(__('hall.created_at'))
            ->dateTime()
            ->placeholder(__('hall.not_available'));
        $components[] = TextEntry::make('updated_at')
            ->label(__('hall.updated_at'))
            ->dateTime()
            ->placeholder(__('hall.not_available'));

        return $schema
            ->components($components);
    }
}
