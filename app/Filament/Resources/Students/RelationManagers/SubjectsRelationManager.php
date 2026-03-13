<?php

namespace App\Filament\Resources\Students\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;


class SubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'subjects';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('enrollments.enrolled_subjects');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('subjects.code')),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('subjects.name')),
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
                    ->label(__('enrollments.add_subject'))
                    ->form(fn(AttachAction $action): array => [
                        Forms\Components\Select::make('recordId')
                            ->label(__('enrollments.subject'))
                            ->options(
                                \App\Models\Subject::query()
                                    ->pluck('name', 'id')
                                    ->toArray()
                            )
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
                    ->using(function (array $data): Model {
                        $this->ownerRecord->subjects()->attach($data['recordId'], [
                            'semester' => $data['semester'],
                            'year' => $data['year'],
                            'status' => $data['status'],
                        ]);
                        return $this->ownerRecord;
                    })
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
                    ->using(function (Model $record, array $data): Model {
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


