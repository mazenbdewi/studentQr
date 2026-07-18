<?php

namespace App\Filament\Pages;

use App\Exports\ManaraScheduleImportErrorsExport;
use App\Imports\WeeklyScheduleImport;
use App\Services\ScheduleImportReconciliationBuilder;
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
class ManaraScheduleImport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'manara-schedule-import';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CalendarDays;

    protected string $view = 'filament.pages.manara-schedule-import';

    public ?array $data = [];

    public ?array $summary = null;

    public ?string $errorsUrl = null;

    public ?string $sourceBatchUuid = null;

    public ?string $reconciliationUrl = null;

    public bool $uploadReady = false;

    public function mount(): void
    {
        $sourceBatch = request()->query('source_batch');
        $this->sourceBatchUuid = is_string($sourceBatch) && $sourceBatch !== '' ? $sourceBatch : null;
        $this->form->fill();
    }

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
        return __('manara-schedule-import.title');
    }

    public static function getNavigationSort(): ?int
    {
        return 6;
    }

    public function getTitle(): string
    {
        return __('manara-schedule-import.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('manara-schedule-import.form_section'))
                    ->schema([
                        FileUpload::make('file')
                            ->label(__('manara-schedule-import.excel_file'))
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                            ])
                            ->maxSize(51200)
                            ->live()
                            ->afterStateUpdated(fn (mixed $state) => $this->handleUploadedFileStateChanged($state))
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function verifyUploadedFileReady(): void
    {
        $this->setUploadReadyState($this->data['file'] ?? null);
    }

    public function import(): void
    {
        $uploadedFile = $this->firstUploadedFile($this->data['file'] ?? null);
        $originalFilename = $uploadedFile?->getClientOriginalName();
        $state = $this->form->getState();
        $file = $state['file'] ?? null;
        $fileName = $originalFilename ?: basename((string) $file);
        $sanitizedFile = null;
        $this->resetResults();

        try {
            $this->prepareLongRunningImport();
            $uploadedFilePath = $this->localPathForUploadedFile((string) $file);
            $sourceFingerprint = hash_file('sha256', $uploadedFilePath);

            if ($sourceFingerprint === false) {
                throw new \RuntimeException('تعذر حساب بصمة ملف الجدول المرفوع.');
            }

            $sanitizer = app(XlsxNumericCellSanitizer::class);
            $sanitizedFile = $sanitizer->sanitizeToTemporaryFile(
                $uploadedFilePath,
            );
            $import = app(WeeklyScheduleImport::class);
            $import->import(
                $sanitizedFile,
                $fileName,
                $this->sourceBatchUuid,
                Filament::auth()->id(),
                $sourceFingerprint,
                (string) $file,
            );
            $this->summary = $import->getSummary();
            $batch = $import->getBatch();

            if ($batch) {
                app(ScheduleImportReconciliationBuilder::class)->build(
                    $batch,
                    $uploadedFilePath,
                    $fileName,
                    (string) $file,
                );
                $this->reconciliationUrl = ScheduleImportReconciliationReport::getUrl(['batch' => $batch->uuid]);
            }

            if ($import->getErrors() !== [] && $batch) {
                $errorPath = 'import-errors/manara-schedule-errors-'.now()->format('Ymd-His').'-'.$batch->uuid.'.xlsx';
                Excel::store(new ManaraScheduleImportErrorsExport($import->getErrors()), $errorPath, 'public');
                $batch->update(['error_file_path' => $errorPath]);
            } else {
                $errorPath = $batch?->error_file_path;
            }

            if (filled($errorPath)) {
                $this->errorsUrl = route(
                    'admin.manara-schedule-import.errors.download',
                    ['fileName' => basename((string) $errorPath)],
                    false,
                );
            }

            Notification::make()
                ->title(__('manara-schedule-import.completed'))
                ->body(__('manara-schedule-import.completed_body', [
                    'imported' => $this->summary['imported_rows'] ?? 0,
                    'rejected' => $this->summary['rejected_rows'] ?? 0,
                ]))
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title(__('manara-schedule-import.failed'))
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
        $this->reconciliationUrl = null;
    }

    private function firstUploadedFile(mixed $state): ?TemporaryUploadedFile
    {
        if ($state instanceof TemporaryUploadedFile) {
            return $state;
        }

        if (is_array($state)) {
            foreach ($state as $file) {
                if ($file instanceof TemporaryUploadedFile) {
                    return $file;
                }
            }
        }

        return null;
    }

    private function handleUploadedFileStateChanged(mixed $state): void
    {
        $this->resetResults();
        $this->setUploadReadyState($state);
    }

    private function setUploadReadyState(mixed $state): void
    {
        $this->uploadReady = $this->hasValidUploadedFile($state);
        $this->dispatch('schedule-upload-state', ready: $this->uploadReady);
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
