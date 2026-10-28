<?php

namespace App\Filament\Pages;

use App\Exports\ManaraEnrollmentImportErrorsExport;
use App\Imports\ManaraStudentEnrollmentsImport;
use App\Models\ImportBatch;
use App\Services\ActivityLogger;
use App\Support\SpreadsheetRowCounter;
use App\Support\XlsxNumericCellSanitizer;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/** @property-read Schema $form */
class ManaraEnrollmentImport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'manara-enrollment-import';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowUpTray;

    protected string $view = 'filament.pages.manara-enrollment-import';

    public ?array $data = [];

    public ?array $summary = null;

    public ?string $errorsUrl = null;

    public bool $uploadReady = false;

    public ?string $completedBatchUuid = null;

    public ?string $activeBatchUuid = null;

    public ?string $pendingFilePath = null;

    public ?string $pendingFileName = null;

    public bool $processingImport = false;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasAnyRole(['super-admin', 'admin']);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-dashboard.navigation.initial_setup');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-dashboard.navigation.initial_setup_step_one');
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public function getTitle(): string
    {
        return __('manara-import.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('manara-import.form_section'))
                    ->schema([
                        FileUpload::make('file')
                            ->label(__('manara-import.excel_file'))
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                            ])
                            ->maxSize(51200)
                            ->live()
                            ->afterStateUpdated(fn (mixed $state) => $this->handleUploadedFileStateChanged($state))
                            ->required(),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function verifyUploadedFileReady(): void
    {
        $this->setUploadReadyState($this->data['file'] ?? null);
    }

    public function startImport(): void
    {
        $state = $this->form->getState();
        $file = $state['file'] ?? null;
        $fileName = basename((string) $file);
        $this->resetResults();

        try {
            $uploadedFilePath = $this->localPathForUploadedFile((string) $file);
            $sourceFingerprint = hash_file('sha256', $uploadedFilePath);

            if ($sourceFingerprint === false) {
                throw new \RuntimeException('تعذر حساب بصمة ملف التسجيل المرفوع.');
            }

            $batch = ImportBatch::query()->firstOrNew([
                'deduplication_key' => ImportBatch::deduplicationKey(
                    ImportBatch::TYPE_ENROLLMENTS,
                    $sourceFingerprint,
                ),
            ]);

            $samePreparedBatch = $batch->exists
                && $batch->status === ImportBatch::STATUS_PENDING
                && $this->activeBatchUuid === $batch->uuid
                && $this->processingImport;
            $activeProcessingBatch = $batch->exists
                && $batch->status === ImportBatch::STATUS_PROCESSING
                && $batch->updated_at?->isAfter(now()->subHours(2));

            if ($samePreparedBatch || $activeProcessingBatch) {
                Notification::make()->title(__('manara-import.already_processing'))->warning()->send();

                return;
            }

            $totalRows = app(SpreadsheetRowCounter::class)->countSourceRows($uploadedFilePath);
            $batch->fill([
                'import_type' => ImportBatch::TYPE_ENROLLMENTS,
                'source_filename' => $fileName,
                'source_fingerprint' => $sourceFingerprint,
                'source_import_batch_id' => null,
                'status' => ImportBatch::STATUS_PENDING,
                'total_rows' => $totalRows,
                'processed_rows' => 0,
                'imported_rows' => 0,
                'rejected_rows' => 0,
                'summary' => null,
                'error_file_path' => null,
                'started_at' => now(),
                'completed_at' => null,
                'created_by' => Filament::auth()->id(),
            ])->save();

            $this->activeBatchUuid = $batch->uuid;
            $this->pendingFilePath = (string) $file;
            $this->pendingFileName = $fileName;
            $this->processingImport = true;
            $this->dispatch('enrollment-import-prepared',
                progressUrl: route('admin.import-batches.progress', $batch, false),
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->processingImport = false;
            Notification::make()->title(__('manara-import.failed'))->body($exception->getMessage())->danger()->send();
        }
    }

    /** Backward-compatible synchronous entry point used by existing integrations and tests. */
    public function import(): void
    {
        $this->startImport();

        if ($this->processingImport && $this->activeBatchUuid) {
            $this->processImport();
        }
    }

    public function processImport(): void
    {
        if (! $this->processingImport || blank($this->activeBatchUuid) || blank($this->pendingFilePath)) {
            return;
        }

        $batch = ImportBatch::query()
            ->where('uuid', $this->activeBatchUuid)
            ->where('import_type', ImportBatch::TYPE_ENROLLMENTS)
            ->where('created_by', Filament::auth()->id())
            ->firstOrFail();

        if ($batch->status !== ImportBatch::STATUS_PENDING) {
            $this->processingImport = false;

            return;
        }

        $startedAt = $batch->started_at ?? now();
        $fileName = $this->pendingFileName ?: basename($this->pendingFilePath);
        $sanitizedFile = null;

        try {
            $this->prepareLongRunningImport();
            $batch->update(['status' => ImportBatch::STATUS_PROCESSING]);
            $uploadedFilePath = $this->localPathForUploadedFile($this->pendingFilePath);

            $import = new ManaraStudentEnrollmentsImport;
            $lastPersisted = 0;
            $import->onProgress(function (int $processed, array $summary) use ($batch, &$lastPersisted): void {
                if ($processed - $lastPersisted < 100 && $processed < (int) $batch->total_rows) {
                    return;
                }

                $lastPersisted = $processed;
                $batch->update([
                    'processed_rows' => min((int) $batch->total_rows, $processed),
                    'imported_rows' => (int) ($summary['imported_rows'] ?? 0),
                    'rejected_rows' => (int) ($summary['failed_rows'] ?? 0),
                ]);
            });
            $sanitizer = app(XlsxNumericCellSanitizer::class);
            $sanitizedFile = $sanitizer->sanitizeToTemporaryFile($uploadedFilePath);

            Excel::import($import, $sanitizedFile);

            $this->summary = $import->getSummary();

            if ($import->getErrors() !== []) {
                $errorPath = 'import-errors/manara-enrollment-errors-'.now()->format('Ymd-His').'.xlsx';
                Excel::store(new ManaraEnrollmentImportErrorsExport($import->getErrors()), $errorPath, 'public');
                $this->errorsUrl = route(
                    'admin.manara-enrollment-import.errors.download',
                    ['fileName' => basename($errorPath)],
                    false,
                );
            }

            $termSync = [];

            foreach ($import->getImportedAcademicTermRowCounts() as $academicTermId => $rowCount) {
                $termSync[$academicTermId] = ['row_count' => $rowCount];
            }

            $batch->academicTerms()->sync($termSync);
            $batch->update([
                'status' => ((int) $this->summary['failed_rows']) > 0
                    ? ImportBatch::STATUS_COMPLETED_WITH_ERRORS
                    : ImportBatch::STATUS_COMPLETED,
                'total_rows' => (int) $this->summary['total_rows'],
                'processed_rows' => (int) $this->summary['total_rows'],
                'imported_rows' => (int) $this->summary['imported_rows'],
                'rejected_rows' => (int) $this->summary['failed_rows'],
                'summary' => $this->summary,
                'error_file_path' => $errorPath ?? null,
                'completed_at' => now(),
            ]);
            $this->completedBatchUuid = $batch->uuid;

            app(ActivityLogger::class)->logImportSummary(
                'students',
                'manara_enrollment_import',
                $fileName,
                (int) $this->summary['total_rows'],
                (int) $this->summary['imported_rows'],
                (int) $this->summary['failed_rows'],
                $startedAt->toIso8601String(),
                now()->toIso8601String(),
                ['summary' => $this->summary],
            );

            Notification::make()
                ->title(__('manara-import.completed'))
                ->body(__('manara-import.completed_body', [
                    'imported' => $this->summary['imported_rows'],
                    'failed' => $this->summary['failed_rows'],
                ]))
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            $batch?->update([
                'status' => ImportBatch::STATUS_FAILED,
                'completed_at' => now(),
                'summary' => ['error' => $exception->getMessage()],
            ]);

            app(ActivityLogger::class)->logImportSummary(
                'students',
                'manara_enrollment_import',
                $fileName,
                0,
                0,
                1,
                $startedAt->toIso8601String(),
                now()->toIso8601String(),
                ['status' => 'failed', 'error' => $exception->getMessage()],
            );

            Notification::make()
                ->title(__('manara-import.failed'))
                ->body($exception->getMessage())
                ->danger()
                ->send();
        } finally {
            if (isset($sanitizer)) {
                $sanitizer->deleteTemporaryFile($sanitizedFile);
            }

            $this->processingImport = false;
            $this->dispatch('enrollment-import-finished');
        }
    }

    private function resetResults(): void
    {
        $this->summary = null;
        $this->errorsUrl = null;
        $this->completedBatchUuid = null;
    }

    private function handleUploadedFileStateChanged(mixed $state): void
    {
        $this->resetResults();
        $this->activeBatchUuid = null;
        $this->pendingFilePath = null;
        $this->pendingFileName = null;
        $this->processingImport = false;
        $this->setUploadReadyState($state);
    }

    private function setUploadReadyState(mixed $state): void
    {
        $this->uploadReady = $this->hasValidUploadedFile($state);
        $this->dispatch('manara-upload-state', ready: $this->uploadReady);
    }

    private function hasValidUploadedFile(mixed $state): bool
    {
        if ($state instanceof TemporaryUploadedFile) {
            return $state->isValid() && $state->exists();
        }

        if (is_array($state)) {
            foreach ($state as $file) {
                if ($this->hasValidUploadedFile($file)) {
                    return true;
                }
            }

            return false;
        }

        return is_string($state)
            && $state !== ''
            && is_file($this->localPathForUploadedFile($state));
    }

    private function localPathForUploadedFile(string $file): string
    {
        if (is_file($file)) {
            return $file;
        }

        $disk = config('filament.default_filesystem_disk', config('filesystems.default'));
        $path = Storage::disk($disk)->path($file);

        return is_file($path) ? $path : $file;
    }

    private function prepareLongRunningImport(): void
    {
        @set_time_limit(0);
        DB::disableQueryLog();

        if (app()->bound(\Fruitcake\LaravelDebugbar\LaravelDebugbar::class)) {
            app(\Fruitcake\LaravelDebugbar\LaravelDebugbar::class)->disable();
        }
    }
}
