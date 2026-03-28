<?php

namespace App\Filament\Resources\Faculties\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FacultyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label(__('faculty.name')),

                TextEntry::make('name_en')
                    ->label(__('faculty.name_en'))
                    ->placeholder('-'),

                TextEntry::make('description')
                    ->label(__('faculty.description'))
                    ->placeholder('-')
                    ->columnSpanFull(),

                IconEntry::make('is_active')
                    ->label(__('faculty.is_active'))
                    ->boolean(),

                TextEntry::make('created_at')
                    ->label(__('faculty.created_at'))
                    ->dateTime(),

                TextEntry::make('updated_at')
                    ->label(__('faculty.updated_at'))
                    ->dateTime(),
            ]);
    }
}