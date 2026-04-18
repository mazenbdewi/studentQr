<?php

namespace App\Filament\Resources\Users\Pages;

use App\Exports\Templates\UsersTemplateExport;
use App\Filament\Resources\Users\UserResource;
use App\Imports\UsersImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_template')
                ->label(__('user.template_download'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(fn () => Excel::download(new UsersTemplateExport(), 'users_template.xlsx')),

            Action::make('import')
                ->label(__('user.import_excel'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->modalHeading(__('user.import_excel'))
                ->modalContent($this->getImportModalContent())
                ->form([
                    FileUpload::make('file')
                        ->label(__('user.excel_file'))
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->maxSize(51200)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $import = new UsersImport();

                    try {
                        Excel::import($import, $data['file']);

                        Notification::make()
                            ->title(__('user.import_success'))
                            ->body(__('import-help.stats.imported', ['count' => $import->getImportedCount()]))
                            ->success()
                            ->send();
                    } catch (ValidationException $e) {
                        $messages = $this->formatValidationMessages($e);

                        Notification::make()
                            ->title(__('user.import_failed'))
                            ->body(implode('<br>', array_slice($messages, 0, 5)))
                            ->danger()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('user.import_failed'))
                            ->body(__('user.import_unexpected_error'))
                            ->danger()
                            ->send();
                    }
                }),

            CreateAction::make(),
        ];
    }

    private function getImportModalContent(): HtmlString
    {
        $roleItems = implode('', array_map(
            fn (string $role): string => '<li class="text-sm">' . e($role) . '</li>',
            UserResource::getImportRoleDescriptions(),
        ));

        $columnItems = implode('', array_map(
            fn (string $item): string => '<li class="text-sm">' . e($item) . '</li>',
            [
                __('user.import_name_help'),
                __('user.import_email_help'),
                __('user.import_password_help_text'),
                __('user.import_role_help_text'),
            ],
        ));

        $noteItems = implode('', array_map(
            fn (string $item): string => '<li class="text-sm">' . e($item) . '</li>',
            [
                __('user.import_password_hashed_note'),
                __('user.import_unique_email_note'),
            ],
        ));

        return new HtmlString(
            '<div class="p-4 space-y-4 prose-sm prose max-w-none" dir="rtl">'
            . '<div><h4 class="mb-2 text-sm font-semibold text-white">' . e(__('import-help.instructions_title')) . '</h4><ul class="ml-6 space-y-2 text-gray-300 list-disc">'
            . implode('', array_map(
                fn ($instruction) => '<li class="text-sm">' . e($instruction) . '</li>',
                __('import-help.simple_instructions'),
            ))
            . '</ul><p class="mt-4 text-xs text-gray-400">' . e(__('import-help.column_order_note')) . '</p></div>'
            . '<div><h4 class="mb-2 text-sm font-semibold text-white">' . e(__('user.accepted_roles')) . '</h4><ul class="ml-6 space-y-2 text-gray-300 list-disc">' . $roleItems . '</ul></div>'
            . '<div><h4 class="mb-2 text-sm font-semibold text-white">' . e(__('user.column_guide')) . '</h4><ul class="ml-6 space-y-2 text-gray-300 list-disc">' . $columnItems . '</ul></div>'
            . '<div><h4 class="mb-2 text-sm font-semibold text-white">' . e(__('user.import_notes')) . '</h4><ul class="ml-6 space-y-2 text-gray-300 list-disc">' . $noteItems . '</ul></div>'
            . '</div>'
        );
    }

    private function formatValidationMessages(ValidationException $exception): array
    {
        $messages = [];

        foreach ($exception->failures() as $failure) {
            $row = $failure->row();
            $values = $failure->values();

            foreach ($failure->errors() as $error) {
                $message = str_replace(':row', (string) $row, $error);

                if (str_contains($message, ':input')) {
                    $attribute = $failure->attribute();
                    $message = str_replace(':input', $values[$attribute] ?? $attribute, $message);
                }

                $messages[] = $message;
            }
        }

        return $messages;
    }
}
