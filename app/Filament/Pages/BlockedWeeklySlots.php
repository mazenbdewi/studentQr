<?php

namespace App\Filament\Pages;

use App\Exports\BlockedWeeklySlotsExport;
use App\Models\LectureSessionGenerationRun;
use App\Services\BlockedWeeklySlotReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BlockedWeeklySlots extends Page
{
    protected static ?string $slug = 'blocked-weekly-slots';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ExclamationTriangle;

    protected string $view = 'filament.pages.blocked-weekly-slots';

    public ?int $selectedSlotId = null;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasAnyRole(['super-admin', 'admin']);
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
        $report = $this->report();
        $runId = $report['run'] instanceof LectureSessionGenerationRun ? $report['run']->id : 'latest';
        $timestamp = now()->format('Ymd-His');

        return Excel::download(
            new BlockedWeeklySlotsExport($report['rows'], $report['conflicts']),
            "blocked-weekly-slots-{$runId}-{$timestamp}.xlsx",
            ExcelWriter::XLSX,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
