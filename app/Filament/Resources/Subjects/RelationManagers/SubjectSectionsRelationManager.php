<?php

namespace App\Filament\Resources\Subjects\RelationManagers;

use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class SubjectSectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('subjects.sections');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        if (! $ownerRecord instanceof Subject) {
            return null;
        }

        return (string) $ownerRecord->sections()->count();
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->modelLabel(__('subjects.section'))
            ->pluralModelLabel(__('subjects.sections'))
            ->defaultSort('code')
            ->recordTitle(fn (SubjectSection $record): string => $record->code)
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('subjects.section_code'))
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('section_type')
                    ->label(__('subjects.section_type'))
                    ->formatStateUsing(fn (?string $state): string => Subject::subjectTypeOptions()[SubjectSection::normalizeSectionType($state)] ?? __('subjects.not_available'))
                    ->badge()
                    ->color(fn (?string $state): string => SubjectSection::normalizeSectionType($state) === Subject::TYPE_PRACTICAL ? 'success' : 'info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('lecturer.name')
                    ->label(__('subjects.lecturer'))
                    ->state(function (SubjectSection $record): string {
                        /** @var User|null $lecturer */
                        $lecturer = $record->lecturer;

                        if ($record->hasAmbiguousImportedLecturers()) {
                            return __('subjects.multiple_lecturers_needs_resolution');
                        }

                        return $lecturer ? (string) $lecturer->name : __('subjects.not_specified');
                    })
                    ->color(fn (SubjectSection $record): ?string => $record->hasAmbiguousImportedLecturers() ? 'warning' : null)
                    ->description(function (SubjectSection $record): ?string {
                        /** @var User|null $lecturer */
                        $lecturer = $record->lecturer;

                        return $lecturer ? trim(implode(' · ', array_filter([
                            $lecturer->login_username,
                            $lecturer->status === 'active' && $lecturer->is_active ? __('subjects.account_active') : __('subjects.account_inactive'),
                        ]))) : null;
                    })
                    ->searchable()
                    ->sortable(),

            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('subjects.add_section'))
                    ->icon('heroicon-o-plus')
                    ->schema($this->getSectionFormSchema())
                    ->mutateDataUsing(fn (array $data): array => $this->normalizeSectionData($data)),
            ])
            ->actions([
                EditAction::make()
                    ->label(__('subjects.edit'))
                    ->schema($this->getSectionFormSchema())
                    ->mutateDataUsing(fn (array $data): array => $this->normalizeSectionData($data)),

                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('subjects.delete_selected')),
                ]),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    protected function getSectionFormSchema(): array
    {
        /** @var Subject $subject */
        $subject = $this->ownerRecord;

        return [
            Forms\Components\TextInput::make('code')
                ->label(__('subjects.section_code'))
                ->default(fn (Get $get): string => SubjectSection::nextCodeForSubject(
                    $subject,
                    SubjectSection::normalizeSectionType($get('section_type')) ?? $subject->subject_type,
                ))
                ->required()
                ->live(onBlur: true)
                ->maxLength(20)
                ->dehydrateStateUsing(fn (mixed $state, Get $get): string => SubjectSection::normalizeCodeForType($state, $get('section_type')))
                ->rule(fn (?SubjectSection $record, Get $get) => Rule::unique('subject_sections', 'code')
                    ->where('subject_id', $subject->id)
                    ->where('section_type', SubjectSection::normalizeSectionType($get('section_type')))
                    ->ignore($record)),

            Forms\Components\Select::make('section_type')
                ->label(__('subjects.section_type'))
                ->options(fn (): array => Subject::subjectTypeOptions())
                ->default(fn (): string => $subject->subject_type)
                ->native(false)
                ->live()
                ->required()
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    $set('code', SubjectSection::normalizeCodeForType(1, $state));
                }),

            Forms\Components\TextInput::make('raw_section_number')
                ->label(__('subjects.raw_section_number'))
                ->maxLength(20),

            Forms\Components\Select::make('lecturer_id')
                ->label(__('subjects.lecturer'))
                ->options(fn (): array => User::query()
                    ->withoutTrashed()
                    ->whereHas('roles', fn ($query) => $query->where('name', 'course_lecturer'))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->preload()
                ->native(false)
                ->helperText(__('subjects.lecturer_helper')),
        ];
    }

    protected function normalizeSectionData(array $data): array
    {
        if (array_key_exists('code', $data)) {
            $data['code'] = SubjectSection::normalizeCodeForType($data['code'], $data['section_type'] ?? null);
            $data['raw_section_number'] = $data['raw_section_number']
                ?? SubjectSection::rawSectionNumberFromCode($data['code']);
        }

        return $data;
    }
}
