<?php

namespace App\Filament\Pages;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\Lecturer;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportRow;
use App\Models\Subject;
use App\Models\SubjectSectionScheduleSlot;
use App\Support\AcademicTermContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WeeklySchedule extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'weekly-schedule';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CalendarDays;

    protected string $view = 'filament.pages.weekly-schedule';

    public function mount(): void
    {
        $batchUuid = request()->query('batch');

        if (! is_string($batchUuid) || $batchUuid === '') {
            return;
        }

        $batch = ImportBatch::query()
            ->where('uuid', $batchUuid)
            ->where('import_type', ImportBatch::TYPE_WEEKLY_SCHEDULE)
            ->with('academicTerms:id')
            ->firstOrFail();

        $this->tableFilters = [
            'academic_term_id' => ['value' => (string) $batch->academicTerms->sole()->id],
            'import_batch_id' => ['value' => (string) $batch->id],
        ];
    }

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->can('viewAny', SubjectSectionScheduleSlot::class);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('weekly-schedule.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('weekly-schedule.navigation.view');
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public function getTitle(): string
    {
        return __('weekly-schedule.title');
    }

    public function currentTermWarning(): ?string
    {
        return app(AcademicTermContext::class)->current() ? null : 'لا يوجد فصل دراسي حالي محدد. يرجى تعيين الفصل الدراسي الحالي.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(SubjectSectionScheduleSlot::query()->forCurrentAcademicTerm()->with([
                'academicTerm',
                'subject.department.faculty',
                'subjectSection',
                'lecturer',
                'hall',
                'importBatch',
            ]))
            ->columns([
                TextColumn::make('academicTerm.display_name')
                    ->label(__('weekly-schedule.columns.academic_term'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('subject.code')
                    ->label(__('weekly-schedule.columns.subject_code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject.name')
                    ->label(__('weekly-schedule.columns.subject_name'))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('subjectSection.code')
                    ->label(__('weekly-schedule.columns.section_code'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('subjectSection.section_type')
                    ->label(__('weekly-schedule.columns.section_type'))
                    ->formatStateUsing(fn (?string $state): string => Subject::subjectTypeOptions()[$state] ?? __('weekly-schedule.not_specified')),
                TextColumn::make('lecturer_name')
                    ->label(__('weekly-schedule.columns.lecturer'))
                    ->state(fn (SubjectSectionScheduleSlot $record): string => $record->lecturer?->name
                        ?: $record->raw_teacher_name
                        ?: __('weekly-schedule.not_specified'))
                    ->wrap(),
                TextColumn::make('hall_name')
                    ->label(__('weekly-schedule.columns.hall'))
                    ->state(fn (SubjectSectionScheduleSlot $record): string => $record->hall?->name
                        ?: $record->raw_hall_name
                        ?: __('weekly-schedule.not_specified'))
                    ->wrap(),
                TextColumn::make('weekday')
                    ->label(__('weekly-schedule.columns.weekday'))
                    ->formatStateUsing(fn (int|string|null $state): string => self::weekdayOptions()[(int) $state] ?? __('weekly-schedule.not_specified'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label(__('weekly-schedule.columns.start_time'))
                    ->formatStateUsing(fn (?string $state): string => substr((string) $state, 0, 5))
                    ->sortable(),
                TextColumn::make('end_time')
                    ->label(__('weekly-schedule.columns.end_time'))
                    ->formatStateUsing(fn (?string $state): string => substr((string) $state, 0, 5))
                    ->sortable(),
                TextColumn::make('section_capacity')
                    ->label(__('weekly-schedule.columns.section_capacity'))
                    ->numeric()
                    ->placeholder(__('weekly-schedule.not_specified'))
                    ->sortable(),
                TextColumn::make('expected_student_count')
                    ->label(__('weekly-schedule.columns.expected_students'))
                    ->numeric()
                    ->placeholder(__('weekly-schedule.not_specified'))
                    ->sortable(),
                TextColumn::make('importBatch.source_filename')
                    ->label(__('weekly-schedule.columns.import_batch'))
                    ->description(function (SubjectSectionScheduleSlot $record): ?string {
                        $batch = $record->importBatch;

                        return $batch instanceof ImportBatch ? $batch->uuid : null;
                    })
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('faculty_id')
                    ->label(__('weekly-schedule.filters.faculty'))
                    ->options(fn (): array => Faculty::query()->withoutTrashed()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->whereHas(
                            'subject.department',
                            fn (Builder $department): Builder => $department->where('faculty_id', $data['value']),
                        ),
                    ))
                    ->searchable()
                    ->preload(),
                SelectFilter::make('department_id')
                    ->label(__('weekly-schedule.filters.department'))
                    ->options(fn (): array => Department::query()->withoutTrashed()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->whereHas(
                            'subject',
                            fn (Builder $subject): Builder => $subject->where('department_id', $data['value']),
                        ),
                    ))
                    ->searchable()
                    ->preload(),
                SelectFilter::make('subject_id')
                    ->label(__('weekly-schedule.filters.subject'))
                    ->options(fn (): array => Subject::query()->withoutTrashed()->orderBy('code')->get()
                        ->mapWithKeys(fn (Subject $subject): array => [$subject->id => "{$subject->code} — {$subject->name}"])
                        ->all())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('section_type')
                    ->label(__('weekly-schedule.filters.section_type'))
                    ->options(fn (): array => Subject::subjectTypeOptions())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->whereHas(
                            'subjectSection',
                            fn (Builder $section): Builder => $section->where('section_type', $data['value']),
                        ),
                    )),
                SelectFilter::make('lecturer_id')
                    ->label(__('weekly-schedule.filters.lecturer'))
                    ->options(fn (): array => Lecturer::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('hall_id')
                    ->label(__('weekly-schedule.filters.hall'))
                    ->options(fn (): array => Hall::query()->withoutTrashed()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('weekday')
                    ->label(__('weekly-schedule.filters.weekday'))
                    ->options(self::weekdayOptions()),
                SelectFilter::make('import_batch_id')
                    ->label(__('weekly-schedule.filters.import_batch'))
                    ->options(fn (): array => ImportBatch::query()
                        ->where('import_type', ImportBatch::TYPE_WEEKLY_SCHEDULE)
                        ->latest('id')
                        ->get(['id', 'uuid', 'source_filename'])
                        ->mapWithKeys(fn (ImportBatch $batch): array => [
                            $batch->id => "{$batch->source_filename} — {$batch->uuid}",
                        ])->all())
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('weekday')
            ->paginated([25, 50, 100]);
    }

    /** @return array<string, int> */
    public function summaryCounts(): array
    {
        $query = $this->getFilteredTableQuery() ?? SubjectSectionScheduleSlot::query();

        return [
            'total' => (clone $query)->count(),
            'subjects' => (clone $query)->distinct()->count('subject_id'),
            'theoretical_sections' => (clone $query)
                ->whereHas('subjectSection', fn (Builder $section): Builder => $section->where('section_type', Subject::TYPE_THEORETICAL))
                ->distinct()
                ->count('subject_section_id'),
            'practical_sections' => (clone $query)
                ->whereHas('subjectSection', fn (Builder $section): Builder => $section->where('section_type', Subject::TYPE_PRACTICAL))
                ->distinct()
                ->count('subject_section_id'),
            'lecturers' => (clone $query)->whereNotNull('lecturer_id')->distinct()->count('lecturer_id'),
            'halls' => (clone $query)->whereNotNull('hall_id')->distinct()->count('hall_id'),
            'needs_review' => $this->needsReviewCount(),
        ];
    }

    /** @return array<int, string> */
    public static function weekdayOptions(): array
    {
        return __('weekly-schedule.weekdays');
    }

    private function needsReviewCount(): int
    {
        $query = ScheduleImportRow::query()
            ->whereNotIn('current_reconciliation_status', [
                ScheduleImportRow::STATUS_IGNORED,
                ScheduleImportRow::STATUS_INTENTIONALLY_UNSCHEDULED,
                ScheduleImportRow::STATUS_EXCLUDED_FROM_BATCH_SCHEDULE,
            ])
            ->whereHas('issues', fn (Builder $issues): Builder => $issues->whereIn('resolution_status', [
                ScheduleImportIssue::STATUS_UNRESOLVED,
                ScheduleImportIssue::STATUS_RETRY_FAILED,
            ]));

        $academicTermId = data_get($this->getTableFilterState('academic_term_id'), 'value');
        $importBatchId = data_get($this->getTableFilterState('import_batch_id'), 'value');

        return $query
            ->when(filled($academicTermId), fn (Builder $query): Builder => $query->where('academic_term_id', $academicTermId))
            ->when(filled($importBatchId), fn (Builder $query): Builder => $query->where('import_batch_id', $importBatchId))
            ->count();
    }

    /** @return array<string, int|string|null> */
    public function currentReportFilters(): array
    {
        return app(\App\Services\WeeklyScheduleReportService::class)->normalizeFilters([
            'academic_term_id' => data_get($this->getTableFilterState('academic_term_id'), 'value'),
            'import_batch_id' => data_get($this->getTableFilterState('import_batch_id'), 'value'),
            'faculty_id' => data_get($this->getTableFilterState('faculty_id'), 'value'),
            'department_id' => data_get($this->getTableFilterState('department_id'), 'value'),
            'subject_id' => data_get($this->getTableFilterState('subject_id'), 'value'),
            'section_type' => data_get($this->getTableFilterState('section_type'), 'value'),
            'lecturer_id' => data_get($this->getTableFilterState('lecturer_id'), 'value'),
            'hall_id' => data_get($this->getTableFilterState('hall_id'), 'value'),
            'weekday' => data_get($this->getTableFilterState('weekday'), 'value'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $queryParameters = fn (): array => array_filter($this->currentReportFilters(), fn ($value): bool => $value !== null);

        return [
            Action::make('reports')
                ->label(__('weekly-schedule.actions.reports'))
                ->icon(Heroicon::DocumentChartBar)
                ->url(fn (): string => WeeklyScheduleReports::getUrl($queryParameters())),
            Action::make('excel')
                ->label(__('weekly-schedule.actions.excel'))
                ->icon(Heroicon::ArrowDownTray)
                ->visible(fn (): bool => Filament::auth()->user()?->can('export', SubjectSectionScheduleSlot::class) ?? false)
                ->url(fn (): string => route('admin.weekly-schedule-reports.excel', ['type' => \App\Services\WeeklyScheduleReportService::COMPREHENSIVE, ...$queryParameters()], false)),
            Action::make('print')
                ->label(__('weekly-schedule.actions.print'))
                ->icon(Heroicon::Printer)
                ->visible(fn (): bool => Filament::auth()->user()?->can('export', SubjectSectionScheduleSlot::class) ?? false)
                ->url(fn (): string => route('admin.weekly-schedule-reports.pdf', ['type' => \App\Services\WeeklyScheduleReportService::COMPREHENSIVE, ...$queryParameters()], false)),
            Action::make('reconciliation')
                ->label(__('weekly-schedule.actions.reconciliation'))
                ->icon(Heroicon::ClipboardDocumentCheck)
                ->url(function () use ($queryParameters): string {
                    $batchId = $queryParameters()['import_batch_id'] ?? null;
                    $batch = $batchId ? ImportBatch::find($batchId) : null;

                    return $batch instanceof ImportBatch
                        ? ScheduleImportReconciliationReport::getUrl(['batch' => $batch->uuid])
                        : ScheduleImportReconciliationIndex::getUrl();
                }),
        ];
    }
}
