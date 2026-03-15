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

            Action::make('import')
                ->label(__('lecture-session.import_excel'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    FileUpload::make('file')
                        ->label(__('lecture-session.excel_file'))
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel'
                        ])
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        Excel::import(
                            new LectureSessionsImport,
                            $data['file']
                        );

                        Notification::make()
                            ->title(__('lecture-session.import_success'))
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
                            ->title(__('lecture-session.import_failed'))
                            ->body(implode("<br>", $messages))
                            ->danger()
                            ->send();
                    }
                    // catch (\Exception $e) {
                    //     Notification::make()
                    //         ->title(__('lecture-session.import_failed'))
                    //         ->body($e->getMessage())
                    //         ->danger()
                    //         ->send();
                    // }
                }),

            CreateAction::make(),
        ];
    }
}
