<?php

namespace App\Filament\Resources\Students\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('student.name'))
                    ->searchable(),
                TextColumn::make('faculty.name')
                    ->label(__('student.faculty_id'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('department.name')
                    ->label(__('student.department_id'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('year')
                    ->label(__('student.year'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('student.type'))
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('student.phone'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('student.status'))
                    ->badge(),
                TextColumn::make('student_number')
                    ->label(__('student.student_number'))
                    ->searchable(),
                TextColumn::make('national_number')
                    ->label(__('student.national_number'))
                    ->searchable(),
                TextColumn::make('avatar')
                    ->label(__('student.avatar'))
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label(__('student.is_active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('student.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('student.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
