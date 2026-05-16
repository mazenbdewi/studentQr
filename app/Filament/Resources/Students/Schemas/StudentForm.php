<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Subject;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                    ->options(fn (): array => Faculty::query()->withoutTrashed()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state, ?string $old): void {
                        if ($state !== $old) {
                            $set('department_id', null);
                            $set('subject_ids', []);
                        }
                    })
                    ->default(null),
                Select::make('department_id')
                    ->label(__('student.department_id'))
                    ->options(fn (Get $get): array => Department::query()
                        ->withoutTrashed()
                        ->when($get('faculty_id'), fn ($query, $facultyId) => $query->where('faculty_id', $facultyId))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state, ?string $old): void {
                        if ($state !== $old) {
                            $set('subject_ids', []);
                        }
                    })
                    ->default(null),
                Select::make('subject_ids')
                    ->label(__('student.enrolled_subjects'))
                    ->multiple()
                    ->options(fn (Get $get): array => Subject::query()
                        ->withoutTrashed()
                        ->where('is_active', true)
                        ->when(
                            $get('department_id'),
                            fn ($query, $departmentId) => $query->where('department_id', $departmentId),
                            fn ($query) => $query->whereRaw('1 = 0'),
                        )
                        ->orderBy('code')
                        ->get(['id', 'code', 'name'])
                        ->mapWithKeys(fn (Subject $subject): array => [
                            $subject->id => filled($subject->code)
                                ? "{$subject->code} - {$subject->name}"
                                : $subject->name,
                        ])
                        ->all())
                    ->afterStateHydrated(function (Select $component): void {
                        /** @var \App\Models\Student|null $record */
                        $record = $component->getRecord();

                        if (! $record) {
                            return;
                        }

                        $component->state($record->subjects()->pluck('subjects.id')->all());
                    })
                    ->searchable()
                    ->preload()
                    ->disabled(fn (Get $get): bool => blank($get('department_id')))
                    ->helperText(__('student.enrolled_subjects_help')),
                TextInput::make('phone')
                    ->label(__('student.phone'))
                    ->tel()
                    ->default(null),
                TextInput::make('student_number')
                    ->label(__('student.student_number'))
                    ->required()
                    ->default(null),
                TextInput::make('national_number')
                    ->label(__('student.national_number'))
                    ->default(null),
                Toggle::make('is_active')
                    ->label(__('student.is_active'))
                    ->required(),
            ]);
    }
}
