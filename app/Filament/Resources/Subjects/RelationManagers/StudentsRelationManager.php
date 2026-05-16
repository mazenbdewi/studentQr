<?php

namespace App\Filament\Resources\Subjects\RelationManagers;

use App\Filament\Resources\Students\StudentResource;
use App\Models\Enrollment;
use App\Models\Student;
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

class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('student.enrolled_students');
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['student', 'theoreticalSection', 'practicalSection']))
            ->defaultSort('student_id')
            ->recordTitle(fn (Enrollment $record): string => $record->student?->name ?? __('student.record_title'))
            ->columns([
                Tables\Columns\TextColumn::make('student.student_number')
                    ->label(__('student.student_number'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('student.name')
                    ->label(__('student.name'))
                    ->url(fn (Enrollment $record): string => StudentResource::getUrl('view', ['record' => $record->student]))
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
                    ->label(__('enrollments.attach_student'))
                    ->modalHeading(__('enrollments.create_student_enrollment'))
                    ->modalSubmitActionLabel(__('enrollments.save_enrollment'))
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->schema($this->getEnrollmentFormSchema())
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'status' => $data['status'] ?? Enrollment::STATUS_ENROLLED,
                    ])
                    ->successNotificationTitle(__('filament-actions::create.single.notifications.created.title')),
            ])
            ->actions([
                EditAction::make()
                    ->label(__('enrollments.edit'))
                    ->modalHeading(__('enrollments.edit_student_enrollment'))
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
            Forms\Components\Select::make('student_id')
                ->label(__('enrollments.student'))
                ->relationship(
                    name: 'student',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn (Builder $query) => $query
                        ->withoutTrashed()
                        ->whereDoesntHave(
                            'enrollments',
                            fn (Builder $enrollmentsQuery) => $enrollmentsQuery->where('subject_id', $this->ownerRecord->getKey()),
                        ),
                )
                ->getOptionLabelFromRecordUsing(
                    fn (Student $record): string => filled($record->student_number)
                        ? "{$record->student_number} - {$record->name}"
                        : $record->name,
                )
                ->searchable(['student_number', 'name', 'national_number'])
                ->optionsLimit(50)
                ->required()
                ->rule(
                    Rule::unique('enrollments', 'student_id')
                        ->where(fn ($query) => $query->where('subject_id', $this->ownerRecord->getKey())),
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
                ->options(fn (): array => $this->sectionOptions(Subject::TYPE_THEORETICAL))
                ->searchable()
                ->preload()
                ->nullable(),

            Forms\Components\Select::make('practical_section_id')
                ->label(__('enrollments.practical_section'))
                ->options(fn (): array => $this->sectionOptions(Subject::TYPE_PRACTICAL))
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
                ->default(fn (): ?int => $this->ownerRecord->level)
                ->required(),

            Forms\Components\Select::make('status')
                ->label(__('enrollments.status'))
                ->options(Enrollment::statusOptions())
                ->default(Enrollment::STATUS_ENROLLED)
                ->required(),
        ];
    }

    protected function sectionOptions(string $sectionType): array
    {
        return SubjectSection::query()
            ->where('subject_id', $this->ownerRecord->getKey())
            ->where('section_type', $sectionType)
            ->orderBy('code')
            ->pluck('code', 'id')
            ->all();
    }
}
