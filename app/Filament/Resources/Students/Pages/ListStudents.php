<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
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
            Action::make('import')
                ->label(__('student.import_excel'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success',)
                ->form([
                    FileUpload::make('file')
                        ->label(__('student.excel_file'))
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'application/octet-stream',
                            '*/*'
                        ])
                        ->maxSize(10240)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $file = $data['file'];

                    try {
                        Excel::import(new StudentsImport, $file);
                        Notification::make()
                            ->title(__('student.import_success'))
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
                            ->title(__('student.import_failed'))
                            ->body(implode("<br>", $messages))
                            ->danger()
                            ->send();
                    }
                    // catch (\Exception $e) {
                    //     Notification::make()
                    //         ->title(__('student.import_failed'))
                    //         ->body($e->getMessage())
                    //         ->danger()
                    //         ->send();
                    // }
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
