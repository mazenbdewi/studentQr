<?php

namespace App\Filament\Resources\Subjects\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->columns([
            TextColumn::make('code')
                ->label(__('subjects.code'))
                ->searchable(),
            TextColumn::make('name')
                ->label(__('subjects.name'))
                ->searchable(),
            TextColumn::make('lecturer_id')
                ->label(__('subjects.lecturer_id'))
                ->numeric()
                ->sortable(),
            TextColumn::make('department_id')
                ->label(__('subjects.department_id'))
                ->numeric()
                ->sortable(),
            TextColumn::make('credit_hours')
                ->label(__('subjects.credit_hours'))
                ->numeric()
                ->sortable(),
            TextColumn::make('level')
                ->label(__('subjects.level'))
                ->numeric()
                ->sortable(),
            TextColumn::make('semester')
                ->label(__('subjects.semester'))
                ->numeric()
                ->sortable(),
            IconColumn::make('is_active')
                ->label(__('subjects.is_active'))
                ->boolean(),
            TextColumn::make('created_at')
                ->label(__('subjects.created_at'))
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')
                ->label(__('subjects.updated_at'))
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([
            //
        ])
        ->recordActions([
            ViewAction::make()->label(__('subjects.view')),
            EditAction::make()->label(__('subjects.edit')),
        ])
        ->toolbarActions([
            BulkActionGroup::make([
                DeleteBulkAction::make()->label(__('subjects.delete_selected')),
            ]),
        ]);
    }
}
