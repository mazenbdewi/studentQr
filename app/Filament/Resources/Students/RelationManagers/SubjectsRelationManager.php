<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Filament\Resources\Subjects\SubjectResource;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\SubjectSection;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class SubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('enrollments.enrolled_subjects');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->enrollments()->count();
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->modelLabel(__('enrollments.singular'))
            ->pluralModelLabel(__('enrollments.plural'))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['subject', 'theoreticalSection', 'practicalSection']))
            ->defaultSort('subject_id')
            ->recordTitle(fn (Enrollment $record): string => $record->subject?->name ?? __('subjects.record_title'))
            ->columns([
                Tables\Columns\TextColumn::make('subject.code')
                    ->label(__('subjects.code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject.name')
                    ->label(__('subjects.name'))
                    ->url(fn (Enrollment $record): string => SubjectResource::getUrl('view', ['record' => $record->subject]))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('theoreticalSection.code')
                    ->label(__('enrollments.theoretical_section'))
                    ->placeholder(__('subjects.not_available'))
                    ->badge(),

                Tables\Columns\TextColumn::make('practicalSection.code')
                    ->label(__('enrollments.practical_section'))
                    ->placeholder(__('subjects.not_available'))
                    ->badge(),

                Tables\Columns\TextColumn::make('registration_date')
                    ->label(__('enrollments.registration_date'))
                    ->date()
                    ->placeholder(__('subjects.not_available'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('year')
                    ->label(__('enrollments.year'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('enrollments.status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? __("enrollments.{$state}") : '')
                    ->color(fn (?string $state): string => match ($state) {
                        Enrollment::STATUS_ENROLLED => 'success',
                        Enrollment::STATUS_DROPPED => 'danger',
                        Enrollment::STATUS_PASSED => 'info',
                        Enrollment::STATUS_FAILED => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('enrollments.status'))
                    ->options(Enrollment::statusOptions()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('enrollments.add_subject'))
                    ->modalHeading(__('enrollments.create_subject_enrollment'))
                    ->modalSubmitActionLabel(__('enrollments.save_enrollment'))
                    ->schema($this->getEnrollmentFormSchema()),
            ])
            ->actions([
                EditAction::make()
                    ->label(__('enrollments.edit'))
                    ->modalHeading(__('enrollments.edit_subject_enrollment'))
                    ->modalSubmitActionLabel(__('enrollments.save_enrollment'))
                    ->schema($this->getEnrollmentMetadataSchema()),

                DeleteAction::make()
                    ->label(__('enrollments.delete')),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('enrollments.delete_selected')),
                ]),
            ]);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected function getEnrollmentFormSchema(): array
    {
        return [
            Forms\Components\Select::make('subject_id')
                ->label(__('enrollments.subject'))
                ->relationship(
                    name: 'subject',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn (Builder $query) => $query
                        ->withoutTrashed()
                        ->whereDoesntHave(
                            'enrollments',
                            fn (Builder $enrollmentsQuery) => $enrollmentsQuery->where('student_id', $this->ownerRecord->getKey()),
                        ),
                )
                ->getOptionLabelFromRecordUsing(
                    fn (Subject $record): string => filled($record->code)
                        ? "{$record->code} - {$record->name}"
                        : $record->name,
                )
                ->searchable(['code', 'name'])
                ->optionsLimit(50)
                ->required()
                ->live()
                ->afterStateUpdated(function (?string $state, callable $set): void {
                    $subject = Subject::query()
                        ->select(['id', 'level'])
                        ->find($state);

                    if (! $subject) {
                        return;
                    }

                    $set('year', $subject->level);
                    $set('theoretical_section_id', null);
                    $set('practical_section_id', null);
                })
                ->rule(
                    Rule::unique('enrollments', 'subject_id')
                        ->where(fn ($query) => $query->where('student_id', $this->ownerRecord->getKey())),
                )
                ->validationMessages([
                    'unique' => __('student.already_enrolled'),
                ]),

            ...$this->getEnrollmentMetadataSchema(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected function getEnrollmentMetadataSchema(): array
    {
        return [
            Forms\Components\Select::make('theoretical_section_id')
                ->label(__('enrollments.theoretical_section'))
                ->options(fn (?Enrollment $record, callable $get): array => $this->sectionOptions(
                    $get('subject_id') ?: $record?->subject_id,
                    Subject::TYPE_THEORETICAL,
                ))
                ->searchable()
                ->preload()
                ->nullable(),

            Forms\Components\Select::make('practical_section_id')
                ->label(__('enrollments.practical_section'))
                ->options(fn (?Enrollment $record, callable $get): array => $this->sectionOptions(
                    $get('subject_id') ?: $record?->subject_id,
                    Subject::TYPE_PRACTICAL,
                ))
                ->searchable()
                ->preload()
                ->nullable(),

            Forms\Components\DatePicker::make('registration_date')
                ->label(__('enrollments.registration_date')),

            Forms\Components\TextInput::make('year')
                ->label(__('enrollments.year'))
                ->numeric()
                ->minValue(1)
                ->maxValue(6)
                ->required(),

            Forms\Components\Select::make('status')
                ->label(__('enrollments.status'))
                ->options(Enrollment::statusOptions())
                ->default(Enrollment::STATUS_ENROLLED)
                ->required(),
        ];
    }

    protected function sectionOptions(mixed $subjectId, string $sectionType): array
    {
        if (blank($subjectId)) {
            return [];
        }

        return SubjectSection::query()
            ->where('subject_id', $subjectId)
            ->where('section_type', $sectionType)
            ->orderBy('code')
            ->pluck('code', 'id')
            ->all();
    }
}
