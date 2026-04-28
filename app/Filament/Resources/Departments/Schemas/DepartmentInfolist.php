<?php

namespace App\Filament\Resources\Departments\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DepartmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
        ->components([
            TextEntry::make('name')
                ->label(__('department.name')),
            TextEntry::make('faculty.name')
                ->label(__('department.faculty'))
                ->placeholder(__('department.not_available')),
            TextEntry::make('description')
                ->label(__('department.description'))
                ->placeholder(__('department.not_available'))
                ->columnSpanFull(),
   
            IconEntry::make('is_active')
                ->label(__('department.is_active'))
                ->boolean(),
            TextEntry::make('created_at')
                ->label(__('department.created_at'))
                ->dateTime()
                ->placeholder(__('department.not_available')),
            TextEntry::make('updated_at')
                ->label(__('department.updated_at'))
                ->dateTime()
                ->placeholder(__('department.not_available')),
        ]);
    }
}
