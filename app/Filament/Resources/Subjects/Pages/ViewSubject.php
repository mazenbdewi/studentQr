<?php


namespace App\Filament\Resources\Subjects\Pages;

use App\Filament\Resources\Subjects\SubjectResource;
use App\Imports\SubjectStudentsImport;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Maatwebsite\Excel\Facades\Excel;


class ViewSubject extends ViewRecord
{
    protected static string $resource = SubjectResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make(),
            Action::make('import_students')
                ->label(__('subjects.import_students'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    FileUpload::make('file')
                        ->label(__('subjects.excel_file'))
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel'
                        ])
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        Excel::import(
                            new SubjectStudentsImport(
                                $this->record->id,
                                null,
                                null
                            ),
                            $data['file']
                        );

                        Notification::make()
                            ->title(__('subjects.import_success'))
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title(__('subjects.import_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}


 
