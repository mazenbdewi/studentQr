<?php

namespace App\Filament\Resources\LectureSessions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LectureSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('lecturer_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('hall_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('session_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('start_time')
                    ->time()
                    ->sortable(),
                TextColumn::make('end_time')
                    ->time()
                    ->sortable(),
                TextColumn::make('actual_start')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('actual_end')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('attendance_mode')
                    ->badge(),
                TextColumn::make('qr_refresh_rate')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('expected_students')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('actual_attendance')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
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
