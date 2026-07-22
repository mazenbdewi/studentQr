<?php

namespace App\Filament\Pages;

use App\Exports\BlockedWeeklySlotsExport;
use App\Models\LectureSessionGenerationRun;
use App\Models\User;
use App\Services\BlockedWeeklySlotReconciliationService;
use App\Services\BlockedWeeklySlotReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BlockedWeeklySlots extends Page
{
    protected static ?string $slug = 'blocked-weekly-slots';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ExclamationTriangle;

    protected string $view = 'filament.pages.blocked-weekly-slots';

    public ?int $selectedSlotId = null;

    /** @var array<int, int> */
    public array $selectedSlotIds = [];

    /** @var array<string, mixed> */
    public array $filters = [
        'academic_term_id' => '',
        'subject' => '',
        'section' => '',
        'weekday' => '',
        'problem' => '',
        'missing_lecturer' => false,
        'missing_hall' => false,
        'conflict' => false,
        'slot_id' => '',
        'excel_row' => '',
    ];

    public string $bulkAction = BlockedWeeklySlotReconciliationService::ACTION_ASSIGN_LECTURER;

    public ?int $bulkLecturerId = null;

    public ?int $bulkHallId = null;

    public ?int $bulkWeekday = null;

    public string $bulkStartTime = '';

    public string $bulkEndTime = '';

    public string $bulkReason = '';

    public string $bulkNote = '';

    /** @var array<string, mixed>|null */
    public ?array $bulkPreview = null;

    /** @var array<string, mixed>|null */
    public ?array $lastApplyResult = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return (bool) ($user?->hasAnyRole(['super-admin', 'admin']) || $user?->can('preview blocked weekly slot reconciliation'));
    }

    public static function getNavigationGroup(): ?string
    {
        return __('weekly-schedule.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return 'الخانات المحجوبة من توليد الجلسات';
    }

    public static function getNavigationSort(): ?int
    {
        return 7;
    }

    public function getTitle(): string
    {
        return 'الخانات المحجوبة من توليد الجلسات';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadBlockedWeeklySlots')
                ->label('تنزيل تقرير Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action('downloadBlockedWeeklySlots'),
        ];
    }

    /** @return array{run: mixed, rows: array<int, array<string, mixed>>, conflicts: array<int, array<string, mixed>>, summary: array<string, int>} */
    public function report(): array
    {
        return app(BlockedWeeklySlotReportService::class)->latestReport();
    }

    /** @return array<int, array<string, mixed>> */
    public function filteredRows(): array
    {
        return collect($this->report()['rows'])
            ->filter(fn (array $row): bool => $this->rowMatchesFilters($row))
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function savedGroups(): array
    {
        return app(BlockedWeeklySlotReconciliationService::class)->savedGroups();
    }

    /** @return array<int, array<string, mixed>> */
    public function lecturerOptions(): array
    {
        return app(BlockedWeeklySlotReconciliationService::class)->lecturerOptions();
    }

    /** @return array<int, array<string, mixed>> */
    public function hallOptions(): array
    {
        return app(BlockedWeeklySlotReconciliationService::class)->hallOptions();
    }

    /** @return array<int, int> */
    public function filteredSlotIds(): array
    {
        return collect($this->filteredRows())->pluck('رقم الموعد الأسبوعي')->map(fn (mixed $id): int => (int) $id)->values()->all();
    }

    public function selectGroup(string $key): void
    {
        $group = collect($this->savedGroups())->firstWhere('key', $key);
        $this->selectedSlotIds = $group ? array_values($group['slot_ids']) : [];
        $this->bulkPreview = null;
    }

    public function selectFiltered(): void
    {
        $this->selectedSlotIds = $this->filteredSlotIds();
        $this->bulkPreview = null;
    }

    public function clearSelection(): void
    {
        $this->selectedSlotIds = [];
        $this->bulkPreview = null;
    }

    public function previewBulkAction(?string $action = null): void
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        if ($action) {
            $this->bulkAction = $action;
        }

        $this->bulkPreview = app(BlockedWeeklySlotReconciliationService::class)
            ->preview($this->selectedSlotIds, $this->proposal(), $user);
        $this->lastApplyResult = null;
    }

    public function applyBulkAction(): void
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        $this->lastApplyResult = app(BlockedWeeklySlotReconciliationService::class)
            ->apply($this->selectedSlotIds, $this->proposal(), $user);
        $this->bulkPreview = null;
    }

    /** @return array<int, array<string, mixed>> */
    public function selectedConflicts(): array
    {
        if (! $this->selectedSlotId) {
            return [];
        }

        return collect($this->report()['conflicts'])
            ->filter(fn (array $conflict): bool => (int) ($conflict['source_slot_id'] ?? 0) === $this->selectedSlotId
                || (int) ($conflict['conflicting_source_slot_id'] ?? 0) === $this->selectedSlotId)
            ->values()
            ->all();
    }

    public function showConflictDetails(int $slotId): void
    {
        $this->selectedSlotId = $slotId;
    }

    public function closeConflictDetails(): void
    {
        $this->selectedSlotId = null;
    }

    public function downloadBlockedWeeklySlots(): BinaryFileResponse
    {
        Gate::authorize('export blocked weekly slot reports');

        $report = $this->report();
        $runId = $report['run'] instanceof LectureSessionGenerationRun ? $report['run']->id : 'latest';
        $timestamp = now()->format('Ymd-His');
        $treatments = app(BlockedWeeklySlotReconciliationService::class)->suggestedTreatmentRows($report['rows']);

        return Excel::download(
            new BlockedWeeklySlotsExport($report['rows'], $report['conflicts'], $treatments),
            "blocked-weekly-slots-{$runId}-{$timestamp}.xlsx",
            ExcelWriter::XLSX,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    private function rowMatchesFilters(array $row): bool
    {
        $filters = $this->filters;
        $codes = collect($row['رموز المشكلات'] ?? []);

        return (blank($filters['academic_term_id'] ?? null) || (string) ($row['معرف الفصل الدراسي'] ?? '') === (string) $filters['academic_term_id'])
            && (blank($filters['subject'] ?? null) || str_contains((string) ($row['المادة'] ?? ''), (string) $filters['subject']))
            && (blank($filters['section'] ?? null) || str_contains((string) ($row['الشعبة'] ?? ''), (string) $filters['section']))
            && (blank($filters['weekday'] ?? null) || (string) ($row['اليوم'] ?? '') === (string) $filters['weekday'])
            && (blank($filters['problem'] ?? null) || $codes->contains((string) $filters['problem']))
            && (! (bool) ($filters['missing_lecturer'] ?? false) || $codes->contains('missing_lecturer_identity'))
            && (! (bool) ($filters['missing_hall'] ?? false) || $codes->contains('missing_hall'))
            && (! (bool) ($filters['conflict'] ?? false) || $codes->intersect(['weekly_schedule_overlap', 'scheduling_conflict'])->isNotEmpty())
            && (blank($filters['slot_id'] ?? null) || (string) ($row['رقم الموعد الأسبوعي'] ?? '') === (string) $filters['slot_id'])
            && (blank($filters['excel_row'] ?? null) || (string) ($row['رقم صف Excel'] ?? '') === (string) $filters['excel_row']);
    }

    /** @return array<string, mixed> */
    private function proposal(): array
    {
        return [
            'action' => $this->bulkAction,
            'lecturer_id' => $this->bulkLecturerId,
            'hall_id' => $this->bulkHallId,
            'weekday' => $this->bulkWeekday,
            'start_time' => $this->bulkStartTime,
            'end_time' => $this->bulkEndTime,
            'reason' => $this->bulkReason,
            'note' => $this->bulkNote,
        ];
    }
}
