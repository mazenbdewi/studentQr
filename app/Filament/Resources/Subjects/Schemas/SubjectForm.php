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
                Select::make('subject_type')
                    ->label(__('subjects.subject_type'))
                    ->options(fn (): array => Subject::subjectTypeOptions())
                    ->native(false)
                    ->required()
                    ->default(Subject::TYPE_THEORETICAL),
                TextInput::make('department_id')
                    ->label(__('subjects.department_id'))
                    ->numeric()
                    ->default(null),
                Toggle::make('is_active')
                    ->label(__('subjects.is_active'))
                    ->required(),
            ]);
    }
}
