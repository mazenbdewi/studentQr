<?php

namespace App\Filament\Pages;

use App\Models\ImportBatch;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportRow;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Services\ScheduleImportReconciliationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->queryForTab($this->activeTab))
            ->columns([
                TextColumn::make('source_row_number')->label(__('schedule-import-reconciliation.fields.row'))->sortable(),
                TextColumn::make('source_payload.subject_code')->label(__('schedule-import-reconciliation.fields.subject_code'))->searchable(),
                TextColumn::make('source_payload.subject_name')->label(__('schedule-import-reconciliation.fields.subject_name'))->wrap(),
                TextColumn::make('source_payload.section_type')->label(__('schedule-import-reconciliation.fields.section_type')),
                TextColumn::make('source_payload.section_number')->label(__('schedule-import-reconciliation.fields.section_number')),
                TextColumn::make('normalized_payload.section_code')->label(__('schedule-import-reconciliation.fields.normalized_section'))->badge(),
                TextColumn::make('source_payload.expected_student_count')->label(__('schedule-import-reconciliation.fields.expected_students')),
                TextColumn::make('source_payload.teacher_name')->label(__('schedule-import-reconciliation.fields.lecturer'))->wrap(),
                TextColumn::make('source_payload.hall_name')->label(__('schedule-import-reconciliation.fields.hall')),
                TextColumn::make('source_payload.weekday_values')
                    ->label(__('schedule-import-reconciliation.fields.weekdays'))
                    ->formatStateUsing(fn (mixed $state): string => collect($state ?: [])->filter()->map(fn ($value, $day): string => "{$day}: {$value}")->implode(' | '))
                    ->wrap(),
                TextColumn::make('academicTerm.display_name')->label(__('schedule-import-reconciliation.fields.academic_term'))->badge(),
                TextColumn::make('issues.issue_type')
                    ->label(__('schedule-import-reconciliation.fields.issue_type'))
                    ->state(fn (ScheduleImportRow $record): string => $record->issues->pluck('issue_type')->unique()->implode('، '))
                    ->wrap(),
                TextColumn::make('issues.reason_ar')
                    ->label(__('schedule-import-reconciliation.fields.reason'))
                    ->state(fn (ScheduleImportRow $record): string => $record->issues->pluck('reason_ar')->unique()->implode(' | '))
                    ->wrap(),
                TextColumn::make('current_reconciliation_status')->label(__('schedule-import-reconciliation.fields.status'))->badge(),
                TextColumn::make('issues.suggested_matches')
                    ->label(__('schedule-import-reconciliation.fields.suggestions'))
                    ->state(fn (ScheduleImportRow $record): string => $record->issues->flatMap(fn (ScheduleImportIssue $issue): array => $issue->suggested_matches ?? [])->map(fn (array $candidate): string => ($candidate['subject']['code'] ?? '').' — '.($candidate['subject']['name'] ?? ''))->filter()->unique()->implode(' | '))
                    ->wrap(),
            ])
            ->recordActions([
                Action::make('link')
                    ->label(__('schedule-import-reconciliation.actions.link'))
                    ->visible(fn (): bool => Filament::auth()->user()?->can('resolve schedule-import issues') ?? false)
                    ->requiresConfirmation()
                    ->form([
                        Select::make('issue_id')->label(__('schedule-import-reconciliation.fields.issue_type'))->options(fn (ScheduleImportRow $record): array => $this->issueOptions($record, catalogOnly: true))->required(),
                        Select::make('subject_id')->label(__('schedule-import-reconciliation.fields.subject_name'))->options(fn (): array => Subject::query()->withoutTrashed()->orderBy('code')->get()->mapWithKeys(fn (Subject $subject): array => [$subject->id => "{$subject->code} — {$subject->name}"])->all())->searchable()->live()->required(),
                        Select::make('section_id')->label(__('schedule-import-reconciliation.fields.normalized_section'))->options(fn (Get $get): array => SubjectSection::query()->where('academic_term_id', $this->batchRecord->academicTerms->sole()->id)->where('subject_id', $get('subject_id'))->pluck('code', 'id')->all())->searchable()->required(),
                        Textarea::make('note')->label(__('schedule-import-reconciliation.fields.note')),
                    ])
                    ->action(function (ScheduleImportRow $record, array $data): void {
                        $issue = $this->issueForRow($record, (int) $data['issue_id']);
                        app(ScheduleImportReconciliationService::class)->link($issue, (int) $data['subject_id'], (int) $data['section_id'], Filament::auth()->user(), $data['note'] ?? null);
                        $this->successNotification();
                    }),
                Action::make('ignore')
                    ->label(__('schedule-import-reconciliation.actions.ignore'))
                    ->visible(fn (): bool => Filament::auth()->user()?->can('ignore schedule-import issues') ?? false)
                    ->requiresConfirmation()
                    ->form([
                        Select::make('issue_id')->options(fn (ScheduleImportRow $record): array => $this->issueOptions($record))->required(),
                        Textarea::make('note')->label(__('schedule-import-reconciliation.fields.note'))->required(),
                    ])
                    ->action(function (ScheduleImportRow $record, array $data): void {
                        app(ScheduleImportReconciliationService::class)->ignore($this->issueForRow($record, (int) $data['issue_id']), Filament::auth()->user(), $data['note']);
                        $this->successNotification();
                    }),
                Action::make('acknowledge')
                    ->label(__('schedule-import-reconciliation.actions.acknowledge'))
                    ->visible(fn (): bool => Filament::auth()->user()?->can('resolve schedule-import issues') ?? false)
                    ->form([
                        Select::make('issue_id')->options(fn (ScheduleImportRow $record): array => $this->issueOptions($record, warningsOnly: true))->required(),
                        Textarea::make('note')->label(__('schedule-import-reconciliation.fields.note')),
                    ])
                    ->action(function (ScheduleImportRow $record, array $data): void {
                        app(ScheduleImportReconciliationService::class)->acknowledge($this->issueForRow($record, (int) $data['issue_id']), Filament::auth()->user(), $data['note'] ?? null);
                        $this->successNotification();
                    }),
                Action::make('unscheduled')
                    ->label(__('schedule-import-reconciliation.actions.unscheduled'))
                    ->visible(fn (ScheduleImportRow $record): bool => $record->issues->contains('issue_type', ScheduleImportIssue::TYPE_NO_WEEKLY_TIME) && (Filament::auth()->user()?->can('resolve schedule-import issues') ?? false))
                    ->requiresConfirmation()
                    ->form([Textarea::make('note')->label(__('schedule-import-reconciliation.fields.note'))->required()])
                    ->action(function (ScheduleImportRow $record, array $data): void {
                        $issue = $record->issues->firstWhere('issue_type', ScheduleImportIssue::TYPE_NO_WEEKLY_TIME);
                        app(ScheduleImportReconciliationService::class)->intentionallyUnscheduled($issue, Filament::auth()->user(), $data['note']);
                        $this->successNotification();
                    }),
                Action::make('retry')
                    ->label(__('schedule-import-reconciliation.actions.retry'))
                    ->visible(fn (): bool => Filament::auth()->user()?->can('retry schedule-import rows') ?? false)
                    ->requiresConfirmation()
                    ->form([
                        Select::make('issue_id')->options(fn (ScheduleImportRow $record): array => $this->issueOptions($record, mappedOnly: true))->required(),
                        Textarea::make('note')->label(__('schedule-import-reconciliation.fields.note')),
                    ])
                    ->action(function (ScheduleImportRow $record, array $data): void {
                        $result = app(ScheduleImportReconciliationService::class)->retry($this->issueForRow($record, (int) $data['issue_id']), Filament::auth()->user(), $data['note'] ?? null);
                        ($result->resolution_status === ScheduleImportIssue::STATUS_RESOLVED ? Notification::make()->success() : Notification::make()->danger())
                            ->title(__('schedule-import-reconciliation.action_completed'))->send();
                    }),
            ])
            ->defaultSort('source_row_number');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('schedule-import-reconciliation.actions.export'))
                ->icon(Heroicon::ArrowDownTray)
                ->visible(fn (): bool => Filament::auth()->user()?->can('export schedule-import reconciliation') ?? false)
                ->url(fn (): string => route('admin.schedule-import-reconciliation.export', ['batch' => $this->batchRecord->uuid], false)),
        ];
    }

    private function queryForTab(string $tab): Builder
    {
        $query = ScheduleImportRow::query()->with(['academicTerm', 'issues'])->where('import_batch_id', $this->batchRecord->id);
        $blocking = fn (Builder $query): Builder => $query->where('severity', ScheduleImportIssue::SEVERITY_ERROR)->whereIn('resolution_status', [ScheduleImportIssue::STATUS_UNRESOLVED, ScheduleImportIssue::STATUS_RETRY_FAILED]);
        $warnings = fn (Builder $query): Builder => $query->where('severity', ScheduleImportIssue::SEVERITY_WARNING)->where('resolution_status', ScheduleImportIssue::STATUS_UNRESOLVED);

        return match ($tab) {
            'needs_attention' => $query
                ->whereNotIn('current_reconciliation_status', [ScheduleImportRow::STATUS_IGNORED, ScheduleImportRow::STATUS_INTENTIONALLY_UNSCHEDULED])
                ->where(function (Builder $query) use ($blocking): void {
                    $query->whereHas('issues', $blocking)
                        ->orWhere(function (Builder $mapped): void {
                            $mapped->where('current_reconciliation_status', ScheduleImportRow::STATUS_UNRESOLVED)
                                ->whereHas('issues', fn (Builder $issueQuery): Builder => $issueQuery
                                    ->where('resolution_status', ScheduleImportIssue::STATUS_RESOLVED)
                                    ->whereNotNull('resolved_subject_section_id'));
                        });
                }),
            'warnings' => $query
                ->whereNotIn('current_reconciliation_status', [ScheduleImportRow::STATUS_IGNORED, ScheduleImportRow::STATUS_INTENTIONALLY_UNSCHEDULED])
                ->whereDoesntHave('issues', $blocking)
                ->whereHas('issues', $warnings),
            'excluded' => $query->whereIn('current_reconciliation_status', [ScheduleImportRow::STATUS_IGNORED, ScheduleImportRow::STATUS_INTENTIONALLY_UNSCHEDULED]),
            'successful' => $query
                ->where('current_reconciliation_status', ScheduleImportRow::STATUS_RESOLVED)
                ->whereNotIn('current_reconciliation_status', [ScheduleImportRow::STATUS_IGNORED, ScheduleImportRow::STATUS_INTENTIONALLY_UNSCHEDULED])
                ->where(function (Builder $slotEvidence): void {
                    $slotEvidence
                        ->whereIn('original_import_status', [ScheduleImportRow::ORIGINAL_IMPORTED, ScheduleImportRow::ORIGINAL_PARTIALLY_IMPORTED, ScheduleImportRow::ORIGINAL_WARNING_ONLY])
                        ->orWhereJsonLength('import_result->reconciliation_slot_ids', '>', 0);
                })
                ->whereDoesntHave('issues', fn (Builder $issueQuery): Builder => $issueQuery->where('resolution_status', ScheduleImportIssue::STATUS_UNRESOLVED)),
            default => $query->whereRaw('1 = 0'),
        };
    }

    private function issueOptions(ScheduleImportRow $row, bool $catalogOnly = false, bool $warningsOnly = false, bool $mappedOnly = false): array
    {
        return $row->issues
            ->when($catalogOnly, fn ($issues) => $issues->whereIn('issue_type', [ScheduleImportIssue::TYPE_SUBJECT_NOT_FOUND, ScheduleImportIssue::TYPE_SUBJECT_NOT_UNIQUE, ScheduleImportIssue::TYPE_NON_AUTHORITATIVE_SUBJECT_CODE, ScheduleImportIssue::TYPE_SECTION_NOT_FOUND, ScheduleImportIssue::TYPE_ZERO_STUDENT_SUBJECT_MISSING, ScheduleImportIssue::TYPE_ZERO_STUDENT_SECTION_MISSING]))
            ->when($warningsOnly, fn ($issues) => $issues->where('severity', ScheduleImportIssue::SEVERITY_WARNING))
            ->when($mappedOnly, fn ($issues) => $issues->whereNotNull('resolved_subject_section_id'))
            ->mapWithKeys(fn (ScheduleImportIssue $issue): array => [$issue->id => $issue->reason_ar])
            ->all();
    }

    private function issueForRow(ScheduleImportRow $row, int $issueId): ScheduleImportIssue
    {
        return $row->issues()->findOrFail($issueId);
    }

    private function successNotification(): void
    {
        Notification::make()->title(__('schedule-import-reconciliation.action_completed'))->success()->send();
    }
}
