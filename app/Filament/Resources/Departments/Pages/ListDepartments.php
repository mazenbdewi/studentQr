<?php

namespace App\Filament\Resources\Departments\Pages;

use App\Filament\Resources\Departments\DepartmentResource;
use App\Imports\DepartmentsImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

class ListDepartments extends ListRecords
{
    protected static string $resource = DepartmentResource::class;

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
                        Excel::import(
                            new DepartmentsImport,
                            $data['file']
                        );

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
