<?php


namespace App\Filament\Resources\Subjects\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachBulkAction;
use Illuminate\Database\Eloquent\Model;

class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student_number')
                    ->label(__('student.student_number')),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('student.name')),
                Tables\Columns\TextColumn::make('pivot.semester')
                    ->label(__('enrollments.semester')),
                Tables\Columns\TextColumn::make('pivot.year')
                    ->label(__('enrollments.year')),
                Tables\Columns\TextColumn::make('pivot.status')
                    ->label(__('enrollments.status'))
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'enrolled' => 'success',
                        'dropped' => 'danger',
                        'passed' => 'info',
                        'failed' => 'warning',
                    }),

            ])
            ->headerActions([
                AttachAction::make()
                    ->label(__('enrollments.attach_student'))
                    ->form([
                        Forms\Components\Select::make('recordId')
                            ->label(__('enrollments.student'))
                            ->options(\App\Models\Student::pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('semester')
                            ->label(__('enrollments.semester'))
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('year')
                            ->label(__('enrollments.year'))
                            ->numeric()
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label(__('enrollments.status'))
                            ->options([
                                'enrolled' => __('enrollments.enrolled'),
                                'dropped' => __('enrollments.dropped'),
                                'passed' => __('enrollments.passed'),
                                'failed' => __('enrollments.failed'),
                            ])
                            ->default('enrolled')
                            ->required(),
                    ])
                    ->using(function (array $data) {
                        $this->ownerRecord->students()->attach($data['recordId'], [
                            'semester' => $data['semester'],
                            'year' => $data['year'],
                            'status' => $data['status'],
                        ]);
                        return $this->ownerRecord;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->form([
                        Forms\Components\TextInput::make('semester')
                            ->label(__('enrollments.semester'))
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('year')
                            ->label(__('enrollments.year'))
                            ->numeric()
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label(__('enrollments.status'))
                            ->options([
                                'enrolled' => __('enrollments.enrolled'),
                                'dropped' => __('enrollments.dropped'),
                                'passed' => __('enrollments.passed'),
                                'failed' => __('enrollments.failed'),
                            ])
                            ->required(),
                    ])
                    ->using(function (Model $record, array $data) {
                        $record->pivot->update([
                            'semester' => $data['semester'],
                            'year' => $data['year'],
                            'status' => $data['status'],
                        ]);
                        return $record;
                    }),
                DetachAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
