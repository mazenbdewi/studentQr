<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Filament\Resources\Subjects\SubjectResource;
use App\Models\Enrollment;
use App\Models\Subject;
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('subject'))
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

                Tables\Columns\TextColumn::make('semester')
                    ->label(__('enrollments.semester'))
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
                    ->schema($this->getEnrollmentFormSchema()),
            ])
            ->actions([
                EditAction::make()
                    ->schema($this->getEnrollmentMetadataSchema()),

                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
                        ->select(['id', 'semester', 'level'])
                        ->find($state);

                    if (! $subject) {
                        return;
                    }

                    $set('semester', $subject->semester);
                    $set('year', $subject->level);
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
            Forms\Components\TextInput::make('semester')
                ->label(__('enrollments.semester'))
                ->numeric()
                ->minValue(1)
                ->maxValue(2)
                ->required(),

            Forms\Components\TextInput::make('year')
                ->label(__('enrollments.year'))
                ->numeric()
                ->minValue(1)
                ->maxValue(6)
                ->default(fn (): ?int => $this->ownerRecord->year)
                ->required(),

            Forms\Components\Select::make('status')
                ->label(__('enrollments.status'))
                ->options(Enrollment::statusOptions())
                ->default(Enrollment::STATUS_ENROLLED)
                ->required(),
        ];
    }
}
