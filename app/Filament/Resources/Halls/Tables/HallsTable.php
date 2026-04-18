<?php

namespace App\Filament\Resources\Halls\Tables;

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

class HallsTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->columns([
            TextColumn::make('code')
                ->label(__('hall.code'))
                ->searchable(),
            TextColumn::make('name')
                ->label(__('hall.name'))
                ->searchable(),
            TextColumn::make('floor')
                ->label(__('hall.floor'))
                ->numeric()
                ->sortable(),
            IconColumn::make('is_active')
                ->label(__('hall.is_active'))
                ->boolean(),
            TextColumn::make('created_at')
                ->label(__('hall.created_at'))
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')
                ->label(__('hall.updated_at'))
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('deleted_at')
                ->label(__('hall.deleted_at'))
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([
            TrashedFilter::make(),
        ])
        ->recordActions([
            ViewAction::make()->label(__('hall.view')),
            EditAction::make()
                ->label(__('hall.edit'))
                ->visible(fn ($record): bool => ! $record->trashed()),
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ])
        ->toolbarActions([
            BulkActionGroup::make([
                DeleteBulkAction::make()->label(__('hall.delete_selected')),
                RestoreBulkAction::make(),
                ForceDeleteBulkAction::make(),
            ]),
        ]);
    }
}
