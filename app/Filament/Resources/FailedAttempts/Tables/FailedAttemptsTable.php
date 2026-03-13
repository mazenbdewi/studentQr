<?php

namespace App\Filament\Resources\FailedAttempts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FailedAttemptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->columns([
            TextColumn::make('student_id')
                ->label(__('failed_attempt.student_id'))
                ->numeric()
                ->sortable(),
            TextColumn::make('lecture_session_id')
                ->label(__('failed_attempt.lecture_session_id'))
                ->numeric()
                ->sortable(),
            TextColumn::make('reason')
                ->label(__('failed_attempt.reason'))
                ->badge(),
            TextColumn::make('ip_address')
                ->label(__('failed_attempt.ip_address'))
                ->searchable(),
            TextColumn::make('created_at')
                ->label(__('failed_attempt.created_at'))
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')
                ->label(__('failed_attempt.updated_at'))
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([
            //
        ])
        ->recordActions([
            ViewAction::make()->label(__('failed_attempt.view')),
            EditAction::make()->label(__('failed_attempt.edit')),
        ])
        ->toolbarActions([
            BulkActionGroup::make([
                DeleteBulkAction::make()->label(__('failed_attempt.delete_selected')),
            ]),
        ]);
    }
}
