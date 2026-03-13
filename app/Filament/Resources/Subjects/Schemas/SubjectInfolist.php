<?php

namespace App\Filament\Resources\Subjects\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SubjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
        ->components([
            TextEntry::make('code')
                ->label(__('subjects.code')),
            TextEntry::make('name')
                ->label(__('subjects.name')),
            TextEntry::make('lecturer_id')
                ->label(__('subjects.lecturer_id'))
                ->numeric()
                ->placeholder(__('subjects.not_available')),
            TextEntry::make('department_id')
                ->label(__('subjects.department_id'))
                ->numeric()
                ->placeholder(__('subjects.not_available')),
            TextEntry::make('credit_hours')
                ->label(__('subjects.credit_hours'))
                ->numeric(),
            TextEntry::make('level')
                ->label(__('subjects.level'))
                ->numeric(),
            TextEntry::make('semester')
                ->label(__('subjects.semester'))
                ->numeric(),
            IconEntry::make('is_active')
                ->label(__('subjects.is_active'))
                ->boolean(),
            TextEntry::make('created_at')
                ->label(__('subjects.created_at'))
                ->dateTime()
                ->placeholder(__('subjects.not_available')),
            TextEntry::make('updated_at')
                ->label(__('subjects.updated_at'))
                ->dateTime()
                ->placeholder(__('subjects.not_available')),
        ]);
    }
}
