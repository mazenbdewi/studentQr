<?php

namespace App\Filament\Pages;

use App\Models\ImportBatch;
use App\Models\ScheduleImportRow;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ScheduleImportReconciliationIndex extends Page
{
    protected static ?string $slug = 'schedule-import-reconciliation';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentCheck;

    protected string $view = 'filament.pages.schedule-import-reconciliation-index';

    public ?string $batchId = null;

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $batches = $this->batchQuery()->get();
        $this->batchId = $batches->count() === 1 ? (string) $batches->first()->id : null;
    }

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->can('viewAny', ScheduleImportRow::class);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('weekly-schedule.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('weekly-schedule.navigation.reconciliation');
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public function getTitle(): string
    {
        return __('weekly-schedule.navigation.reconciliation');
    }

    public function openReport(): mixed
    {
        $batch = $this->batchQuery()->find($this->batchId);
        if (! $batch) {
            Notification::make()->danger()->title(__('weekly-schedule-reports.select_batch_error'))->send();

            return null;
        }

        return redirect(ScheduleImportReconciliationReport::getUrl(['batch' => $batch->uuid]));
    }

    public function batchOptions(): array
    {
        return $this->batchQuery()->get()->mapWithKeys(function (ImportBatch $batch): array {
            $term = $batch->academicTerms->first()?->display_name;

            return [$batch->id => collect([$batch->source_filename, $term])->filter()->implode(' — ')];
        })->all();
    }

    private function batchQuery()
    {
        return ImportBatch::query()
            ->where('import_type', ImportBatch::TYPE_WEEKLY_SCHEDULE)
            ->whereIn('status', [ImportBatch::STATUS_COMPLETED, ImportBatch::STATUS_COMPLETED_WITH_ERRORS])
            ->with('academicTerms')
            ->orderByDesc('completed_at');
    }
}
