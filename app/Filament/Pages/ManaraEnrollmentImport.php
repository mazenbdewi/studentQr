<?php

namespace App\Filament\Pages;

use App\Exports\ManaraEnrollmentImportErrorsExport;
use App\Imports\ManaraStudentEnrollmentsImport;
use App\Models\ImportBatch;
use App\Services\ActivityLogger;
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

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasAnyRole(['super-admin', 'admin']);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-dashboard.navigation.imports_exports');
    }

    public static function getNavigationLabel(): string
    {
        return __('manara-import.navigation_label');
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
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

    public function import(): void
    {
        $state = $this->form->getState();
        $startedAt = now();
        $file = $state['file'] ?? null;
        $fileName = basename((string) $file);
        $sanitizedFile = null;
        $batch = null;
        $this->resetResults();

        try {
            $this->prepareLongRunningImport();

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
            $batch->fill([
                'import_type' => ImportBatch::TYPE_ENROLLMENTS,
                'source_filename' => $fileName,
                'source_fingerprint' => $sourceFingerprint,
                'source_import_batch_id' => null,
                'status' => ImportBatch::STATUS_PROCESSING,
                'total_rows' => 0,
                'imported_rows' => 0,
                'rejected_rows' => 0,
                'summary' => null,
                'error_file_path' => null,
                'started_at' => $startedAt,
                'completed_at' => null,
                'created_by' => Filament::auth()->id(),
            ])->save();

            $import = new ManaraStudentEnrollmentsImport;
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
