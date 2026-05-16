<?php

namespace App\Filament\Resources\Subjects;


use App\Filament\Resources\Students\RelationManagers\SubjectsRelationManager;
use App\Filament\Resources\Subjects\RelationManagers\StudentsRelationManager;
use App\Filament\Resources\Subjects\RelationManagers\SubjectSectionsRelationManager;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Subject;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\ValidationException;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;


class SubjectResource extends Resource
{
    protected static ?string $model = Subject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BookOpen;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('filament-dashboard.subjects');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-dashboard.navigation.academic_data');
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }
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
                Forms\Components\Select::make('faculty_id')
                    ->label(__('subjects.faculty'))
                    ->options(fn (): array => static::getFacultyOptions())
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Forms\Components\Select $component, mixed $state): void {
                        if (filled($state)) {
                            return;
                        }

                        /** @var Subject|null $record */
                        $record = $component->getRecord();

                        $component->state($record?->department?->faculty_id);
                    })
                    ->afterStateUpdated(function (Set $set, ?string $state, ?string $old): void {
                        if ($state !== $old) {
                            $set('department_id', null);
                        }
                    }),

                Forms\Components\Select::make('department_id')
                    ->label(__('subjects.department_id'))
                    ->options(fn (Get $get): array => static::getDepartmentOptionsForFaculty($get('faculty_id')))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->disabled(fn (Get $get): bool => blank($get('faculty_id')))
                    ->placeholder(__('subjects.select_faculty_first'))
                    ->helperText(__('subjects.department_helper_text'))
                    ->noSearchResultsMessage(__('subjects.no_departments_for_selected_faculty')),

                Forms\Components\TextInput::make('code')
                    ->label(__('subjects.code'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Forms\Components\TextInput::make('name')
                    ->label(__('subjects.name'))
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('subject_type')
                    ->label(__('subjects.subject_type'))
                    ->options(fn (): array => Subject::subjectTypeOptions())
                    ->native(false)
                    ->required()
                    ->default(Subject::TYPE_THEORETICAL),

                Forms\Components\Toggle::make('is_active')
                    ->label(__('subjects.is_active'))
                    ->default(true)
                    ->required(),

                Forms\Components\Select::make('lecturer_id')
                    ->label(__('subjects.lecturer'))
                    ->options(fn() => \App\Models\User::query()
                        ->withoutTrashed()
                        ->whereHas('roles', fn($q) => $q->where('name', 'course_lecturer'))
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withCount('sections')
                ->with(['sections' => fn ($sectionsQuery) => $sectionsQuery
                    ->select(['id', 'subject_id', 'code'])
                    ->orderBy('code'),
                ]))
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('subjects.code'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('subjects.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label(__('subjects.subject_type'))
                    ->formatStateUsing(fn (Subject $record): string => $record->subject_type_label)
                    ->badge()
                    ->color(fn (?string $state): string => $state === Subject::TYPE_PRACTICAL ? 'success' : 'info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sections_count')
                    ->label(__('subjects.sections_count'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('section_codes')
                    ->label(__('subjects.section_codes'))
                    ->state(fn (Subject $record): string => $record->sections
                        ->pluck('code')
                        ->filter()
                        ->take(5)
                        ->implode(', '))
                    ->placeholder(__('subjects.not_available'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('lecturer.name')
                    ->label(__('subjects.lecturer'))
                    ->formatStateUsing(fn (?string $state): string => $state ?: __('subjects.not_assigned'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->label(__('subjects.department_id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label(__('subjects.deleted_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('subjects.is_active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department')
                    ->label(__('subjects.department_id'))
                    ->relationship(
                        name: 'department',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->withoutTrashed(),
                    ),
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('subjects.is_active')),
                Tables\Filters\SelectFilter::make('subject_type')
                    ->label(__('subjects.subject_type'))
                    ->options(fn (): array => Subject::subjectTypeOptions()),
            ])
            ->actions([
                EditAction::make()
                    ->label(__('subjects.edit'))
                    ->visible(fn (Subject $record): bool => ! $record->trashed()),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->hasRole('super-admin')),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->hasRole('super-admin')),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SubjectSectionsRelationManager::class,
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
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        if (auth()->user()->hasRole('course_lecturer')) {
            return $query->where('lecturer_id', auth()->id());
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['super-admin', 'admin', 'manager']);
    }

    public static function getFacultyOptions(): array
    {
        return Faculty::query()
            ->withoutTrashed()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function getDepartmentOptionsForFaculty(int | string | null $facultyId): array
    {
        if (blank($facultyId)) {
            return [];
        }

        return Department::query()
            ->withoutTrashed()
            ->where('is_active', true)
            ->where('faculty_id', $facultyId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function validateSubjectFacultyDepartment(array $data): array
    {
        $facultyId = $data['faculty_id'] ?? null;
        $departmentId = $data['department_id'] ?? null;

        if (blank($facultyId) || blank($departmentId)) {
            return static::stripTransientSubjectFormState($data);
        }

        $belongsToFaculty = Department::query()
            ->withoutTrashed()
            ->whereKey($departmentId)
            ->where('faculty_id', $facultyId)
            ->exists();

        if (! $belongsToFaculty) {
            throw ValidationException::withMessages([
                'department_id' => __('subjects.department_not_in_selected_faculty'),
            ]);
        }

        return static::stripTransientSubjectFormState($data);
    }

    public static function stripTransientSubjectFormState(array $data): array
    {
        unset($data['faculty_id']);

        return $data;
    }
}
