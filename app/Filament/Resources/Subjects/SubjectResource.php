<?php

namespace App\Filament\Resources\Subjects;


use App\Filament\Resources\Students\RelationManagers\SubjectsRelationManager;
use App\Filament\Resources\Subjects\RelationManagers\StudentsRelationManager;
use App\Models\Subject;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Icons\Heroicon;


class SubjectResource extends Resource
{
    protected static ?string $model = Subject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::AcademicCap;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;


    public static function getModelLabel(): string
    {
        return __('subjects.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('subjects.plural');
    }

    public static function getCreatePageTitle(): string
    {
        return __('subjects.create_title');
    }

    public static function getCreateActionLabel(): string
    {
        return __('subjects.create');
    }

    public static function getRecordTitle($record): ?string
    {
        return $record->name ?? __('subjects.record_title') . ' #' . $record->id;
    }


    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->label(__('subjects.code'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')
                    ->label(__('subjects.name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('department_id')
                    ->label(__('subjects.department_id'))
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\TextInput::make('credit_hours')
                    ->label(__('subjects.credit_hours'))
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(6),
                Forms\Components\TextInput::make('level')
                    ->label(__('subjects.level'))
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(5),
                Forms\Components\TextInput::make('semester')
                    ->label(__('subjects.semester'))
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(2),
                Forms\Components\Toggle::make('is_active')
                    ->label(__('subjects.is_active'))
                    ->default(true)
                    ->required(),
                Forms\Components\Hidden::make('lecturer_id')
                    ->default(fn() => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('code')
                ->label(__('subjects.code'))
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('name')
                ->label(__('subjects.name'))
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('department.name')
                ->label(__('subjects.department_id'))
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('credit_hours')
                ->label(__('subjects.credit_hours')),
            Tables\Columns\IconColumn::make('is_active')
                ->label(__('subjects.is_active'))
                ->boolean(),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('department')
                ->label(__('subjects.department_id'))
                ->relationship('department', 'name'),
            Tables\Filters\TernaryFilter::make('is_active')
                ->label(__('subjects.is_active')),
        ])
        ->actions([
            // Tables\Actions\EditAction::make()->label(__('subjects.edit')),
            // Tables\Actions\ViewAction::make()->label(__('subjects.view')),
        ])
        ->bulkActions([]);
}

    public static function getRelations(): array
    {
        return [
            StudentsRelationManager::class

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubjects::route('/'),
            'create' => Pages\CreateSubject::route('/create'),
            'edit' => Pages\EditSubject::route('/{record}/edit'),
            'view' => Pages\ViewSubject::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->user()->hasRole('course_lecturer')) {
            return $query->where('lecturer_id', auth()->id());
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['super-admin', 'manager']);
    }
}
