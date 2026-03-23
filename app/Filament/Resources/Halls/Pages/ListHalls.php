<?php

namespace App\Filament\Resources\Halls\Pages;

use App\Filament\Resources\Halls\HallResource;
use App\Imports\HallsImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;
use Illuminate\View\Compilers\BladeCompiler;

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
                ->action(fn() => Excel::download(new \App\Exports\Templates\HallsTemplateExport(), 'halls_template.xlsx')),

            Action::make('import')
                ->label(__('hall.import_excel'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->modalHeading(__('import-help.modal_title.halls'))
                ->modalContent(new \Illuminate\Support\HtmlString('<div class="p-4 space-y-3 prose-sm prose max-w-none" dir="rtl"><ul class="ml-6 space-y-2 text-gray-300 list-disc">' . implode('', array_map(fn($i) => '<li class="text-sm">' . htmlspecialchars($i) . '</li>', __('import-help.simple_instructions'))) . '</ul><p class="mt-4 text-xs text-gray-400">' . __('import-help.column_order_note') . '</p></div>'))
                ->form([
                    FileUpload::make('file')
                        ->label(__('hall.excel_file'))
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        Excel::import(new HallsImport, $data['file']);

                        Notification::make()
                            ->title(__('hall.import_success'))
                            ->success()
                            ->send();
                    } catch (ValidationException $e) {
                        $messages = [];
                        foreach ($e->failures() as $failure) {
                            $row = $failure->row();
                            $values = $failure->values();

                            foreach ($failure->errors() as $error) {
                                $errorMessage = str_replace(
                                    ':row',
                                    $row,
                                    $error
                                );

                                if (str_contains($errorMessage, ':input')) {
                                    $attribute = $failure->attribute();
                                    $errorMessage = str_replace(
                                        ':input',
                                        $values[$attribute] ?? $attribute,
                                        $errorMessage
                                    );
                                }

                                $messages[] = $errorMessage;
                            }
                        }

                        Notification::make()
                            ->title(__('hall.import_failed'))
                            ->body(implode("<br>", $messages))
                            ->danger()
                            ->send();
                    }
                }),

            CreateAction::make(),
        ];
    }
}

