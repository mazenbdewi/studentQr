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
                Select::make('year')
                    ->label(__('student.year'))
                    ->options([
                        1 => __('student.year_options.1'),
                        2 => __('student.year_options.2'),
                        3 => __('student.year_options.3'),
                        4 => __('student.year_options.4'),
                        5 => __('student.year_options.5'),
                        6 => __('student.year_options.6'),
                    ])
                    ->native(false)
                    ->default(null),
       
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
