<?php

namespace App\Filament\Pages;

use App\Models\AcademicTerm;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\Lecturer;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportRow;
use App\Models\Subject;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Policies\ScheduleImportRowPolicy;
use App\Services\FutureLectureSessionAssignmentService;
use App\Services\LectureSessionGenerationService;
use App\Services\WeeklyScheduleSlotConflictDetector;
use App\Support\AcademicTermContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
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

    public ?int $resolvedAcademicTermId = null;

    public ?int $resolvedImportBatchId = null;

    /** @var array<string, mixed>|null */
    private ?array $readinessSummaryCache = null;

    public function mount(): void
    {
        $batchUuid = request()->query('batch');

        if (! is_string($batchUuid) || $batchUuid === '') {
            $this->resolvedAcademicTermId = app(AcademicTermContext::class)->currentId();

            if ($this->resolvedAcademicTermId) {
                $this->resolvedImportBatchId = $this->latestBatchIdForTerm($this->resolvedAcademicTermId);
                $this->setBatchTableFilter();
            }

            return;
        }

        $batch = ImportBatch::query()
            ->where('uuid', $batchUuid)
            ->where('import_type', ImportBatch::TYPE_WEEKLY_SCHEDULE)
            ->with('academicTerms:id')
            ->firstOrFail();
        $term = $batch->academicTerms->sole();
        $this->resolvedAcademicTermId = (int) $term->id;
        $this->resolvedImportBatchId = (int) $batch->id;

        $this->setBatchTableFilter();
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
        return $this->resolvedAcademicTermId ? null : __('academic-term.no_current');
    }

    public function resolvedAcademicTerm(): ?\App\Models\AcademicTerm
    {
        return $this->resolvedAcademicTermId
            ? \App\Models\AcademicTerm::query()->find($this->resolvedAcademicTermId)
            : null;
    }

    /** @return array<string, mixed> */
    public function readinessSummary(): array
    {
        if ($this->readinessSummaryCache !== null) {
            return $this->readinessSummaryCache;
        }

        $empty = [
            'status' => 'no_term',
            'total_weekly_slots' => 0,
            'ready_slots' => 0,
            'blocked_slots' => 0,
            'missing_lecturer_slots' => 0,
            'missing_hall_slots' => 0,
            'placeholder_lecturer_slots' => 0,
            'placeholder_hall_slots' => 0,
            'real_conflict_slots' => 0,
            'invalid_day_time_slots' => 0,
            'missing_subject_rows' => 0,
            'missing_subject_codes' => 0,
            'missing_section_rows' => 0,
            'excluded_rows' => 0,
            'teaching_dates_missing' => true,
            'teaching_dates_invalid' => false,
            'prerequisite_errors' => [],
            'batch_id' => null,
            'batch_uuid' => null,
        ];
        $term = $this->resolvedAcademicTerm();

        if (! $term instanceof AcademicTerm) {
            return $this->readinessSummaryCache = $empty;
        }

        $batchId = $this->contextBatchId();
        $batch = $batchId ? ImportBatch::query()->find($batchId) : null;
        $generation = app(LectureSessionGenerationService::class);
        $structural = $generation->structuralReadiness($term, $batchId);
        $prerequisiteErrors = $generation->prerequisiteErrorsForTerm($term, $batchId);
        $subjectTypes = [
            ScheduleImportIssue::TYPE_SUBJECT_NOT_FOUND,
            ScheduleImportIssue::TYPE_SUBJECT_NOT_UNIQUE,
            ScheduleImportIssue::TYPE_NON_AUTHORITATIVE_SUBJECT_CODE,
            ScheduleImportIssue::TYPE_ZERO_STUDENT_SUBJECT_MISSING,
        ];
        $sectionTypes = [
            ScheduleImportIssue::TYPE_SECTION_NOT_FOUND,
            ScheduleImportIssue::TYPE_ZERO_STUDENT_SECTION_MISSING,
        ];
        $rows = ScheduleImportRow::query()
            ->where('academic_term_id', $term->id)
            ->when($batchId, fn (Builder $query): Builder => $query->where('import_batch_id', $batchId));
        $withUnresolvedIssueTypes = fn (Builder $query, array $types): Builder => $query->whereHas('issues', fn (Builder $issues): Builder => $issues
            ->whereIn('issue_type', $types)
            ->whereIn('resolution_status', [ScheduleImportIssue::STATUS_UNRESOLVED, ScheduleImportIssue::STATUS_RETRY_FAILED]));
        $subjectRows = $withUnresolvedIssueTypes(clone $rows, $subjectTypes);
        $sectionRows = $withUnresolvedIssueTypes(clone $rows, $sectionTypes);
        $excludedRows = (clone $rows)->whereIn('current_reconciliation_status', [
            ScheduleImportRow::STATUS_IGNORED,
            ScheduleImportRow::STATUS_INTENTIONALLY_UNSCHEDULED,
            ScheduleImportRow::STATUS_EXCLUDED_FROM_BATCH_SCHEDULE,
        ]);
        $excludedRecords = (clone $excludedRows)->get(['id', 'import_result']);
        $excludedSlotIds = $excludedRecords->flatMap(fn (ScheduleImportRow $row): array => $row->relatedScheduleSlotIds())->unique()->values()->all();
        $realConflictSlotIds = app(WeeklyScheduleSlotConflictDetector::class)->affectedSlotIds($term, $batchId, $excludedSlotIds);
        $subjectRowCount = (clone $subjectRows)->count();
        $sectionRowCount = (clone $sectionRows)->count();
        $blocking = $structural['blocked_slots'] + count($realConflictSlotIds) + $subjectRowCount + $sectionRowCount + count($prerequisiteErrors);
        $datesMissing = blank($term->teaching_start_date) || blank($term->teaching_end_date);
        $datesInvalid = ! $datesMissing && $term->teaching_end_date < $term->teaching_start_date;

        return $this->readinessSummaryCache = [
            'status' => $blocking > 0 || $datesMissing || $datesInvalid ? 'blocked' : (($structural['placeholder_lecturer_slots'] + $structural['placeholder_hall_slots']) > 0 ? 'placeholders' : 'complete'),
            'total_weekly_slots' => $structural['total_weekly_slots'],
            'ready_slots' => $structural['ready_slots'],
            'blocked_slots' => $structural['blocked_slots'],
            'missing_lecturer_slots' => $structural['slots_without_lecturer_identity'],
            'missing_hall_slots' => $structural['slots_without_halls'],
            'placeholder_lecturer_slots' => $structural['placeholder_lecturer_slots'],
            'placeholder_hall_slots' => $structural['placeholder_hall_slots'],
            'real_conflict_slots' => count($realConflictSlotIds),
            'invalid_day_time_slots' => $structural['invalid_day_time_slots'],
            'missing_subject_rows' => $subjectRowCount,
            'missing_subject_codes' => (clone $subjectRows)->pluck('normalized_payload')->map(fn (mixed $payload): mixed => is_array($payload) ? ($payload['subject_code_key'] ?? null) : null)->filter()->unique()->count(),
            'missing_section_rows' => $sectionRowCount,
            'excluded_rows' => $excludedRecords->count(),
            'teaching_dates_missing' => $datesMissing,
            'teaching_dates_invalid' => $datesInvalid,
            'prerequisite_errors' => $prerequisiteErrors,
            'batch_id' => $batch?->id,
            'batch_uuid' => $batch?->uuid,
        ];
    }

    public function scheduleIssuesUrl(): string
    {
        $batchId = $this->contextBatchId();

        return ScheduleImportIssues::getUrl($batchId ? ['batch' => $batchId] : []);
    }

    public function reconciliationUrl(): ?string
    {
        $summary = $this->readinessSummary();

        if (! $summary['batch_uuid'] || ((int) $summary['missing_subject_rows'] === 0 && (int) $summary['missing_section_rows'] === 0)) {
            return null;
        }

        $issue = (int) $summary['missing_subject_rows'] > 0 ? 'missing_subject' : 'missing_section';

        return ScheduleImportReconciliationReport::getUrl(['batch' => $summary['batch_uuid'], 'issue' => $issue]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(SubjectSectionScheduleSlot::query()
                ->when(
                    $this->resolvedAcademicTermId,
                    fn (Builder $query, int $termId): Builder => $query->forAcademicTerm($termId),
                    fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
                )
                ->with([
                    'academicTerm',
                    'subject',
                    'subjectSection',
                    'lecturer',
                    'hall',
                    'importBatch',
                ])
                ->withCount('lectureSessions'))
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
            ->recordActions([
                $this->replacePlaceholderAssignmentsAction(),
                $this->applyAssignmentsToFutureSessionsAction(),
            ])
            ->defaultSort('weekday')
            ->defaultPaginationPageOption(25)
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

        $importBatchId = data_get($this->getTableFilterState('import_batch_id'), 'value');

        return $query
            ->when($this->resolvedAcademicTermId, fn (Builder $query): Builder => $query->where('academic_term_id', $this->resolvedAcademicTermId))
            ->when(filled($importBatchId), fn (Builder $query): Builder => $query->where('import_batch_id', $importBatchId))
            ->count();
    }

    /** @return array<string, int|string|null> */
    public function currentReportFilters(): array
    {
        return app(\App\Services\WeeklyScheduleReportService::class)->normalizeFilters([
            'academic_term_id' => $this->resolvedAcademicTermId,
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
        return [
            Action::make('import_weekly_schedule')
                ->label('استيراد البرنامج الأسبوعي')
                ->icon(Heroicon::ArrowUpTray)
                ->color('primary')
                ->visible(fn (): bool => ManaraScheduleImport::canAccess())
                ->url(fn (): string => ManaraScheduleImport::getUrl()),
        ];
    }

    private function replacePlaceholderAssignmentsAction(): Action
    {
        return Action::make('replace_placeholder_assignments')
            ->label(__('weekly-schedule.future_assignment.replace_action'))
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->visible(fn (SubjectSectionScheduleSlot $record): bool => $this->canReplacePlaceholder($record))
            ->modalHeading(__('weekly-schedule.future_assignment.replace_heading'))
            ->modalDescription(__('weekly-schedule.future_assignment.replace_description'))
            ->modalSubmitActionLabel(__('weekly-schedule.future_assignment.save_slot_only'))
            ->form(fn (SubjectSectionScheduleSlot $record): array => [
                Select::make('lecturer_id')
                    ->label(__('weekly-schedule.columns.lecturer'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => app(FutureLectureSessionAssignmentService::class)
                        ->searchAvailableLecturers($record, $search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => app(FutureLectureSessionAssignmentService::class)
                        ->lecturerOptionLabel((int) $value))
                    ->visible(fn (): bool => $record->lecturer?->isPlaceholder() === true && $this->canChangeLecturer()),
                Select::make('hall_id')
                    ->label(__('weekly-schedule.columns.hall'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => app(FutureLectureSessionAssignmentService::class)
                        ->searchAvailableHalls($record, $search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => app(FutureLectureSessionAssignmentService::class)
                        ->hallOptionLabel((int) $value))
                    ->visible(fn (): bool => $record->hall?->isPlaceholder() === true && $this->canChangeHall()),
            ])
            ->action(function (SubjectSectionScheduleSlot $record, array $data): void {
                $actor = Filament::auth()->user();
                abort_unless($actor instanceof User, 403);

                app(FutureLectureSessionAssignmentService::class)->replacePlaceholders(
                    $record,
                    filled($data['lecturer_id'] ?? null) ? (int) $data['lecturer_id'] : null,
                    filled($data['hall_id'] ?? null) ? (int) $data['hall_id'] : null,
                    $actor,
                );

                Notification::make()
                    ->title(__('weekly-schedule.future_assignment.slot_updated_title'))
                    ->body(__('weekly-schedule.future_assignment.slot_updated_body'))
                    ->success()
                    ->send();
            });
    }

    private function applyAssignmentsToFutureSessionsAction(): Action
    {
        return Action::make('apply_assignments_to_future_sessions')
            ->label(__('weekly-schedule.future_assignment.apply_action'))
            ->icon('heroicon-o-arrow-path')
            ->color('primary')
            ->visible(fn (SubjectSectionScheduleSlot $record): bool => $record->lecture_sessions_count > 0
                && $this->canApplyFutureAssignments($record))
            ->modalHeading(__('weekly-schedule.future_assignment.preview_heading'))
            ->modalContent(fn (SubjectSectionScheduleSlot $record) => view('filament.components.future-session-assignment-preview', [
                'preview' => app(FutureLectureSessionAssignmentService::class)->preview($record),
            ]))
            ->modalSubmitActionLabel(__('weekly-schedule.future_assignment.apply_action'))
            ->requiresConfirmation()
            ->action(function (SubjectSectionScheduleSlot $record): void {
                $actor = Filament::auth()->user();
                abort_unless($actor instanceof User, 403);
                $result = app(FutureLectureSessionAssignmentService::class)->apply($record, $actor);

                Notification::make()
                    ->title(__('weekly-schedule.future_assignment.applied_title'))
                    ->body(__('weekly-schedule.future_assignment.applied_body', ['count' => $result['updated_sessions_count']]))
                    ->success()
                    ->send();

                if ($result['lecturer_account_missing']) {
                    Notification::make()
                        ->title(__('weekly-schedule.future_assignment.account_warning'))
                        ->warning()
                        ->send();
                }
            });
    }

    private function canReplacePlaceholder(SubjectSectionScheduleSlot $slot): bool
    {
        return ($slot->lecturer?->isPlaceholder() === true && $this->canChangeLecturer())
            || ($slot->hall?->isPlaceholder() === true && $this->canChangeHall());
    }

    private function canApplyFutureAssignments(SubjectSectionScheduleSlot $slot): bool
    {
        return ($slot->lecturer?->isPlaceholder() === false && $this->canChangeLecturer())
            || ($slot->hall?->isPlaceholder() === false && $this->canChangeHall());
    }

    private function canChangeLecturer(): bool
    {
        return Filament::auth()->user()?->can(ScheduleImportRowPolicy::CHANGE_RECONCILED_LECTURER) ?? false;
    }

    private function canChangeHall(): bool
    {
        return Filament::auth()->user()?->can(ScheduleImportRowPolicy::CHANGE_RECONCILED_HALL) ?? false;
    }

    private function contextBatchId(): ?int
    {
        if ($this->resolvedImportBatchId) {
            return $this->resolvedImportBatchId;
        }

        $value = data_get($this->getTableFilterState('import_batch_id'), 'value');

        return filled($value) ? (int) $value : null;
    }

    private function latestBatchIdForTerm(int $termId): ?int
    {
        return ImportBatch::query()
            ->where('import_type', ImportBatch::TYPE_WEEKLY_SCHEDULE)
            ->whereIn('status', [ImportBatch::STATUS_COMPLETED, ImportBatch::STATUS_COMPLETED_WITH_ERRORS])
            ->whereHas('academicTerms', fn (Builder $query): Builder => $query->whereKey($termId))
            ->whereHas('scheduleSlots', fn (Builder $query): Builder => $query->where('academic_term_id', $termId))
            ->latest('completed_at')
            ->latest('id')
            ->value('id');
    }

    private function setBatchTableFilter(): void
    {
        if ($this->resolvedImportBatchId) {
            $this->tableFilters = [
                'import_batch_id' => ['value' => (string) $this->resolvedImportBatchId],
            ];
        }
    }
}
