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
                    ->label(__('student.name'))
                    ->placeholder('-'),
                TextEntry::make('student_number')
                    ->label(__('student.student_number'))
                    ->placeholder('-'),
                TextEntry::make('national_number')
                    ->label(__('student.national_number'))
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->label(__('student.status'))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? __("student.status_{$state}") : __('student.status_active'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'blocked', 'suspended' => 'danger',
                        default => 'gray',
                    }),
                TextEntry::make('department.name')
                    ->label(__('student.department_id'))
                    ->placeholder('-'),
                TextEntry::make('faculty.name')
                    ->label(__('student.faculty_id'))
                    ->placeholder('-'),
                TextEntry::make('year')
                    ->label(__('student.year'))
                    ->formatStateUsing(fn (?int $state): string => filled($state) ? __("student.year_options.{$state}") : '-')
                    ->placeholder('-'),
                TextEntry::make('phone')
                    ->label(__('student.phone'))
                    ->placeholder('-'),
                IconEntry::make('is_active')
                    ->label(__('student.is_active'))
                    ->boolean(),
            ]);
    }
}
