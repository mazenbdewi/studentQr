<?php

namespace App\Filament\Resources\Halls\Tables;

use App\Models\Hall;
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
use Illuminate\Support\Facades\Schema;

class HallsTable
{
    public static function configure(Table $table): Table
    {
        $columns = [
            TextColumn::make('code')
                ->label(__('hall.code'))
                ->searchable(),
            TextColumn::make('name')
                ->label(__('hall.name'))
                ->searchable(),
        ];

        if (Schema::hasColumn('halls', 'capacity')) {
            $columns[] = TextColumn::make('capacity')
                ->label(__('hall.capacity'))
                ->numeric()
                ->placeholder(__('hall.not_specified'))
                ->sortable();
        }

        if (Schema::hasColumn('halls', 'hall_type')) {
            $columns[] = TextColumn::make('hall_type')
                ->label(__('hall.hall_type'))
                ->formatStateUsing(fn (?string $state): string => $state ? (Hall::hallTypeOptions()[$state] ?? $state) : __('hall.not_specified'))
                ->badge()
                ->searchable()
                ->sortable();
        }

        if (Schema::hasColumn('halls', 'building_name')) {
            $columns[] = TextColumn::make('building_name')
                ->label(__('hall.building_name'))
                ->placeholder(__('hall.not_specified'))
                ->searchable()
                ->toggleable();
        }

        if (Schema::hasColumn('halls', 'faculty_id')) {
            $columns[] = TextColumn::make('faculty.name')
                ->label(__('hall.faculty'))
                ->placeholder(__('hall.not_specified'))
                ->searchable()
                ->toggleable();
        }

        $columns = [
            ...$columns,
            TextColumn::make('floor')
                ->label(__('hall.floor'))
                ->numeric()
                ->placeholder(__('hall.not_specified'))
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
        ];

        return $table
            ->columns($columns)
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
