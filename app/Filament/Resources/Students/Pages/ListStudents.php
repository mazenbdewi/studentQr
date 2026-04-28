<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use App\Services\ActivityLogger;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\StudentsImport;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Validators\ValidationException;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_template')
                ->label(__('import-help.template_download'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function () {
                    app(ActivityLogger::class)->logExport(
                        'students',
                        'students_template_download',
                        'students_template.xlsx'
                    );

                    return Excel::download(new \App\Exports\Templates\StudentsTemplateExport(), 'students_template.xlsx');
                }),

            Action::make('import')
                ->label(__('student.import_excel'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
->modalHeading(__('import-help.modal_title.students'))
->modalContent(new \Illuminate\Support\HtmlString('<div class="p-4 space-y-3 prose-sm prose max-w-none" dir="rtl"><ul class="ml-6 space-y-2 text-gray-300 list-disc">' . implode('', array_map(fn($i) => '<li class="text-sm">' . htmlspecialchars($i) . '</li>', __('import-help.simple_instructions'))) . '</ul><p class="mt-4 text-xs text-gray-400">' . __('import-help.column_order_note') . '</p></div>'))
                ->form([
                    FileUpload::make('file')
                        ->label(__('student.excel_file'))
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->maxSize(51200)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $startedAt = now();
                    $file = $data['file'];
                    $fileName = basename((string) $file);
                    $import = new \App\Imports\StudentsImport();

                    $file = $data['file'];
                    try {
                        Excel::import($import, $file);

                        app(ActivityLogger::class)->logImportSummary(
                            'students',
                            'students_import',
                            $fileName,
                            $import->getImportedCount(),
                            $import->getImportedCount(),
                            0,
                            $startedAt->toIso8601String(),
                            now()->toIso8601String()
                        );

                        Notification::make()
                            ->title(__('student.import_success'))
                            ->body(__('import-help.stats.imported', ['count' => $import->getImportedCount()]))
                            ->success()
                            ->send();
                    } catch (ValidationException $e) {
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
                            'students',
                            'students_import',
                            $fileName,
                            $import->getImportedCount() + $failedCount,
                            $import->getImportedCount(),
                            $failedCount,
                            $startedAt->toIso8601String(),
                            now()->toIso8601String(),
                            ['status' => 'failed']
                        );

                        Notification::make()
                            ->title(__('import-help.stats.imported', ['count' => 0]) . ' | ' . __('import-help.stats.errors', ['count' => count($failures)]))
                            ->body(implode('<br>', array_slice($messages, 0, 5)))
                            ->danger()
                            ->send();
                    }
                }),



            CreateAction::make(),


        ];
    }


    //    protected function getHeaderActions(): array
    //    {
    //        return [
    //            CreateAction::make(),
    //        ];
    //    }
}
