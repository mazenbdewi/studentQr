<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->columns([
            TextColumn::make('user_id')
                ->label(__('audit_logs.user_id'))
                ->numeric()
                ->sortable(),
            TextColumn::make('user_type')
                ->label(__('audit_logs.user_type'))
                ->searchable(),
            TextColumn::make('action')
                ->label(__('audit_logs.action'))
                ->searchable(),
            TextColumn::make('ip_address')
                ->label(__('audit_logs.ip_address'))
                ->searchable(),
            TextColumn::make('location')
                ->label(__('audit_logs.location'))
                ->searchable(),
            TextColumn::make('severity')
                ->label(__('audit_logs.severity'))
                ->badge()
                ->formatStateUsing(fn ($state) => __("audit_logs.severity_{$state}")),
            TextColumn::make('created_at')
                ->label(__('audit_logs.created_at'))
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')
                ->label(__('audit_logs.updated_at'))
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([
            //
        ])
        ->recordActions([
            ViewAction::make()->label(__('audit_logs.view')),
            EditAction::make()->label(__('audit_logs.edit')),
        ])
        ->toolbarActions([
            BulkActionGroup::make([
                DeleteBulkAction::make()->label(__('audit_logs.delete_selected')),
            ]),
        ]);
    }
}
