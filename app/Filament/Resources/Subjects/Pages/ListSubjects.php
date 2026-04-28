<?php

namespace App\Filament\Resources\Subjects\Pages;

use App\Filament\Resources\Subjects\SubjectResource;
use App\Services\ActivityLogger;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\SubjectsImport;
use Maatwebsite\Excel\Validators\ValidationException;

class ListSubjects extends ListRecords
{
    protected static string $resource = SubjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_template')
                ->label(__('subjects.template_download'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function () {
                    app(ActivityLogger::class)->logExport(
                        'subjects',
                        'subjects_template_download',
                        'subjects_template.xlsx'
                    );

                    return Excel::download(new \App\Exports\Templates\SubjectsTemplateExport(), 'subjects_template.xlsx');
                }),

            Action::make('import')
                ->label(__('subjects.import_excel'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->modalHeading(__('import-help.modal_title.subjects'))
->modalContent(new \Illuminate\Support\HtmlString('<div class="p-4 space-y-3 prose-sm prose max-w-none" dir="rtl"><ul class="ml-6 space-y-2 text-gray-300 list-disc">' . implode('', array_map(fn($i) => '<li class="text-sm">' . htmlspecialchars($i) . '</li>', __('import-help.simple_instructions'))) . '</ul><p class="mt-4 text-xs text-gray-400">' . __('import-help.column_order_note') . '</p></div>'))
                ->form([
                    FileUpload::make('file')
                        ->label(__('subjects.excel_file'))
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->required(),
                ])
                ->action(function (array $data) {
                    $startedAt = now();
                    $fileName = basename((string) $data['file']);
                    $import = new \App\Imports\SubjectsImport();

                    try {
                        Excel::import($import, $data['file']);

                        app(ActivityLogger::class)->logImportSummary(
                            'subjects',
                            'subjects_import',
                            $fileName,
                            $import->getImportedCount(),
                            $import->getImportedCount(),
                            0,
                            $startedAt->toIso8601String(),
                            now()->toIso8601String()
                        );

                        Notification::make()
                            ->title(__('subjects.import_success'))
                            ->success()
                            ->send();
                    } catch (ValidationException $e) {
                        $messages = [];

                        foreach ($e->failures() as $failure) {
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

                        $failedCount = count($e->failures());

                        app(ActivityLogger::class)->logImportSummary(
                            'subjects',
                            'subjects_import',
                            $fileName,
                            $import->getImportedCount() + $failedCount,
                            $import->getImportedCount(),
                            $failedCount,
                            $startedAt->toIso8601String(),
                            now()->toIso8601String(),
                            ['status' => 'failed']
                        );

                        Notification::make()
                            ->title(__('subjects.import_failed'))
                            ->body(implode('<br>', array_slice($messages, 0, 5)))
                            ->danger()
                            ->send();
                    }
                }),

            CreateAction::make(),
        ];
    }
}
