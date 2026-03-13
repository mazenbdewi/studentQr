<?php

namespace App\Filament\Resources\FailedAttempts;

use App\Filament\Resources\FailedAttempts\Pages\CreateFailedAttempt;
use App\Filament\Resources\FailedAttempts\Pages\EditFailedAttempt;
use App\Filament\Resources\FailedAttempts\Pages\ListFailedAttempts;
use App\Filament\Resources\FailedAttempts\Pages\ViewFailedAttempt;
use App\Filament\Resources\FailedAttempts\Schemas\FailedAttemptForm;
use App\Filament\Resources\FailedAttempts\Schemas\FailedAttemptInfolist;
use App\Filament\Resources\FailedAttempts\Tables\FailedAttemptsTable;
use App\Models\FailedAttempt;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FailedAttemptResource extends Resource
{
    protected static ?string $model = FailedAttempt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    // protected static ?string $recordTitleAttribute = 'FailedAttempt';
    protected static ?string $recordTitleAttribute = 'id';

    public static function getModelLabel(): string
    {
        return __('failed_attempt.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('failed_attempt.plural');
    }

    public static function getCreatePageTitle(): string
    {
        return __('failed_attempt.create_title');
    }

    public static function getCreateActionLabel(): string
    {
        return __('failed_attempt.create');
    }

    public static function getRecordTitle($record): ?string
    {
        return __('failed_attempt.record_title') . ' #' . $record->id;
    }


    public static function form(Schema $schema): Schema
    {
        return FailedAttemptForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FailedAttemptInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FailedAttemptsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFailedAttempts::route('/'),
            'create' => CreateFailedAttempt::route('/create'),
            'view' => ViewFailedAttempt::route('/{record}'),
            'edit' => EditFailedAttempt::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('super-admin');
    }
}
