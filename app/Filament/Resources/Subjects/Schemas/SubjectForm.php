<?php

namespace App\Filament\Resources\Subjects\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SubjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label(__('subjects.code'))
                    ->required(),
                TextInput::make('name')
                    ->label(__('subjects.name'))
                    ->required(),
                TextInput::make('lecturer_id')
                    ->label(__('subjects.lecturer_id'))
                    ->numeric()
                    ->default(null),
                TextInput::make('department_id')
                    ->label(__('subjects.department_id'))
                    ->numeric()
                    ->default(null),
                TextInput::make('credit_hours')
                    ->label(__('subjects.credit_hours'))
                    ->required()
                    ->numeric(),
                TextInput::make('level')
                    ->label(__('subjects.level'))
                    ->required()
                    ->numeric(),
                TextInput::make('semester')
                    ->label(__('subjects.semester'))
                    ->required()
                    ->numeric(),
                Toggle::make('is_active')
                    ->label(__('subjects.is_active'))
                    ->required(),
            ]);
    }
}
