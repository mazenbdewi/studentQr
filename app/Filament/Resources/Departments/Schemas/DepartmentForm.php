<?php

namespace App\Filament\Resources\Departments\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DepartmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
        ->components([
            TextInput::make('code')
                ->label(__('department.code'))
                ->required(),
            TextInput::make('name')
                ->label(__('department.name_ar'))
                ->required(),
            TextInput::make('name_en')
                ->label(__('department.name_en'))
                ->default(null),
            TextInput::make('faculty_id')
                ->label(__('department.faculty'))
                ->numeric()
                ->default(null),
            TextInput::make('head_of_department')
                ->label(__('department.head'))
                ->numeric()
                ->default(null),
            Textarea::make('description')
                ->label(__('department.description'))
                ->default(null)
                ->columnSpanFull(),
            TextInput::make('total_students')
                ->label(__('department.total_students'))
                ->required()
                ->numeric()
                ->default(0),
            TextInput::make('total_lecturers')
                ->label(__('department.total_lecturers'))
                ->required()
                ->numeric()
                ->default(0),
            Toggle::make('is_active')
                ->label(__('department.is_active'))
                ->required(),
        ]);
    }
}
