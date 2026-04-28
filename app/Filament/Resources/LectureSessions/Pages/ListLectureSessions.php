<?php

namespace App\Filament\Resources\LectureSessions\Pages;

use App\Filament\Resources\LectureSessions\LectureSessionResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ListRecords;


use App\Imports\LectureSessionsImport;

use Filament\Notifications\Notification;

use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

class ListLectureSessions extends ListRecords
{
    protected static string $resource = LectureSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [

            Action::make('download_template')
                ->label(__('lecture-session.template_download'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(fn() => Excel::download(new \App\Exports\Templates\LectureSessionsTemplateExport(), 'lecture_sessions_template.xlsx')),

            Action::make('import')
                ->label(__('lecture-session.import_excel'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->modalHeading(__('import-help.modal_title.lecture_sessions'))
                ->modalContent(new \Illuminate\Support\HtmlString('<div class="p-4 space-y-3 prose-sm prose max-w-none" dir="rtl"><ul class="ml-6 space-y-2 text-gray-300 list-disc">' . implode('', array_map(fn($i) => '<li class="text-sm">' . htmlspecialchars($i) . '</li>', __('import-help.simple_instructions'))) . '</ul><p class="mt-4 text-xs text-gray-400">' . __('import-help.column_order_note') . '</p></div>'))
                ->form([
                    FileUpload::make('file')
                        ->label(__('lecture-session.excel_file'))
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                        ->required(),
                ])
              ->action(function (array $data) {
    try {
        Excel::import(new \App\Imports\LectureSessionsImport(), $data['file']);

        \Filament\Notifications\Notification::make()
            ->title(__('lecture-session.import_success'))
            ->success()
            ->send();

    } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
        $messages = [];

        foreach ($e->failures() as $failure) {
            $row = $failure->row();
            $values = $failure->values();

            foreach ($failure->errors() as $error) {
                $errorMessage = str_replace(':row', $row, $error);

                if (str_contains($errorMessage, ':input')) {
                    $attribute = $failure->attribute();
                    $errorMessage = str_replace(':input', $values[$attribute] ?? $attribute, $errorMessage);
                }

                $messages[] = $errorMessage;
            }
        }

        \Filament\Notifications\Notification::make()
            ->title(__('lecture-session.import_failed'))
            ->body(implode('<br>', array_slice($messages, 0, 5)))
            ->danger()
            ->send();

    } catch (\Illuminate\Validation\ValidationException $e) {
        $messages = [];

        foreach ($e->errors() as $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = $error;
            }
        }

        \Filament\Notifications\Notification::make()
            ->title(__('lecture-session.import_failed'))
            ->body(implode('<br>', array_slice($messages, 0, 5)))
            ->danger()
            ->send();

    } catch (\Throwable $e) {
        \Filament\Notifications\Notification::make()
            ->title(__('lecture-session.import_failed'))
            ->body($e->getMessage())
            ->danger()
            ->send();
    }
}),

            CreateAction::make(),
        ];
    }
}
