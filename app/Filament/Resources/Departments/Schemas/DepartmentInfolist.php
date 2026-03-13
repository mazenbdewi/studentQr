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
            TextEntry::make('code')
                ->label(__('department.code')),
            TextEntry::make('name')
                ->label(__('department.name_ar')),
            TextEntry::make('name_en')
                ->label(__('department.name_en'))
                ->placeholder(__('department.not_available')),
            TextEntry::make('faculty_id')
                ->label(__('department.faculty'))
                ->numeric()
                ->placeholder(__('department.not_available')),
            TextEntry::make('head_of_department')
                ->label(__('department.head'))
                ->numeric()
                ->placeholder(__('department.not_available')),
            TextEntry::make('description')
                ->label(__('department.description'))
                ->placeholder(__('department.not_available'))
                ->columnSpanFull(),
            TextEntry::make('total_students')
                ->label(__('department.total_students'))
                ->numeric(),
            TextEntry::make('total_lecturers')
                ->label(__('department.total_lecturers'))
                ->numeric(),
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
