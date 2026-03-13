<?php

namespace App\Filament\Resources\Halls\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
            TextColumn::make('capacity')
                ->label(__('hall.capacity'))
                ->numeric()
                ->sortable(),
            IconColumn::make('has_projector')
                ->label(__('hall.has_projector'))
                ->boolean(),
            IconColumn::make('has_computer')
                ->label(__('hall.has_computer'))
                ->boolean(),
            TextColumn::make('network_ssid')
                ->label(__('hall.network_ssid'))
                ->searchable(),
            TextColumn::make('ip_range_start')
                ->label(__('hall.ip_range_start'))
                ->searchable(),
            TextColumn::make('ip_range_end')
                ->label(__('hall.ip_range_end'))
                ->searchable(),
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
        ])
        ->filters([])
        ->recordActions([
            ViewAction::make()->label(__('hall.view')),
            EditAction::make()->label(__('hall.edit')),
        ])
        ->toolbarActions([
            BulkActionGroup::make([
                DeleteBulkAction::make()->label(__('hall.delete_selected')),
            ]),
        ]);
    }
}
