<?php

namespace App\Filament\Resources\Students\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class StudentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label(__('student.name')),
                TextEntry::make('faculty.name')
                    ->label(__('student.faculty_id'))
                    ->placeholder('-'),
                TextEntry::make('department.name')
                    ->label(__('student.department_id'))
                    ->placeholder('-'),
                TextEntry::make('year')
                    ->label(__('student.year'))
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('type')
                    ->label(__('student.type')),
                TextEntry::make('phone')
                    ->label(__('student.phone'))
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->label(__('student.status'))
                    ->badge(),
                TextEntry::make('student_number')
                    ->label(__('student.student_number'))
                    ->placeholder('-'),
                TextEntry::make('national_number')
                    ->label(__('student.national_number'))
                    ->placeholder('-'),
                TextEntry::make('avatar')
                    ->label(__('student.avatar'))
                    ->placeholder('-'),
                IconEntry::make('is_active')
                    ->label(__('student.is_active'))
                    ->boolean(),
                TextEntry::make('created_at')
                    ->label(__('student.created_at'))
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label(__('student.updated_at'))
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
