<?php

namespace App\Filament\Resources\Halls\Pages;

use App\Exports\HallMetadataReportExport;
use App\Exports\HallMetadataTemplateExport;
use App\Filament\Resources\Halls\HallResource;
use App\Services\ActivityLogger;
use App\Services\HallMetadataService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListHalls extends ListRecords
{
    protected static string $resource = HallResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_template')
                ->label(__('hall.template_download'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function () {
                    Gate::authorize('export hall metadata');

                    app(ActivityLogger::class)->logExport(
                        'halls',
                        'hall_metadata_template_download',
                        HallMetadataService::TEMPLATE_FILENAME
                    );

                    return Excel::download(
                        new HallMetadataTemplateExport,
                        HallMetadataService::TEMPLATE_FILENAME,
                        ExcelWriter::XLSX,
                        ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                    );
                }),

            Action::make('preview_metadata_import')
                ->label(__('hall.preview_metadata_import'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->form($this->metadataImportForm())
                ->action(fn (array $data): BinaryFileResponse => $this->metadataReport($data, preview: true)),

            Action::make('import_metadata')
                ->label(__('hall.import_metadata'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('سيتم تحديث بيانات القاعات حسب رمز القاعة فقط. لا يتم حذف أو إنشاء قاعات في هذا المسار.')
                ->form($this->metadataImportForm())
                ->action(fn (array $data): BinaryFileResponse => $this->metadataReport($data, preview: false)),

            CreateAction::make(),
        ];
    }

    /** @return array<int, FileUpload> */
    private function metadataImportForm(): array
    {
        return [
            FileUpload::make('file')
                ->label(__('hall.excel_file'))
                ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                ->required(),
        ];
    }

    /** @param array<string, mixed> $data */
    private function metadataReport(array $data, bool $preview): BinaryFileResponse
    {
        Gate::authorize($preview ? 'preview hall metadata import' : 'import hall metadata');

        $startedAt = now();
        $path = $this->resolveUploadedPath((string) $data['file']);
        $service = app(HallMetadataService::class);
        $rows = $service->rowsFromSpreadsheet($path);
        $result = $preview ? $service->preview($rows) : $service->import($rows);
        $hasErrors = $result['error_rows'] !== [];
        $timestamp = now()->format('Ymd-His');
        $filename = $hasErrors
            ? "hall-metadata-errors-{$timestamp}.xlsx"
            : ($preview ? "hall-metadata-preview-success-{$timestamp}.xlsx" : "hall-metadata-success-{$timestamp}.xlsx");

        app(ActivityLogger::class)->logImportSummary(
            'halls',
            $preview ? 'hall_metadata_import_preview' : 'hall_metadata_import',
            basename((string) $data['file']),
            count($rows),
            count($result['success_rows']),
            count($result['error_rows']),
            $startedAt->toIso8601String(),
            now()->toIso8601String(),
            ['status' => $hasErrors ? 'completed_with_errors' : 'completed', 'preview' => $preview],
        );

        $notification = Notification::make()
            ->title($preview ? __('hall.preview_complete') : __('hall.import_success'))
            ->body($hasErrors ? __('hall.metadata_errors_found') : __('hall.metadata_no_errors'));

        ($hasErrors ? $notification->danger() : $notification->success())->send();

        return Excel::download(
            $hasErrors ? HallMetadataReportExport::errors($result['error_rows']) : HallMetadataReportExport::success($result['success_rows']),
            $filename,
            ExcelWriter::XLSX,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    private function resolveUploadedPath(string $path): string
    {
        if (is_file($path)) {
            return $path;
        }

        $disk = config('filesystems.default', 'local');
        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->path($path);
        }

        return $path;
    }
}
