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
use Illuminate\Support\HtmlString;

class ViewSubject extends ViewRecord
{
    protected static string $resource = SubjectResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make(),
            Action::make('download_subject_students_template')
                ->label(__('subjects.download_subject_students_template'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function () {
                    return Excel::download(new \App\Exports\Templates\SubjectStudentsTemplateExport($this->record), $this->record->name . '_students_template.xlsx');
                }),
            Action::make('import_students')
                ->label(__('subjects.import_students'))
                ->modalHeading(__('import-help.modal_title.subject_students'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->modalContent(new HtmlString('<div class="p-4 space-y-3 prose-sm prose max-w-none" dir="rtl"><ul class="ml-6 space-y-2 text-gray-300 list-disc">' . implode('', array_map(fn($i) => '<li class="text-sm">' . htmlspecialchars($i) . '</li>', __('import-help.simple_instructions'))) . '</ul><p class="mt-4 text-xs text-gray-400">' . __('import-help.column_order_note') . '</p></div>'))
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

                        return redirect()->to(static::getResource()::getUrl('view', [
                            'record' => $this->record,
                        ]));

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

