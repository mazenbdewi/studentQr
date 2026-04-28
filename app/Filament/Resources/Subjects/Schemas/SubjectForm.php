<?php

namespace App\Filament\Resources\Subjects\Schemas;

use App\Models\Subject;
use Filament\Forms\Components\Select;
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
                Select::make('semester')
                    ->label(__('subjects.semester'))
                    ->options(fn (): array => Subject::semesterOptions())
                    ->native(false)
                    ->afterStateHydrated(fn ($component, mixed $state): mixed => $component->state(Subject::normalizeSemester($state)))
                    ->required()
                    ->default(Subject::SEMESTER_FIRST),
                Toggle::make('is_active')
                    ->label(__('subjects.is_active'))
                    ->required(),
            ]);
    }
}
