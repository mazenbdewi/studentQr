<?php

namespace App\Filament\Pages;

use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\Lecturer;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportRow;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Rules\ValidScheduleIdentityValue;
use App\Services\ScheduleImportIssueWorkflow;
use App\Services\ScheduleImportReconciliationService;
use App\Services\ScheduleImportReconciliationSummaryService;
use App\Services\ScheduleImportRowResolutionContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Schema;

class ScheduleImportReconciliationReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'schedule-import-reconciliation/{batch}';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentCheck;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.schedule-import-reconciliation-report';

    public ImportBatch $batchRecord;

    public string $activeTab = 'needs_attention';

    public function mount(string $batch): void
    {
        abort_unless(static::canAccess(), 403);
        $this->batchRecord = ImportBatch::query()
            ->where('uuid', $batch)
            ->where('import_type', ImportBatch::TYPE_WEEKLY_SCHEDULE)
            ->with(['academicTerms', 'sourceImportBatch'])
            ->firstOrFail();
    }

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->can('viewAny', ScheduleImportRow::class);
    }

    public function getTitle(): string
    {
        return __('schedule-import-reconciliation.title');
    }

    public function selectTab(string $tab): void
    {
        abort_unless(array_key_exists($tab, $this->tabLabels()), 404);
        $this->activeTab = $tab;
        $this->resetTable();
    }

    public function tabLabels(): array
    {
        return [
            'needs_attention' => __('schedule-import-reconciliation.tabs.needs_attention'),
            'warnings' => __('schedule-import-reconciliation.tabs.warnings'),
            'excluded' => __('schedule-import-reconciliation.tabs.excluded'),
            'successful' => __('schedule-import-reconciliation.tabs.successful'),
        ];
    }

    public function tabCounts(): array
    {
        return collect(array_keys($this->tabLabels()))
            ->mapWithKeys(fn (string $tab): array => [$tab => $this->queryForTab($tab)->count()])
            ->all();
    }

    public function remediationSummary(): array
    {
        return app(ScheduleImportReconciliationSummaryService::class)->forBatch($this->batchRecord->id);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->queryForTab($this->activeTab))
            ->columns([
                TextColumn::make('source_row_number')->label(__('schedule-import-reconciliation.fields.row'))->sortable(),
                TextColumn::make('source_payload.subject_code')->label(__('schedule-import-reconciliation.fields.subject_code'))->searchable(),
                TextColumn::make('source_payload.subject_name')->label(__('schedule-import-reconciliation.fields.subject_name'))->wrap(),
                TextColumn::make('normalized_payload.section_code')->label(__('schedule-import-reconciliation.fields.normalized_section'))->badge(),
                TextColumn::make('issues.issue_type')
                    ->label(__('schedule-import-reconciliation.fields.issue_type'))
                    ->state(fn (ScheduleImportRow $record): string => $record->issues
                        ->pluck('issue_type')
                        ->unique()
                        ->map(fn (string $issueType): string => ScheduleImportIssue::label($issueType))
                        ->implode('، '))
                    ->wrap(),
                TextColumn::make('required_action')
                    ->label(__('schedule-import-reconciliation.fields.required_action'))
                    ->state(fn (ScheduleImportRow $record): string => app(ScheduleImportIssueWorkflow::class)->requiredActionLabel($record))
                    ->badge()
                    ->wrap(),
                TextColumn::make('issues.reason_ar')
                    ->label(__('schedule-import-reconciliation.fields.reason'))
                    ->state(fn (ScheduleImportRow $record): string => $record->issues->whereIn('resolution_status', [ScheduleImportIssue::STATUS_UNRESOLVED, ScheduleImportIssue::STATUS_RETRY_FAILED])->pluck('reason_ar')->unique()->implode(' | '))
                    ->wrap(),
                TextColumn::make('current_reconciliation_status')
                    ->label(__('schedule-import-reconciliation.fields.status'))
                    ->formatStateUsing(fn (string $state): string => __('schedule-import-reconciliation.statuses.'.$state))
                    ->badge(),
                TextColumn::make('issues.suggested_matches')
                    ->label(__('schedule-import-reconciliation.fields.suggestions'))
                    ->state(fn (ScheduleImportRow $record): string => $record->issues->flatMap(fn (ScheduleImportIssue $issue): array => $issue->suggested_matches ?? [])->map(fn (array $candidate): string => ($candidate['subject']['code'] ?? '').' — '.($candidate['subject']['name'] ?? '').' — '.collect([$candidate['subject']['faculty'] ?? null, $candidate['subject']['department'] ?? null])->filter()->implode(' / '))->filter()->unique()->implode(' | '))
                    ->wrap(),
            ])
            ->recordActions([
                $this->subjectAction(),
                $this->sectionAction(),
                $this->lecturerAction(),
                $this->hallAction(),
                $this->weeklyTimeAction(),
                $this->exclusionAction(),
                $this->ignoreAction(),
                $this->conflictAction(),
                $this->retryAction(),
                $this->detailsAction(),
                $this->historyAction(),
            ])
            ->defaultSort('source_row_number');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('schedule-import-reconciliation.actions.export'))
                ->icon(Heroicon::ArrowDownTray)
                ->visible(fn (): bool => Filament::auth()->user()?->can('export', ScheduleImportRow::class) ?? false)
                ->url(fn (): string => route('admin.schedule-import-reconciliation.export', ['batch' => $this->batchRecord->uuid], false)),
        ];
    }

    private function subjectAction(): Action
    {
        return Action::make('link-subject')
            ->label(__('schedule-import-reconciliation.actions.link_subject'))
            ->visible(fn (ScheduleImportRow $record): bool => $this->canIssue($record, 'resolveSubjectMapping', ScheduleImportIssueWorkflow::SUBJECT_ISSUES))
            ->form(fn (ScheduleImportRow $record): array => [
                Select::make('subject_id')
                    ->label(__('schedule-import-reconciliation.fields.subject'))
                    ->options($this->subjectOptions())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                Select::make('section_id')
                    ->label(__('schedule-import-reconciliation.fields.section'))
                    ->options(fn (Get $get): array => $this->sectionOptions($record, (int) $get('subject_id')))
                    ->searchable()
                    ->preload()
                    ->required(),
                Textarea::make('note')->label(__('schedule-import-reconciliation.fields.note')),
            ])
            ->action(function (ScheduleImportRow $record, array $data): void {
                app(ScheduleImportReconciliationService::class)->mapSubject($record, (int) $data['subject_id'], (int) $data['section_id'], Filament::auth()->user(), $data['note'] ?? null);
                $this->successNotification();
            });
    }

    private function sectionAction(): Action
    {
        return Action::make('link-section')
            ->label(__('schedule-import-reconciliation.actions.link_section'))
            ->visible(fn (ScheduleImportRow $record): bool => $this->canIssue($record, 'resolveSectionMapping', ScheduleImportIssueWorkflow::SECTION_ISSUES))
            ->disabled(fn (ScheduleImportRow $record): bool => $this->dependency($record, 'section') !== null)
            ->tooltip(fn (ScheduleImportRow $record): ?string => $this->dependency($record, 'section'))
            ->form(fn (ScheduleImportRow $record): array => [
                Select::make('section_id')
                    ->label(__('schedule-import-reconciliation.fields.section'))
                    ->options(fn (): array => $this->sectionOptions($record, app(ScheduleImportIssueWorkflow::class)->subjectForRow($record)?->id))
                    ->searchable()
                    ->preload()
                    ->required(),
                Textarea::make('note')->label(__('schedule-import-reconciliation.fields.note')),
            ])
            ->modalDescription(fn (ScheduleImportRow $record): string => collect([
                app(ScheduleImportIssueWorkflow::class)->subjectForRow($record)?->code,
                app(ScheduleImportIssueWorkflow::class)->subjectForRow($record)?->name,
            ])->filter()->implode(' — '))
            ->action(function (ScheduleImportRow $record, array $data): void {
                app(ScheduleImportReconciliationService::class)->mapSection($record, (int) $data['section_id'], Filament::auth()->user(), $data['note'] ?? null);
                $this->successNotification();
            });
    }

    private function lecturerAction(): Action
    {
        return Action::make('assign-lecturer')
            ->label(__('schedule-import-reconciliation.actions.assign_lecturer'))
            ->visible(fn (ScheduleImportRow $record): bool => $this->canIssue($record, 'assignLecturer', ScheduleImportIssueWorkflow::LECTURER_ISSUES))
            ->disabled(fn (ScheduleImportRow $record): bool => $this->dependency($record, 'lecturer') !== null)
            ->tooltip(fn (ScheduleImportRow $record): ?string => $this->dependency($record, 'lecturer'))
            ->form(fn (ScheduleImportRow $record): array => [
                Radio::make('mode')
                    ->label(__('schedule-import-reconciliation.fields.creation_mode'))
                    ->options(function () use ($record): array {
                        $options = ['existing' => __('schedule-import-reconciliation.actions.create_existing')];

                        if ($this->canIssue($record, 'createLecturerIdentity', [ScheduleImportIssue::TYPE_LECTURER_MISSING])) {
                            $options['create'] = __('schedule-import-reconciliation.actions.create_new');
                        }

                        return $options;
                    })
                    ->default('existing')
                    ->live()
                    ->required(),
                Select::make('lecturer_id')
                    ->label(__('schedule-import-reconciliation.fields.existing_lecturer'))
                    ->options($this->lecturerOptions($record))
                    ->visible(fn (Get $get): bool => $get('mode') === 'existing')
                    ->required(fn (Get $get): bool => $get('mode') === 'existing')
                    ->searchable()
                    ->preload(),
                TextInput::make('lecturer_name')
                    ->label(__('schedule-import-reconciliation.fields.new_lecturer_name'))
                    ->visible(fn (Get $get): bool => $get('mode') === 'create')
                    ->required(fn (Get $get): bool => $get('mode') === 'create')
                    ->rules([new ValidScheduleIdentityValue]),
                Checkbox::make('confirm_creation')
                    ->label(__('schedule-import-reconciliation.fields.confirm_creation'))
                    ->visible(fn (Get $get): bool => $get('mode') === 'create')
                    ->accepted(),
                Textarea::make('note')->label(__('schedule-import-reconciliation.fields.note')),
            ])
            ->action(function (ScheduleImportRow $record, array $data): void {
                $service = app(ScheduleImportReconciliationService::class);
                ($data['mode'] ?? 'existing') === 'create'
                    ? $service->createLecturerIdentity($record, (string) $data['lecturer_name'], Filament::auth()->user(), $data['note'] ?? null)
                    : $service->assignLecturer($record, (int) $data['lecturer_id'], Filament::auth()->user(), $data['note'] ?? null);
                $this->successNotification();
            });
    }

    private function hallAction(): Action
    {
        return Action::make('assign-hall')
            ->label(__('schedule-import-reconciliation.actions.assign_hall'))
            ->visible(fn (ScheduleImportRow $record): bool => $this->canIssue($record, 'assignHall', ScheduleImportIssueWorkflow::HALL_ISSUES))
            ->disabled(fn (ScheduleImportRow $record): bool => $this->dependency($record, 'hall') !== null)
            ->tooltip(fn (ScheduleImportRow $record): ?string => $this->dependency($record, 'hall'))
            ->form(fn (ScheduleImportRow $record): array => [
                Radio::make('mode')->label(__('schedule-import-reconciliation.fields.creation_mode'))->options(function () use ($record): array {
                    $options = ['existing' => __('schedule-import-reconciliation.actions.create_existing')];

                    if ($this->canIssue($record, 'createHall', ScheduleImportIssueWorkflow::HALL_ISSUES)) {
                        $options['create'] = __('schedule-import-reconciliation.actions.create_new');
                    }

                    return $options;
                })->default('existing')->live()->required(),
                Select::make('hall_id')->label(__('schedule-import-reconciliation.fields.existing_hall'))
                    ->options(fn (): array => Hall::query()->withoutTrashed()->orderBy('name')->get()->mapWithKeys(fn (Hall $hall): array => [$hall->id => "{$hall->code} — {$hall->name}"])->all())
                    ->visible(fn (Get $get): bool => $get('mode') === 'existing')->required(fn (Get $get): bool => $get('mode') === 'existing')->searchable()->preload(),
                TextInput::make('hall_code')->label(__('schedule-import-reconciliation.fields.new_hall_code'))->visible(fn (Get $get): bool => $get('mode') === 'create')->required(fn (Get $get): bool => $get('mode') === 'create')->rules([new ValidScheduleIdentityValue]),
                TextInput::make('hall_name')->label(__('schedule-import-reconciliation.fields.new_hall_name'))->visible(fn (Get $get): bool => $get('mode') === 'create')->required(fn (Get $get): bool => $get('mode') === 'create')->rules([new ValidScheduleIdentityValue]),
                Checkbox::make('confirm_hall_creation')->label(__('schedule-import-reconciliation.fields.confirm_hall_creation'))->visible(fn (Get $get): bool => $get('mode') === 'create')->accepted(),
                Textarea::make('note')->label(__('schedule-import-reconciliation.fields.note')),
            ])
            ->action(function (ScheduleImportRow $record, array $data): void {
                $service = app(ScheduleImportReconciliationService::class);
                ($data['mode'] ?? 'existing') === 'create'
                    ? $service->createHall($record, (string) $data['hall_code'], (string) $data['hall_name'], Filament::auth()->user(), $data['note'] ?? null)
                    : $service->assignHall($record, (int) $data['hall_id'], Filament::auth()->user(), $data['note'] ?? null);
                $this->successNotification();
            });
    }

    private function weeklyTimeAction(): Action
    {
        return Action::make('assign-weekly-time')
            ->label(__('schedule-import-reconciliation.actions.assign_weekly_time'))
            ->visible(fn (ScheduleImportRow $record): bool => $this->canIssue($record, 'assignWeeklyTime', ScheduleImportIssueWorkflow::TIME_ISSUES))
            ->disabled(fn (ScheduleImportRow $record): bool => $this->dependency($record, 'time') !== null)
            ->tooltip(fn (ScheduleImportRow $record): ?string => $this->dependency($record, 'time'))
            ->form(fn (ScheduleImportRow $record): array => [
                Placeholder::make('effective_subject')
                    ->label(__('schedule-import-reconciliation.fields.subject'))
                    ->content(function () use ($record): string {
                        $subject = app(ScheduleImportIssueWorkflow::class)->subjectForRow($record);

                        return collect([$subject?->code, $subject?->name])->filter()->implode(' — ');
                    }),
                Placeholder::make('effective_section')
                    ->label(__('schedule-import-reconciliation.fields.section'))
                    ->content(function () use ($record): string {
                        $section = app(ScheduleImportRowResolutionContext::class)->effectiveSubjectSection($record);

                        return $section instanceof SubjectSection ? $section->code : '—';
                    }),
                Placeholder::make('effective_academic_term')
                    ->label(__('schedule-import-reconciliation.fields.academic_term'))
                    ->content(function () use ($record): string {
                        $term = $record->academicTerm()->first();

                        return $term->display_name;
                    }),
                Placeholder::make('optional_identity_notice')
                    ->label(__('schedule-import-reconciliation.fields.optional_metadata'))
                    ->content(__('schedule-import-reconciliation.notices.optional_identities'))
                    ->visible(fn (): bool => app(ScheduleImportIssueWorkflow::class)->hasUnresolvedIssue($record, [
                        ...ScheduleImportIssueWorkflow::LECTURER_ISSUES,
                        ...ScheduleImportIssueWorkflow::HALL_ISSUES,
                    ]))
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => 'rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 text-sm font-medium text-warning-800 dark:border-warning-500/40 dark:bg-warning-500/10 dark:text-warning-200',
                    ]),
                Repeater::make('times')
                    ->label(__('schedule-import-reconciliation.fields.time_overrides'))
                    ->schema([
                        Select::make('weekday')->label(__('schedule-import-reconciliation.fields.weekday'))->options(__('weekly-schedule.weekdays'))->required(),
                        TimePicker::make('start_time')->label(__('schedule-import-reconciliation.fields.start_time'))->seconds(false)->required(),
                        TimePicker::make('end_time')->label(__('schedule-import-reconciliation.fields.end_time'))->seconds(false)->required(),
                        Select::make('lecturer_id')->label(__('schedule-import-reconciliation.fields.lecturer'))->helperText(__('schedule-import-reconciliation.fields.optional'))->default(fn (): ?int => app(ScheduleImportRowResolutionContext::class)->effectiveLecturerId($record))->options(fn (): array => Lecturer::query()->orderBy('name')->pluck('name', 'id')->all())->searchable()->preload(),
                        Select::make('hall_id')->label(__('schedule-import-reconciliation.fields.hall'))->helperText(__('schedule-import-reconciliation.fields.optional'))->default(fn (): ?int => app(ScheduleImportRowResolutionContext::class)->effectiveHallId($record))->options(fn (): array => Hall::query()->withoutTrashed()->orderBy('name')->pluck('name', 'id')->all())->searchable()->preload(),
                        TextInput::make('section_capacity')->label(__('schedule-import-reconciliation.fields.section_capacity'))->numeric()->minValue(0),
                        TextInput::make('expected_student_count')->label(__('schedule-import-reconciliation.fields.expected_students'))->numeric()->minValue(0)->default($record->source_payload['expected_student_count'] ?? null),
                    ])
                    ->columns(2)
                    ->defaultItems(1)
                    ->minItems(1)
                    ->addActionLabel(__('schedule-import-reconciliation.actions.assign_weekly_time'))
                    ->required(),
                Textarea::make('note')->label(__('schedule-import-reconciliation.fields.note')),
            ])
            ->action(function (ScheduleImportRow $record, array $data): void {
                app(ScheduleImportReconciliationService::class)->addWeeklyTimes($record, $data['times'] ?? [], Filament::auth()->user(), $data['note'] ?? null);
                $this->successNotification();
            });
    }

    private function exclusionAction(): Action
    {
        return Action::make('exclude-from-batch-schedule')
            ->label(__('schedule-import-reconciliation.actions.exclude_from_batch_schedule'))
            ->visible(fn (ScheduleImportRow $record): bool => $this->canIssue($record, 'excludeFromBatchSchedule', [ScheduleImportIssue::TYPE_NO_WEEKLY_TIME]))
            ->disabled(fn (ScheduleImportRow $record): bool => $this->dependency($record, 'exclude') !== null)
            ->tooltip(fn (ScheduleImportRow $record): ?string => $this->dependency($record, 'exclude'))
            ->requiresConfirmation()
            ->modalHeading(__('schedule-import-reconciliation.exclusion.title'))
            ->modalDescription(__('schedule-import-reconciliation.exclusion.explanation'))
            ->form([
                Textarea::make('exclusion_note')
                    ->label(__('schedule-import-reconciliation.fields.exclusion_reason'))
                    ->helperText(__('schedule-import-reconciliation.exclusion.examples'))
                    ->required()
                    ->minLength(5)
                    ->maxLength(500),
            ])
            ->action(function (ScheduleImportRow $record, array $data): void {
                $issue = app(ScheduleImportIssueWorkflow::class)->unresolvedIssues($record, [ScheduleImportIssue::TYPE_NO_WEEKLY_TIME])->firstOrFail();
                app(ScheduleImportReconciliationService::class)->excludeFromBatchSchedule($issue, Filament::auth()->user(), (string) $data['exclusion_note']);
                $this->successNotification();
            });
    }

    private function ignoreAction(): Action
    {
        return Action::make('ignore')
            ->label(__('schedule-import-reconciliation.actions.ignore'))
            ->visible(fn (ScheduleImportRow $record): bool => $this->canIssue($record, 'ignore', ScheduleImportIssueWorkflow::ZERO_STUDENT_ISSUES))
            ->requiresConfirmation()
            ->form([Textarea::make('note')->label(__('schedule-import-reconciliation.fields.note'))->required()])
            ->action(function (ScheduleImportRow $record, array $data): void {
                $issue = app(ScheduleImportIssueWorkflow::class)->unresolvedIssues($record, ScheduleImportIssueWorkflow::ZERO_STUDENT_ISSUES)->firstOrFail();
                app(ScheduleImportReconciliationService::class)->ignore($issue, Filament::auth()->user(), $data['note']);
                $this->successNotification();
            });
    }

    private function conflictAction(): Action
    {
        return Action::make('resolve-conflict')
            ->label(__('schedule-import-reconciliation.actions.resolve_conflict'))
            ->visible(fn (ScheduleImportRow $record): bool => $this->canIssue($record, 'resolveConflict', ScheduleImportIssueWorkflow::CONFLICT_ISSUES))
            ->form([
                Radio::make('decision')->label(__('schedule-import-reconciliation.fields.conflict_decision'))->options(__('schedule-import-reconciliation.conflict_decisions'))->required(),
                Textarea::make('note')->label(__('schedule-import-reconciliation.fields.note')),
            ])
            ->modalContent(fn (ScheduleImportRow $record) => view('filament.components.schedule-import-row-details', [
                'row' => $record->load(['issues', 'resolvedSubject', 'resolvedSubjectSection', 'resolvedLecturer', 'resolvedHall', 'timeOverrides']),
                'conflictingRows' => $this->conflictingRows($record),
                'relatedSlots' => $this->relatedSlots($record),
            ]))
            ->action(function (ScheduleImportRow $record, array $data): void {
                app(ScheduleImportReconciliationService::class)->resolveConflict($record, $data['decision'], Filament::auth()->user(), $data['note'] ?? null);
                $this->successNotification();
            });
    }

    private function retryAction(): Action
    {
        return Action::make('retry-row')
            ->label(__('schedule-import-reconciliation.actions.retry_row'))
            ->visible(fn (ScheduleImportRow $record): bool => Filament::auth()->user()?->can('retry', $record) ?? false)
            ->disabled(fn (ScheduleImportRow $record): bool => $this->dependency($record, 'retry') !== null)
            ->tooltip(fn (ScheduleImportRow $record): ?string => $this->dependency($record, 'retry'))
            ->requiresConfirmation()
            ->form([Textarea::make('note')->label(__('schedule-import-reconciliation.fields.note'))])
            ->action(function (ScheduleImportRow $record, array $data): void {
                $result = app(ScheduleImportReconciliationService::class)->retryRow($record, Filament::auth()->user(), $data['note'] ?? null);
                ($result['conflicts'] ?? []) === [] ? $this->successNotification() : Notification::make()->danger()->title(__('schedule-import-reconciliation.required_actions.conflict'))->send();
            });
    }

    private function detailsAction(): Action
    {
        return Action::make('details')
            ->label(__('schedule-import-reconciliation.actions.details'))
            ->icon(Heroicon::Eye)
            ->modalContent(fn (ScheduleImportRow $record) => view('filament.components.schedule-import-row-details', [
                'row' => $record->load([
                    'issues',
                    'resolvedSubject',
                    'resolvedSubjectSection',
                    'resolvedLecturer',
                    'resolvedHall',
                    'timeOverrides',
                    ...(Schema::hasColumn('schedule_import_rows', 'excluded_from_weekly_schedule_by') ? ['excludedFromWeeklyScheduleBy'] : []),
                ]),
                'relatedSlots' => $this->relatedSlots($record),
            ]))
            ->modalSubmitAction(false);
    }

    private function historyAction(): Action
    {
        return Action::make('history')
            ->label(__('schedule-import-reconciliation.actions.history'))
            ->icon(Heroicon::Clock)
            ->modalContent(function (ScheduleImportRow $record) {
                $issues = $record->issues()->with('actions.actor')->get();
                $actions = $issues
                    ->flatMap(function (ScheduleImportIssue $issue) {
                        return $issue->actions->map(function ($action) use ($issue) {
                            $action->setRelation('issue', $issue);

                            return $action;
                        });
                    })
                    ->sortByDesc('performed_at');

                return view('filament.components.schedule-import-action-history', ['actions' => $actions]);
            })
            ->modalSubmitAction(false);
    }

    private function queryForTab(string $tab): Builder
    {
        $query = ScheduleImportRow::query()->with(['academicTerm', 'issues', 'resolvedSubject', 'resolvedSubjectSection'])->where('import_batch_id', $this->batchRecord->id);
        $blocking = fn (Builder $query): Builder => $query->where('severity', ScheduleImportIssue::SEVERITY_ERROR)->whereIn('resolution_status', [ScheduleImportIssue::STATUS_UNRESOLVED, ScheduleImportIssue::STATUS_RETRY_FAILED]);
        $warnings = fn (Builder $query): Builder => $query->where('severity', ScheduleImportIssue::SEVERITY_WARNING)->whereIn('resolution_status', [ScheduleImportIssue::STATUS_UNRESOLVED, ScheduleImportIssue::STATUS_RETRY_FAILED]);

        return match ($tab) {
            'needs_attention' => $query
                ->whereNotIn('current_reconciliation_status', [ScheduleImportRow::STATUS_IGNORED, ScheduleImportRow::STATUS_INTENTIONALLY_UNSCHEDULED, ScheduleImportRow::STATUS_EXCLUDED_FROM_BATCH_SCHEDULE])
                ->where(function (Builder $needsAttention) use ($blocking, $warnings): void {
                    $needsAttention
                        ->whereHas('issues', $blocking)
                        ->orWhere(function (Builder $awaitingRetry) use ($warnings): void {
                            $awaitingRetry
                                ->where('current_reconciliation_status', ScheduleImportRow::STATUS_UNRESOLVED)
                                ->whereDoesntHave('issues', $warnings);
                        });
                }),
            'warnings' => $query->whereNotIn('current_reconciliation_status', [ScheduleImportRow::STATUS_IGNORED, ScheduleImportRow::STATUS_INTENTIONALLY_UNSCHEDULED, ScheduleImportRow::STATUS_EXCLUDED_FROM_BATCH_SCHEDULE])->whereDoesntHave('issues', $blocking)->whereHas('issues', $warnings),
            'excluded' => $query->whereIn('current_reconciliation_status', [ScheduleImportRow::STATUS_IGNORED, ScheduleImportRow::STATUS_INTENTIONALLY_UNSCHEDULED, ScheduleImportRow::STATUS_EXCLUDED_FROM_BATCH_SCHEDULE]),
            'successful' => $query
                ->where('current_reconciliation_status', ScheduleImportRow::STATUS_RESOLVED)
                ->whereDoesntHave('issues', fn (Builder $issueQuery): Builder => $issueQuery->whereIn('resolution_status', [ScheduleImportIssue::STATUS_UNRESOLVED, ScheduleImportIssue::STATUS_RETRY_FAILED]))
                ->where(function (Builder $slotEvidence): void {
                    $slotEvidence
                        ->whereIn('original_import_status', [ScheduleImportRow::ORIGINAL_IMPORTED, ScheduleImportRow::ORIGINAL_PARTIALLY_IMPORTED, ScheduleImportRow::ORIGINAL_WARNING_ONLY])
                        ->orWhereJsonLength('import_result->slot_ids', '>', 0)
                        ->orWhereJsonLength('import_result->reconciliation_slot_ids', '>', 0);
                }),
            default => $query->whereRaw('1 = 0'),
        };
    }

    private function subjectOptions(): array
    {
        return Subject::query()->withoutTrashed()->with('department.faculty')->orderBy('code')->get()
            ->mapWithKeys(fn (Subject $subject): array => [$subject->id => collect([
                "{$subject->code} — {$subject->name}",
                $subject->department?->faculty?->name,
                $subject->department?->name,
            ])->filter()->implode(' — ')])->all();
    }

    private function sectionOptions(ScheduleImportRow $row, ?int $subjectId): array
    {
        if (! $subjectId) {
            return [];
        }

        $sourceType = $row->normalized_payload['section_type'] ?? null;
        $type = match ($sourceType) {
            'T' => Subject::TYPE_THEORETICAL,
            'P' => Subject::TYPE_PRACTICAL,
            default => null,
        };

        return SubjectSection::query()
            ->where('academic_term_id', $row->academic_term_id)
            ->where('subject_id', $subjectId)
            ->when($type, fn (Builder $query, string $value): Builder => $query->where('section_type', $value))
            ->orderBy('code')
            ->pluck('code', 'id')
            ->all();
    }

    private function lecturerOptions(ScheduleImportRow $row): array
    {
        $query = Lecturer::query()->orderBy('name');

        if ($this->hasIssue($row, [ScheduleImportIssue::TYPE_LECTURER_AMBIGUOUS])) {
            $key = (string) ($row->normalized_payload['teacher_name_key'] ?? '');
            $ids = Lecturer::query()->get()->filter(fn (Lecturer $lecturer): bool => app(\App\Support\WeeklyScheduleRowNormalizer::class)->normalizeKey($lecturer->canonical_name ?: $lecturer->name) === $key)->pluck('id');
            $query->whereIn('id', $ids);
        }

        return $query->get()->mapWithKeys(fn (Lecturer $lecturer): array => [$lecturer->id => collect([$lecturer->name, $lecturer->lecturer_id, $lecturer->email])->filter()->implode(' — ')])->all();
    }

    private function conflictingRows(ScheduleImportRow $row)
    {
        $normalized = $row->normalized_payload ?? [];

        return ScheduleImportRow::query()
            ->where('import_batch_id', $row->import_batch_id)
            ->where('id', '!=', $row->id)
            ->get()
            ->filter(function (ScheduleImportRow $candidate) use ($normalized): bool {
                $other = $candidate->normalized_payload ?? [];

                return ($other['subject_code_key'] ?? null) === ($normalized['subject_code_key'] ?? null)
                    && ($other['section_code'] ?? null) === ($normalized['section_code'] ?? null)
                    && collect($other['weekday_values'] ?? [])->intersect($normalized['weekday_values'] ?? [])->isNotEmpty();
            })
            ->values();
    }

    /** @return EloquentCollection<int, SubjectSectionScheduleSlot> */
    private function relatedSlots(ScheduleImportRow $row): EloquentCollection
    {
        return SubjectSectionScheduleSlot::query()
            ->with(['subject', 'subjectSection', 'lecturer', 'hall'])
            ->whereIn('id', $row->relatedScheduleSlotIds())
            ->where('academic_term_id', $row->academic_term_id)
            ->orderBy('id')
            ->get();
    }

    private function hasIssue(ScheduleImportRow $row, array $types): bool
    {
        return app(ScheduleImportIssueWorkflow::class)->hasUnresolvedIssue($row, $types);
    }

    private function canIssue(ScheduleImportRow $row, string $ability, array $types): bool
    {
        $issue = app(ScheduleImportIssueWorkflow::class)->unresolvedIssues($row, $types)->first();

        return $issue !== null && (Filament::auth()->user()?->can($ability, $issue) ?? false);
    }

    private function dependency(ScheduleImportRow $row, string $action): ?string
    {
        return app(ScheduleImportIssueWorkflow::class)->dependencyMessage($row, $action);
    }

    private function successNotification(): void
    {
        Notification::make()->title(__('schedule-import-reconciliation.action_completed'))->success()->send();
    }
}
