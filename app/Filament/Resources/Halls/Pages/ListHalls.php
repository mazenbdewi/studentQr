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



class ListHalls extends ListRecords
{
    protected static string $resource = HallResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label(__('hall.import_excel'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    FileUpload::make('file')
                        ->label(__('hall.excel_file'))
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel'
                        ])
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        Excel::import(new HallsImport, $data['file']);

                        Notification::make()
                            ->title(__('hall.import_success'))
                            ->success()
                            ->send();
                    }
                    //  catch (ValidationException $e) {
                    //     $messages = [];
                    //     foreach ($e->failures() as $failure) {
                    //         $row = $failure->row();
                    //         foreach ($failure->errors() as $error) {
                    //             $messages[] = str_replace(
                    //                 [':row', ':input'],
                    //                 [$row, $failure->values()['code'] ?? ''],
                    //                 $error
                    //             );
                    //         }
                    //     }

                    //     Notification::make()
                    //         ->title(__('hall.import_failed'))
                    //         ->body(implode("\n", $messages))
                    //         ->danger()
                    //         ->send();
                    // }
                    catch (ValidationException $e) {
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
