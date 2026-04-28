<?php

namespace App\Filament\Resources\Departments\Pages;

use App\Filament\Resources\Departments\DepartmentResource;
use App\Imports\DepartmentsImport;
use App\Services\ActivityLogger;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;
use Illuminate\View\Compilers\BladeCompiler;

class ListDepartments extends ListRecords
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_template')
                ->label(__('department.template_download'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function () {
                    app(ActivityLogger::class)->logExport(
                        'departments',
                        'departments_template_download',
                        'departments_template.xlsx'
                    );

                    return Excel::download(new \App\Exports\Templates\DepartmentsTemplateExport(), 'departments_template.xlsx');
                }),

            Action::make('import')
                ->label(__('department.import_excel'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->modalHeading(__('import-help.modal_title.departments'))
                ->modalContent(new \Illuminate\Support\HtmlString('<div class="p-4 space-y-3 prose-sm prose max-w-none" dir="rtl"><ul class="ml-6 space-y-2 text-gray-300 list-disc">' . implode('', array_map(fn($i) => '<li class="text-sm">' . htmlspecialchars($i) . '</li>', __('import-help.simple_instructions'))) . '</ul><p class="mt-4 text-xs text-gray-400">' . __('import-help.column_order_note') . '</p></div>'))
                ->form([
                    FileUpload::make('file')
                        ->label(__('department.excel_file'))
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                        ->required(),
                ])
                ->action(function (array $data) {
                    $startedAt = now();
                    $fileName = basename((string) $data['file']);
                    $import = new \App\Imports\DepartmentsImport();

                    try {
                        Excel::import($import, $data['file']);

                        app(ActivityLogger::class)->logImportSummary(
                            'departments',
                            'departments_import',
                            $fileName,
                            $import->getImportedCount(),
                            $import->getImportedCount(),
                            0,
                            $startedAt->toIso8601String(),
                            now()->toIso8601String()
                        );
                        Notification::make()
                            ->title(__('department.import_success'))
                            ->body(__('department.stats_imported', ['count' => $import->getImportedCount()]))
                            ->success()
                            ->send();
                    } catch (ValidationException $e) {
                        // Enhanced error handling same as students
                        $failures = $e->failures();
                        $messages = [];
                        foreach ($failures as $failure) {
                            $row = $failure->row();
                            $values = $failure->values();
                            foreach ($failure->errors() as $error) {
                                $errorMessage = str_replace(':row', $row, $error);
                                if (str_contains($errorMessage, ':input')) {
                                    $attr = $failure->attribute();
                                    $errorMessage = str_replace(':input', $values[$attr] ?? $attr, $errorMessage);
                                }
                                $messages[] = $errorMessage;
                            }
                        }

                        $failedCount = count($failures);

                        app(ActivityLogger::class)->logImportSummary(
                            'departments',
                            'departments_import',
                            $fileName,
                            $import->getImportedCount() + $failedCount,
                            $import->getImportedCount(),
                            $failedCount,
                            $startedAt->toIso8601String(),
                            now()->toIso8601String(),
                            ['status' => 'failed']
                        );

                        Notification::make()
                            ->title(__('department.import_stats', ['imported' => $import->getImportedCount(), 'errors' => count($failures)]))
                            ->body(implode('<br>', array_slice($messages, 0, 5)))
                            ->danger()
                            ->send();
                    }
                }),

            CreateAction::make(),
        ];
    }
}
