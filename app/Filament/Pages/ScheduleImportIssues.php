<?php

namespace App\Filament\Pages;

use App\Exceptions\ScheduleAssignmentConflictException;
use App\Exports\WeeklyScheduleIssuesExport;
use App\Models\AcademicTerm;
use App\Models\ImportBatch;
use App\Models\User;
use App\Services\BlockedWeeklySlotReconciliationService;
use App\Services\WeeklyScheduleIssueResult;
use App\Services\WeeklyScheduleIssueService;
use App\Support\AcademicTermContext;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ScheduleImportIssues extends Page
{
    protected static ?string $slug = 'schedule-import-issues';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ExclamationTriangle;

    protected string $view = 'filament.pages.schedule-import-issues';

    public ?int $academicTermId = null;

    public ?int $importBatchId = null;

    public ?int $selectedSlotId = null;

    public ?int $selectedLecturerId = null;

    public ?int $selectedHallId = null;

    public ?string $resolutionType = null;

    public ?string $statusFilter = 'needs_attention';

    public ?string $reasonFilter = null;

    public ?string $facultyFilter = null;

    public ?string $departmentFilter = null;

    public ?string $subjectFilter = null;

    public ?string $sectionFilter = null;

    public ?string $lecturerFilter = null;

    public ?string $hallFilter = null;

    public ?string $weekdayFilter = null;

    public ?string $exclusionReason = null;

    public string $lecturerSearch = '';

    public string $hallSearch = '';

    /** @var array{title: string, message: string, hint: string, conflicts: array<int, array<string, string>>}|null */
    public ?array $assignmentConflict = null;

    /** The page number is deliberately the only issue-list state sent to Livewire. */
    public int $page = 1;

    /** Bumps after a write so the next render obtains a fresh server-side page. */
    public int $issuesVersion = 0;

    public ?string $selectedStartTime = null;

    public ?string $selectedEndTime = null;

    /** @var array<int, array<string, mixed>> */
    public array $selectedConflicts = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && ($user->isSuperAdmin() || $user->can(\App\Policies\ScheduleImportRowPolicy::VIEW));
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $term = app(AcademicTermContext::class)->currentOrNull();
        abort_unless($term instanceof AcademicTerm, 404);

        $requestedTerm = request()->integer('term');
        abort_unless($requestedTerm === 0 || $requestedTerm === $term->id, 404);
        $this->academicTermId = $term->id;
        $requestedBatch = request()->integer('batch');
        $this->importBatchId = $requestedBatch > 0 && ImportBatch::query()
            ->whereKey($requestedBatch)
            ->whereHas('academicTerms', fn ($query) => $query->whereKey($term->id))
            ->exists() ? $requestedBatch : null;
    }

    public function getTitle(): string
    {
        return 'معالجة مشكلات الجدول الأسبوعي';
    }

    public function issueResult(): WeeklyScheduleIssueResult
    {
        $term = AcademicTerm::query()->findOrFail($this->academicTermId);

        return app(WeeklyScheduleIssueService::class)->result($term, $this->importBatchId);
    }

    /** @return array<string, mixed> */
    public function issues(): array
    {
        return $this->issueResult()->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    public function filteredRows(): array
    {
        return collect($this->issueResult()->issues)
            ->when($this->statusFilter && $this->statusFilter !== 'all', fn ($rows) => $rows->where('status', $this->statusFilter))
            ->when($this->reasonFilter, fn ($rows) => $rows->filter(fn (array $row): bool => in_array($this->reasonFilter, $row['reasons'], true)))
            ->when($this->facultyFilter, fn ($rows) => $rows->where('faculty', $this->facultyFilter))
            ->when($this->departmentFilter, fn ($rows) => $rows->where('department', $this->departmentFilter))
            ->when($this->subjectFilter, fn ($rows) => $rows->where('subject', $this->subjectFilter))
            ->when($this->sectionFilter, fn ($rows) => $rows->where('section', $this->sectionFilter))
            ->when($this->lecturerFilter, fn ($rows) => $rows->where('lecturer', $this->lecturerFilter))
            ->when($this->hallFilter, fn ($rows) => $rows->where('hall', $this->hallFilter))
            ->when($this->weekdayFilter, fn ($rows) => $rows->where('weekday_value', (int) $this->weekdayFilter))
            ->values()
            ->all();
    }

    /**
     * Builds exactly one server-side result for the current render.  Its rows
     * are never a public Livewire property, so they cannot inflate a snapshot.
     *
     * @return array{summary: array<string, mixed>, rows: array<int, array<string, mixed>>, filters: array<string, array<int|string, mixed>>, pagination: array<string, int>}
     */
    public function issuePage(): array
    {
        $term = AcademicTerm::query()->findOrFail($this->academicTermId);

        return app(WeeklyScheduleIssueService::class)->page(
            $term,
            $this->importBatchId,
            $this->filters(),
            $this->page,
            50,
        );
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['statusFilter', 'reasonFilter', 'facultyFilter', 'departmentFilter', 'subjectFilter', 'sectionFilter', 'lecturerFilter', 'hallFilter', 'weekdayFilter'], true)) {
            $this->page = 1;
        }
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    /** @return array<string, array<int|string, mixed>> */
    public function filterOptions(): array
    {
        $rows = collect($this->issueResult()->issues);
        $values = fn (string $key): array => $rows->pluck($key)->filter(fn ($value) => filled($value) && $value !== '—')->unique()->sort()->values()->all();

        return [
            'reasons' => collect($this->issueResult()->issueCountsByKey)->keys()->mapWithKeys(fn (string $reason): array => [$reason => $this->reasonLabel($reason)])->all(),
            'faculties' => $values('faculty'), 'departments' => $values('department'), 'subjects' => $values('subject'),
            'sections' => $values('section'), 'lecturers' => $values('lecturer'), 'halls' => $values('hall'),
            'weekdays' => $rows->pluck('weekday_value')->unique()->sort()->mapWithKeys(fn (int $day): array => [(string) $day => __('weekly-schedule.weekdays')[$day] ?? (string) $day])->all(),
        ];
    }

    public function reasonLabel(string $reason): string
    {
        $key = 'lecture-session.lecture_generation.reasons.'.$reason;
        $label = __($key);

        return $label === $key ? __('lecture-session.lecture_generation.reasons.unknown') : $label;
    }

    public function openResolution(int $slotId, string $type): void
    {
        abort_unless(in_array($type, ['lecturer', 'hall'], true), 404);
        $this->authorizeAction($type === 'lecturer'
            ? \App\Policies\ScheduleImportRowPolicy::CHANGE_RECONCILED_LECTURER
            : \App\Policies\ScheduleImportRowPolicy::CHANGE_RECONCILED_HALL);
        $this->selectedSlotId = $slotId;
        $this->resolutionType = $type;
        $this->selectedLecturerId = null;
        $this->selectedHallId = null;
        $this->lecturerSearch = '';
        $this->hallSearch = '';
        $this->clearAssignmentConflict();
    }

    public function closeResolution(): void
    {
        $this->selectedSlotId = null;
        $this->resolutionType = null;
        $this->selectedStartTime = null;
        $this->selectedEndTime = null;
        $this->exclusionReason = null;
        $this->lecturerSearch = '';
        $this->hallSearch = '';
        $this->clearAssignmentConflict();
    }

    /** @return array<int, array<string, mixed>> */
    public function lecturerOptions(): array
    {
        return app(BlockedWeeklySlotReconciliationService::class)->lecturerOptions($this->lecturerSearch);
    }

    /** @return array<int, array<string, mixed>> */
    public function hallOptions(): array
    {
        return app(BlockedWeeklySlotReconciliationService::class)->hallOptions($this->hallSearch);
    }

    public function selectResolutionOption(string $type, int $id): void
    {
        abort_unless(in_array($type, ['lecturer', 'hall'], true) && $this->resolutionType === $type, 404);

        if ($type === 'lecturer') {
            $this->selectedLecturerId = $id;
        } else {
            $this->selectedHallId = $id;
        }

        $this->clearAssignmentConflict();
    }

    public function updatedLecturerSearch(): void
    {
        $this->clearAssignmentConflict();
    }

    public function updatedHallSearch(): void
    {
        $this->clearAssignmentConflict();
    }

    public function updatedSelectedLecturerId(): void
    {
        $this->clearAssignmentConflict();
    }

    public function updatedSelectedHallId(): void
    {
        $this->clearAssignmentConflict();
    }

    public function selectedResolutionOptionLabel(): ?string
    {
        $service = app(BlockedWeeklySlotReconciliationService::class);

        return $this->resolutionType === 'lecturer' && $this->selectedLecturerId
            ? $service->lecturerOptionLabel($this->selectedLecturerId)
            : ($this->resolutionType === 'hall' && $this->selectedHallId ? $service->hallOptionLabel($this->selectedHallId) : null);
    }

    public function applyResolution(): void
    {
        abort_unless($this->selectedSlotId && $this->resolutionType, 404);

        $proposal = match ($this->resolutionType) {
            'lecturer' => ['action' => BlockedWeeklySlotReconciliationService::ACTION_ASSIGN_LECTURER, 'lecturer_id' => $this->selectedLecturerId],
            'hall' => ['action' => BlockedWeeklySlotReconciliationService::ACTION_ASSIGN_HALL, 'hall_id' => $this->selectedHallId],
            default => throw new \LogicException('نوع المعالجة غير صالح.'),
        };
        $actor = Filament::auth()->user();
        abort_unless($actor instanceof User, 403);

        $startedAt = hrtime(true);
        $slotId = $this->selectedSlotId;
        $this->debugTiming('action_started', ['slot_id' => $slotId, 'action' => $proposal['action']]);
        try {
            $result = app(BlockedWeeklySlotReconciliationService::class)->apply([$slotId], $proposal, $actor);
        } catch (ScheduleAssignmentConflictException $exception) {
            $this->showAssignmentConflict($exception);

            return;
        }
        $failed = collect($result['results'])->firstWhere('status', 'failed');

        if ($failed) {
            Notification::make()->title('تعذر تطبيق المعالجة')->body((string) ($failed['error'] ?? 'تعذر تحديث الخانة.'))->danger()->send();

            return;
        }

        $this->closeResolution();
        $this->issuesVersion++;
        $this->debugTiming('action_finished', ['slot_id' => $slotId, 'duration_ms' => $this->elapsedMilliseconds($startedAt)]);
        Notification::make()->title('تم تحديث الخانة وإعادة التحقق منها.')->success()->send();
    }

    public function openTimeResolution(int $slotId): void
    {
        $this->authorizeAction(\App\Policies\ScheduleImportRowPolicy::CHANGE_RECONCILED_WEEKLY_TIME);
        $this->selectedSlotId = $slotId;
        $this->resolutionType = 'time';
        $row = collect($this->issueResult()->issues)->firstWhere('slot_id', $slotId);
        $this->selectedStartTime = $row['start_time'] ?? null;
        $this->selectedEndTime = $row['end_time'] ?? null;
        $this->selectedConflicts = collect($this->issueResult()->preview['conflicts'] ?? [])
            ->filter(fn (array $conflict): bool => (int) ($conflict['source_slot_id'] ?? 0) === $slotId || (int) ($conflict['conflicting_source_slot_id'] ?? 0) === $slotId)
            ->values()
            ->all();
    }

    public function openExclusion(int $slotId): void
    {
        $this->authorizeAction(\App\Policies\ScheduleImportRowPolicy::EXCLUDE_WEEKLY_SLOT_FROM_CURRENT_BATCH);
        $this->selectedSlotId = $slotId;
        $this->resolutionType = 'exclude';
        $this->exclusionReason = null;
        $this->selectedConflicts = [];
    }

    public function applyTimeResolution(): void
    {
        $this->applyProposal(['action' => BlockedWeeklySlotReconciliationService::ACTION_CHANGE_TIME, 'start_time' => $this->selectedStartTime, 'end_time' => $this->selectedEndTime]);
    }

    public function applyExclusion(): void
    {
        $this->applyProposal(['action' => BlockedWeeklySlotReconciliationService::ACTION_EXCLUDE_FROM_CURRENT_BATCH, 'reason' => $this->exclusionReason]);
    }

    public function reopenSlot(int $slotId): void
    {
        $this->authorizeAction(\App\Policies\ScheduleImportRowPolicy::EXCLUDE_WEEKLY_SLOT_FROM_CURRENT_BATCH);
        $actor = Filament::auth()->user();
        abort_unless($actor instanceof User, 403);
        app(BlockedWeeklySlotReconciliationService::class)->reopenBatchScopedExclusion($slotId, $actor);
        $this->issuesVersion++;
        Notification::make()->title('تمت إعادة فتح الخانة وإعادة تقييمها.')->success()->send();
    }

    public function exportExcel()
    {
        $this->authorizeAction(\App\Policies\ScheduleImportRowPolicy::EXPORT);
        $term = AcademicTerm::query()->findOrFail($this->academicTermId);
        $batch = $this->importBatchId ? ImportBatch::query()->find($this->importBatchId) : null;

        return Excel::download(new WeeklyScheduleIssuesExport($this->issueResult(), $this->filteredRows(), $term->display_name, $batch?->source_filename ?: $batch?->completed_at?->format('d/m/Y')), 'weekly-schedule-issues.xlsx');
    }

    private function applyProposal(array $proposal): void
    {
        abort_unless($this->selectedSlotId !== null, 404);
        $actor = Filament::auth()->user();
        abort_unless($actor instanceof User, 403);
        $startedAt = hrtime(true);
        $slotId = $this->selectedSlotId;
        $this->debugTiming('action_started', ['slot_id' => $slotId, 'action' => $proposal['action']]);
        $result = app(BlockedWeeklySlotReconciliationService::class)->apply([$slotId], $proposal, $actor);
        $failed = collect($result['results'])->firstWhere('status', 'failed');
        if ($failed) {
            Notification::make()->title('تعذر تطبيق المعالجة')->body((string) ($failed['error'] ?? 'تعذر تحديث الخانة.'))->danger()->send();

            return;
        }
        $this->closeResolution();
        $this->issuesVersion++;
        $this->debugTiming('action_finished', ['slot_id' => $slotId, 'duration_ms' => $this->elapsedMilliseconds($startedAt)]);
        Notification::make()->title('تم تحديث الخانة وإعادة التحقق منها.')->success()->send();
    }

    private function authorizeAction(string $ability): void
    {
        $actor = Filament::auth()->user();
        abort_unless($actor instanceof User, 403);
        Gate::forUser($actor)->authorize($ability);
    }

    /** @return array<string, string|int|null> */
    private function filters(): array
    {
        return [
            'status' => $this->statusFilter,
            'reason' => $this->reasonFilter,
            'faculty' => $this->facultyFilter,
            'department' => $this->departmentFilter,
            'subject' => $this->subjectFilter,
            'section' => $this->sectionFilter,
            'lecturer' => $this->lecturerFilter,
            'hall' => $this->hallFilter,
            'weekday' => $this->weekdayFilter,
        ];
    }

    /** @param array<string, mixed> $context */
    private function debugTiming(string $stage, array $context = []): void
    {
        if (! app()->hasDebugModeEnabled()) {
            return;
        }

        Log::debug('schedule-import-issues timing', ['stage' => $stage, ...$context]);
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }

    private function clearAssignmentConflict(): void
    {
        $this->assignmentConflict = null;
        $this->resetErrorBag('selectedLecturerId');
        $this->resetErrorBag('selectedHallId');
    }

    private function showAssignmentConflict(ScheduleAssignmentConflictException $exception): void
    {
        $type = count($exception->conflicts) > 1 ? 'multiple' : $this->resolutionType;
        $type = in_array($type, ['lecturer', 'hall', 'section', 'multiple'], true) ? $type : 'section';
        $baseKey = 'schedule-import-issues.conflict.';
        $field = $this->resolutionType === 'hall' ? 'selectedHallId' : 'selectedLecturerId';
        $fieldKey = $this->resolutionType === 'hall' ? 'field_hall' : 'field_lecturer';

        $this->assignmentConflict = [
            'title' => __($baseKey.$type.'_title'),
            'message' => __($baseKey.$type.'_message'),
            'hint' => __($baseKey.$type.'_hint'),
            'conflicts' => collect($exception->conflicts)->take(3)->map(fn (array $conflict): array => [
                'weekday' => __('weekly-schedule.weekdays')[(int) $conflict['weekday']] ?? (string) $conflict['weekday'],
                'time' => $conflict['startTime'].' – '.$conflict['endTime'],
                'subject' => $conflict['subjectName'],
                'section' => $conflict['sectionCode'],
                'lecturer' => $conflict['lecturerName'],
                'hall' => $conflict['hallLabel'],
            ])->all(),
            'additional_count' => max(0, count($exception->conflicts) - 3),
        ];
        $this->addError($field, __($baseKey.$fieldKey));
        Notification::make()->danger()->title(__($baseKey.'could_not_save'))->body(__($baseKey.'notification'))->persistent()->send();
    }
}
