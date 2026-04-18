<?php

namespace App\Filament\Resources\Departments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
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
                // TextColumn::make('faculty_id')
                //     ->label(__('department.faculty'))
                //     ->numeric()
                //     ->sortable(),

                TextColumn::make('faculty.name')
                    ->label(__('department.faculty'))
                    ->searchable()
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
                TextColumn::make('deleted_at')
                    ->label(__('department.deleted_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()->label(__('department.view')),
                EditAction::make()
                    ->label(__('department.edit'))
                    ->visible(fn ($record): bool => ! $record->trashed()),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label(__('department.delete_selected')),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
