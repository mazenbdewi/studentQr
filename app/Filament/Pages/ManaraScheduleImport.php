<?php

namespace App\Filament\Pages;

use App\Exports\LecturerLoginCredentialsExport;
use App\Exports\ManaraScheduleImportErrorsExport;
use App\Imports\WeeklyScheduleImport;
use App\Models\AcademicTerm;
use App\Models\ImportBatch;
use App\Services\LecturerAccountPreparationService;
use App\Services\ScheduleAcademicTermResolver;
use App\Services\ScheduleImportReconciliationBuilder;
use App\Support\XlsxNumericCellSanitizer;
use BackedEnum;
use Filament\Actions\Action;
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
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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

    public bool $sourceBatchReady = false;

    public ?string $sourceResolutionError = null;

    public ?string $resolvedAcademicTermName = null;

    public ?string $sourceBatchFilename = null;

    public ?int $sourceBatchImportedRows = null;

    public ?string $reconciliationUrl = null;

    public ?string $weeklyScheduleUrl = null;

    public ?string $resultStatus = null;

    public ?string $resultStatusLabel = null;

    public ?string $resultBatchUuid = null;

    public bool $resultHasPersistedSchedule = false;

    public bool $uploadReady = false;

    public bool $accountsPrepared = false;

    public function mount(): void
    {
        $this->form->fill();

        $resultBatch = request()->query('batch');

        if (is_string($resultBatch) && $resultBatch !== '') {
            $batch = ImportBatch::query()
                ->where('uuid', $resultBatch)
                ->where('import_type', ImportBatch::TYPE_WEEKLY_SCHEDULE)
                ->with(['sourceImportBatch.academicTerms', 'academicTerms'])
                ->firstOrFail();
            $sourceBatch = $batch->sourceImportBatch;
            $this->sourceBatchUuid = $sourceBatch?->uuid;
            $this->sourceBatchReady = $sourceBatch?->isEligibleEnrollmentSource() === true
                && $sourceBatch->academicTerms->count() === 1;
            $this->resolvedAcademicTermName = $batch->academicTerms->first()?->display_name;
            $this->sourceBatchFilename = $sourceBatch?->source_filename;
            $this->sourceBatchImportedRows = $sourceBatch?->imported_rows;
            $this->loadBatchResult($batch);
            $this->sendBatchResultNotification($batch);

            return;
        }

        $sourceBatch = request()->query('source_batch');
        $explicitSourceBatchUuid = is_string($sourceBatch) && $sourceBatch !== '' ? $sourceBatch : null;

        try {
            [$resolvedBatch, $academicTerm] = app(ScheduleAcademicTermResolver::class)->resolve(
                [],
                $explicitSourceBatchUuid,
            );
            $this->setResolvedSourceBatch($resolvedBatch, $academicTerm);
        } catch (\RuntimeException $exception) {
            $this->sourceBatchUuid = null;
            $this->sourceBatchReady = false;
            $this->sourceResolutionError = $exception->getMessage();
        }
    }

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
        return __('manara-schedule-import.navigation_label');
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
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
                            ->disabled(fn (): bool => ! $this->sourceBatchReady)
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

        if (! $this->sourceBatchReady || blank($this->sourceBatchUuid)) {
            $this->sendFailedNotification($this->sourceResolutionError);

            return;
        }

        $file = $state['file'] ?? null;
        $fileName = $originalFilename ?: basename((string) $file);
        $sanitizedFile = null;
        $import = null;
        $batch = null;
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
                $batch?->refresh();
            }

            if (! $batch) {
                throw new \RuntimeException(__('manara-schedule-import.failed_no_result'));
            }

            $this->loadBatchResult($batch->fresh());
            $this->sendBatchResultNotification($batch);
        } catch (Throwable $exception) {
            report($exception);

            $batch ??= $import?->getBatch();
            $batch?->refresh();

            if ($batch && in_array($batch->status, [
                ImportBatch::STATUS_COMPLETED,
                ImportBatch::STATUS_COMPLETED_WITH_ERRORS,
            ], true)) {
                $this->loadBatchResult($batch);
                $this->sendBatchResultNotification($batch);
            } else {
                $this->sendFailedNotification($exception->getMessage());
            }
        } finally {
            if (isset($sanitizer)) {
                $sanitizer->deleteTemporaryFile($sanitizedFile);
            }
        }
    }

    public function canPrepareUserAccounts(): bool
    {
        $term = $this->accountPreparationTerm();

        if (! $this->resultHasPersistedSchedule || ! $term instanceof AcademicTerm) {
            return false;
        }

        return $this->accountsNeedingPreparation($term) > 0;
    }

    public function hasNoAccountsNeedingPreparation(): bool
    {
        $term = $this->accountPreparationTerm();

        return $this->resultHasPersistedSchedule
            && $term instanceof AcademicTerm
            && $this->accountsNeedingPreparation($term) === 0;
    }

    public function prepareUserAccounts(): ?BinaryFileResponse
    {
        $term = $this->accountPreparationTerm();

        if (! $this->resultHasPersistedSchedule || ! $term instanceof AcademicTerm) {
            Notification::make()->title('أكمل استيراد البرنامج الأسبوعي بنجاح أولًا.')->warning()->send();

            return null;
        }

        if ($this->accountsNeedingPreparation($term) === 0) {
            $this->accountsPrepared = true;
            Notification::make()->title('لا توجد حسابات تحتاج إلى التهيئة.')->success()->send();

            return null;
        }

        try {
            $result = app(LecturerAccountPreparationService::class)->prepareBulkAccounts($term, Filament::auth()->user());
            $this->accountsPrepared = true;

            Notification::make()
                ->title('تمت تهيئة حسابات المستخدمين')
                ->body(__('lecturer-account-preparation.bulk_completed_body', [
                    'created' => $result['created_account_count'],
                    'roles' => $result['granted_role_count'],
                    'blocked' => $result['blocked_count'],
                ]))
                ->success()
                ->send();

            return $result['credential_rows'] === [] ? null : Excel::download(
                new LecturerLoginCredentialsExport($result['credential_rows']),
                'lecturer-login-credentials-'.$this->safeFilenameSegment($term->display_name).'-'.now()->format('Ymd-His').'.xlsx',
                ExcelWriter::XLSX,
            );
        } catch (\RuntimeException $exception) {
            Notification::make()->title($exception->getMessage())->warning()->send();
        } catch (Throwable) {
            Notification::make()->title('تعذر إكمال تهيئة الحسابات. بقيت نتيجة الاستيراد محفوظة ويمكن المحاولة مجددًا.')->danger()->send();
        }

        return null;
    }

    private function resetResults(): void
    {
        $this->summary = null;
        $this->errorsUrl = null;
        $this->reconciliationUrl = null;
        $this->weeklyScheduleUrl = null;
        $this->resultStatus = null;
        $this->resultStatusLabel = null;
        $this->resultBatchUuid = null;
        $this->resultHasPersistedSchedule = false;
        $this->accountsPrepared = false;
    }

    private function setResolvedSourceBatch(ImportBatch $batch, \App\Models\AcademicTerm $academicTerm): void
    {
        $this->sourceBatchUuid = $batch->uuid;
        $this->sourceBatchReady = true;
        $this->resolvedAcademicTermName = $academicTerm->display_name;
        $this->sourceBatchFilename = $batch->source_filename;
        $this->sourceBatchImportedRows = $batch->imported_rows;
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

    private function loadBatchResult(ImportBatch $batch): void
    {
        $batch->loadMissing('academicTerms');
        $this->summary = [
            ...($batch->summary ?? []),
            'weekly_schedule_slots' => $batch->scheduleSlots()->count(),
        ];
        $this->resultStatus = $batch->status;
        $this->resultStatusLabel = match ($batch->status) {
            ImportBatch::STATUS_COMPLETED => __('manara-schedule-import.status.completed'),
            ImportBatch::STATUS_COMPLETED_WITH_ERRORS => __('manara-schedule-import.status.completed_with_errors'),
            default => __('manara-schedule-import.status.failed'),
        };
        $this->resultBatchUuid = $batch->uuid;
        $this->resultHasPersistedSchedule = in_array($batch->status, [
            ImportBatch::STATUS_COMPLETED,
            ImportBatch::STATUS_COMPLETED_WITH_ERRORS,
        ], true);
        $this->weeklyScheduleUrl = $this->resultHasPersistedSchedule
            ? WeeklySchedule::getUrl(['batch' => $batch->uuid])
            : null;
        $this->reconciliationUrl = $this->resultHasPersistedSchedule
            ? ScheduleImportReconciliationReport::getUrl(['batch' => $batch->uuid])
            : null;

        if (filled($batch->error_file_path)) {
            $this->errorsUrl = route(
                'admin.manara-schedule-import.errors.download',
                ['fileName' => basename((string) $batch->error_file_path)],
                false,
            );
        }
    }

    private function accountPreparationTerm(): ?AcademicTerm
    {
        if (! $this->resultBatchUuid) {
            return null;
        }

        return ImportBatch::query()
            ->where('uuid', $this->resultBatchUuid)
            ->with('academicTerms:id')
            ->first()?->academicTerms
            ->first();
    }

    private function accountsNeedingPreparation(AcademicTerm $term): int
    {
        return collect(app(LecturerAccountPreparationService::class)->previewBulkPreparation($term)['rows'])
            ->whereIn('status', ['needs_create', 'needs_course_lecturer_role', 'needs_login_username'])
            ->count();
    }

    private function safeFilenameSegment(string $value): string
    {
        $segment = preg_replace('/[^\pL\pN\-]+/u', '-', $value) ?: 'academic-term';

        return trim($segment, '-') ?: 'academic-term';
    }

    private function sendBatchResultNotification(ImportBatch $batch): void
    {
        $summary = $this->summary ?? [];
        $notification = Notification::make()
            ->title(match ($batch->status) {
                ImportBatch::STATUS_COMPLETED => __('manara-schedule-import.status.completed'),
                ImportBatch::STATUS_COMPLETED_WITH_ERRORS => __('manara-schedule-import.status.completed_with_errors'),
                default => __('manara-schedule-import.status.failed'),
            })
            ->body(match ($batch->status) {
                ImportBatch::STATUS_COMPLETED => __('manara-schedule-import.completed_body', [
                    'slots' => $summary['weekly_schedule_slots'] ?? 0,
                    'imported' => $summary['imported_rows'] ?? 0,
                ]),
                ImportBatch::STATUS_COMPLETED_WITH_ERRORS => __('manara-schedule-import.completed_with_errors_body', [
                    'slots' => $summary['weekly_schedule_slots'] ?? 0,
                    'imported' => $summary['imported_rows'] ?? 0,
                    'rejected' => $summary['rejected_rows'] ?? 0,
                ]),
                default => __('manara-schedule-import.failed_body'),
            })
            ->actions($this->resultNotificationActions());

        match ($batch->status) {
            ImportBatch::STATUS_COMPLETED => $notification->success(),
            ImportBatch::STATUS_COMPLETED_WITH_ERRORS => $notification->warning(),
            default => $notification->danger(),
        };

        $notification->send();
    }

    private function sendFailedNotification(?string $detail = null): void
    {
        Notification::make()
            ->title(__('manara-schedule-import.status.failed'))
            ->body(collect([
                __('manara-schedule-import.failed_body'),
                filled($detail) ? $detail : null,
            ])->filter()->implode(' '))
            ->danger()
            ->send();
    }

    /** @return array<int, Action> */
    private function resultNotificationActions(): array
    {
        return collect([
            $this->weeklyScheduleUrl ? Action::make('view-weekly-schedule')
                ->label(__('manara-schedule-import.open_weekly_schedule'))
                ->button()
                ->url($this->weeklyScheduleUrl) : null,
            $this->reconciliationUrl ? Action::make('view-reconciliation')
                ->label(__('manara-schedule-import.open_reconciliation'))
                ->url($this->reconciliationUrl) : null,
            $this->errorsUrl ? Action::make('download-errors')
                ->label(__('manara-schedule-import.download_errors'))
                ->color('danger')
                ->url($this->errorsUrl, shouldOpenInNewTab: true) : null,
        ])->filter()->values()->all();
    }
}
