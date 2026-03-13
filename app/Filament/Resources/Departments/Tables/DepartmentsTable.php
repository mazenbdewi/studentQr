<?php

namespace App\Filament\Resources\Departments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DepartmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
    ->columns([
        TextColumn::make('code')
            ->label(__('department.code'))
            ->searchable(),
        TextColumn::make('name')
            ->label(__('department.name_ar'))
            ->searchable(),
        TextColumn::make('name_en')
            ->label(__('department.name_en'))
            ->searchable(),
        TextColumn::make('faculty_id')
            ->label(__('department.faculty'))
            ->numeric()
            ->sortable(),
        TextColumn::make('head_of_department')
            ->label(__('department.head'))
            ->numeric()
            ->sortable(),
        TextColumn::make('total_students')
            ->label(__('department.total_students'))
            ->numeric()
            ->sortable(),
        TextColumn::make('total_lecturers')
            ->label(__('department.total_lecturers'))
            ->numeric()
            ->sortable(),
        IconColumn::make('is_active')
            ->label(__('department.is_active'))
            ->boolean(),
        TextColumn::make('created_at')
            ->label(__('department.created_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true),
        TextColumn::make('updated_at')
            ->label(__('department.updated_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true),
    ])
    ->filters([
        //
    ])
    ->recordActions([
        ViewAction::make()->label(__('department.view')),
        EditAction::make()->label(__('department.edit')),
    ])
    ->toolbarActions([
        BulkActionGroup::make([
            DeleteBulkAction::make()->label(__('department.delete_selected')),
        ]),
    ]);
    }
}
