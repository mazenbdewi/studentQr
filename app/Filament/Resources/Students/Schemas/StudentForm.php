<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Models\Department;
use App\Models\Faculty;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('student.name'))
                    ->required(),
                Select::make('faculty_id')
                    ->label(__('student.faculty_id'))
                    ->options(Faculty::all()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->default(null),
                Select::make('department_id')
                    ->label(__('student.department_id'))
                    ->options(Department::all()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->default(null),
                TextInput::make('year')
                    ->label(__('student.year'))
                    ->numeric()
                    ->default(null),
                TextInput::make('type')
                    ->label(__('student.type'))
                    ->required()
                    ->default('student'),
                TextInput::make('phone')
                    ->label(__('student.phone'))
                    ->tel()
                    ->default(null),
                Select::make('status')
                    ->label(__('student.status'))
                    ->options([
                        'pending' => __('student.status_pending'),
                        'active' => __('student.status_active'),
                        'blocked' => __('student.status_blocked'),
                        'suspended' => __('student.status_suspended'),
                    ])
                    ->default('pending')
                    ->required(),
                TextInput::make('student_number')
                    ->label(__('student.student_number'))
                    ->default(null),
                TextInput::make('national_number')
                    ->label(__('student.national_number'))
                    ->default(null),
                TextInput::make('avatar')
                    ->label(__('student.avatar'))
                    ->default(null),
                Toggle::make('is_active')
                    ->label(__('student.is_active'))
                    ->required(),
            ]);
    }
}
