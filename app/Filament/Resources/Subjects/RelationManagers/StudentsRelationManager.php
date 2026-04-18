<?php

namespace App\Filament\Resources\Subjects\RelationManagers;

use App\Exports\Templates\SubjectStudentsTemplateExport;
use App\Filament\Resources\Students\StudentResource;
use App\Imports\SubjectStudentsImport;
use App\Models\Enrollment;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('student'))
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
                    ->label(__('enrollments.attach_student'))
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->schema($this->getEnrollmentFormSchema())
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'status' => $data['status'] ?? Enrollment::STATUS_ENROLLED,
                    ])
                    ->successNotificationTitle(__('filament-actions::create.single.notifications.created.title')),

                Action::make('download_subject_students_template')
                    ->label(__('subjects.download_subject_students_template'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(fn () => Excel::download(
                        new SubjectStudentsTemplateExport($this->ownerRecord),
                        $this->ownerRecord->name . '_students_template.xlsx',
                    )),

                Action::make('import_students')
                    ->label(__('subjects.import_students'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->schema([
                        Forms\Components\FileUpload::make('file')
                            ->label(__('subjects.excel_file'))
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                            ])
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        try {
                            Excel::import(new SubjectStudentsImport($this->ownerRecord->id), $data['file']);

                            Notification::make()
                                ->title(__('subjects.import_success'))
                                ->success()
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title(__('subjects.import_failed'))
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
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
            Forms\Components\TextInput::make('semester')
                ->label(__('enrollments.semester'))
                ->numeric()
                ->minValue(1)
                ->maxValue(2)
                ->default(fn (): ?int => $this->ownerRecord->semester)
                ->required(),

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
}
