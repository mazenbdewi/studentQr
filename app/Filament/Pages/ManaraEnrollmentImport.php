<?php

namespace App\Filament\Pages;

use App\Exports\ManaraEnrollmentImportErrorsExport;
use App\Imports\ManaraStudentEnrollmentsImport;
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
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ManaraEnrollmentImport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'manara-enrollment-import';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowUpTray;

    protected string $view = 'filament.pages.manara-enrollment-import';

    public ?array $data = [];

    public ?array $summary = null;

    public ?string $errorsUrl = null;

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
        return __('manara-import.title');
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
                    ->description(__('manara-import.form_description'))
                    ->schema([
                        FileUpload::make('file')
                            ->label(__('manara-import.excel_file'))
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                            ])
                            ->maxSize(51200)
                            ->required(),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function import(): void
    {
        $state = $this->form->getState();
        $startedAt = now();
        $file = $state['file'] ?? null;
        $fileName = basename((string) $file);
        $sanitizedFile = null;
        $this->summary = null;
        $this->errorsUrl = null;

        $import = new ManaraStudentEnrollmentsImport();

        try {
            $sanitizer = app(XlsxNumericCellSanitizer::class);
            $sanitizedFile = $sanitizer->sanitizeToTemporaryFile($this->localPathForUploadedFile((string) $file));

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

    private function localPathForUploadedFile(string $file): string
    {
        if (is_file($file)) {
            return $file;
        }

        $disk = config('filament.default_filesystem_disk', config('filesystems.default'));
        $path = Storage::disk($disk)->path($file);

        return is_file($path) ? $path : $file;
    }
}
