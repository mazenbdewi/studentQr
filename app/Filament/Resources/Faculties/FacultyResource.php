<?php

namespace App\Filament\Resources\Faculties;

use App\Filament\Resources\Faculties\Pages;
use App\Filament\Resources\Faculties\RelationManagers;
use App\Filament\Resources\Faculties\Schemas\FacultyForm;
use App\Filament\Resources\Faculties\Schemas\FacultyInfolist;
use App\Filament\Resources\Faculties\Tables\FacultiesTable;
use App\Models\Faculty;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FacultyResource extends Resource
{
    protected static ?string $model = Faculty::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::AcademicCap;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getNavigationGroup(): ?string
    {
        return __('sidebar.academic_structure');
    }




    public static function getModelLabel(): string
    {
        return __('faculty.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('faculty.plural');
    }

    public static function getCreatePageTitle(): string
    {
        return __('faculty.create_title');
    }

    public static function getCreateActionLabel(): string
    {
        return __('faculty.create');
    }

    public static function getRecordTitle($record): ?string
    {
        return __('faculty.record_title') . ' #' . $record->id;
    }

    public static function form(Schema $schema): Schema
    {
        return FacultyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FacultyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FacultiesTable::configure($table);
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
            'index' => Pages\ListFaculties::route('/'),
            'create' => Pages\CreateFaculty::route('/create'),
            'view' => Pages\ViewFaculty::route('/{record}'),
            'edit' => Pages\EditFaculty::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('super-admin');
    }
}

