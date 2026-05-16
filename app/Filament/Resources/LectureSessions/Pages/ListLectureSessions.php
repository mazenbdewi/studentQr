<?php

namespace App\Filament\Resources\LectureSessions\Pages;

use App\Filament\Resources\LectureSessions\LectureSessionResource;
use App\Models\AppSetting;
use App\Models\Hall;
use App\Models\Subject;
use App\Services\ActivityLogger;
use App\Services\LectureSessionCalendarService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Carbon;


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
                ->action(function () {
                    app(ActivityLogger::class)->logExport(
                        'lecture_sessions',
                        'lecture_sessions_template_download',
                        'lecture_sessions_template.xlsx'
                    );

                    return Excel::download(new \App\Exports\Templates\LectureSessionsTemplateExport(), 'lecture_sessions_template.xlsx');
                }),

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
    $startedAt = now();
    $fileName = basename((string) $data['file']);
    $import = new \App\Imports\LectureSessionsImport();

    try {
        Excel::import($import, $data['file']);

        app(ActivityLogger::class)->logImportSummary(
            'lecture_sessions',
            'lecture_sessions_import',
            $fileName,
            $import->getImportedCount(),
            $import->getImportedCount(),
            0,
            $startedAt->toIso8601String(),
            now()->toIso8601String()
        );

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

        $failedCount = count($e->failures());

        app(ActivityLogger::class)->logImportSummary(
            'lecture_sessions',
            'lecture_sessions_import',
            $fileName,
            $import->getImportedCount() + $failedCount,
            $import->getImportedCount(),
            $failedCount,
            $startedAt->toIso8601String(),
            now()->toIso8601String(),
            ['status' => 'failed']
        );

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

        app(ActivityLogger::class)->logImportSummary(
            'lecture_sessions',
            'lecture_sessions_import',
            $fileName,
            $import->getImportedCount(),
            $import->getImportedCount(),
            count($messages),
            $startedAt->toIso8601String(),
            now()->toIso8601String(),
            ['status' => 'failed']
        );

        \Filament\Notifications\Notification::make()
            ->title(__('lecture-session.import_failed'))
            ->body(implode('<br>', array_slice($messages, 0, 5)))
            ->danger()
            ->send();

    } catch (\Throwable $e) {
        app(ActivityLogger::class)->logImportSummary(
            'lecture_sessions',
            'lecture_sessions_import',
            $fileName,
            $import->getImportedCount(),
            $import->getImportedCount(),
            1,
            $startedAt->toIso8601String(),
            now()->toIso8601String(),
            ['status' => 'failed', 'error' => $e->getMessage()]
        );

        \Filament\Notifications\Notification::make()
            ->title(__('lecture-session.import_failed'))
            ->body($e->getMessage())
            ->danger()
            ->send();
    }
}),

            Action::make('create_recurring')
                ->label(__('lecture-session.create_recurring'))
                ->icon('heroicon-o-calendar-days')
                ->color('warning')
                ->modalHeading(__('lecture-session.create_recurring_heading'))
                ->modalDescription(__('lecture-session.create_recurring_description'))
                ->modalSubmitActionLabel(__('lecture-session.create_recurring_submit'))
                ->modalWidth('5xl')
                ->slideOver()
                ->form([
                    Section::make(__('lecture-session.recurring_basic_section'))
                        ->description(__('lecture-session.recurring_basic_description'))
                        ->columns(2)
                        ->schema([
                            Forms\Components\Select::make('subject_id')
                                ->label(__('lecture-session.subject'))
                                ->options(fn (): array => LectureSessionResource::scopeSubjectQueryForCurrentUser(Subject::query())
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->live()
                                ->afterStateUpdated(fn (callable $set): mixed => $set('subject_section_id', null))
                                ->required(),

                            Forms\Components\Select::make('subject_section_id')
                                ->label(__('subjects.section_code'))
                                ->options(fn (Get $get): array => LectureSessionResource::getSectionOptionsForSubject($get('subject_id')))
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->disabled(fn (Get $get): bool => blank($get('subject_id')))
                                ->required(fn (Get $get): bool => LectureSessionResource::subjectHasSections($get('subject_id')))
                                ->placeholder(__('subjects.select_subject_first')),

                            Forms\Components\Select::make('hall_id')
                                ->label(__('lecture-session.hall'))
                                ->options(fn (): array => Hall::query()
                                    ->withoutTrashed()
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->required(),
                        ]),

                    Section::make(__('lecture-session.recurring_calendar_section'))
                        ->description(__('lecture-session.recurring_calendar_description'))
                        ->columns(2)
                        ->schema([
                            Forms\Components\DatePicker::make('date_from')
                                ->label(__('lecture-session.date_from'))
                                ->default(now()->toDateString())
                                ->native(false)
                                ->live()
                                ->required(),

                            Forms\Components\DatePicker::make('date_to')
                                ->label(__('lecture-session.date_to'))
                                ->default(now()->addMonth()->toDateString())
                                ->native(false)
                                ->live()
                                ->required(),

                            Forms\Components\ToggleButtons::make('weekdays')
                                ->label(__('lecture-session.weekdays'))
                                ->options(static::weekdayOptions())
                                ->default(now()->dayOfWeek)
                                ->columns([
                                    'sm' => 2,
                                    'md' => 4,
                                    'xl' => 7,
                                ])
                                ->colors([
                                    0 => 'primary',
                                    1 => 'primary',
                                    2 => 'primary',
                                    3 => 'primary',
                                    4 => 'primary',
                                    5 => 'primary',
                                    6 => 'primary',
                                ])
                                ->extraAttributes(['class' => 'lecture-weekday-toggle-buttons'])
                                ->live()
                                ->required()
                                ->helperText(__('lecture-session.recurring_weekdays_help'))
                                ->columnSpanFull(),

                            Forms\Components\Placeholder::make('recurring_preview')
                                ->label(__('lecture-session.recurring_preview'))
                                ->content(fn (Get $get): string => static::recurringPreviewText($get))
                                ->columnSpanFull(),
                        ]),

                    Section::make(__('lecture-session.recurring_options_section'))
                        ->description(__('lecture-session.recurring_options_description'))
                        ->columns(2)
                        ->schema([
                            Forms\Components\TimePicker::make('start_time')
                                ->label(__('lecture-session.start_time'))
                                ->default('08:00')
                                ->seconds(false)
                                ->native(false)
                                ->required(),

                            Forms\Components\TimePicker::make('end_time')
                                ->label(__('lecture-session.end_time'))
                                ->default('09:30')
                                ->seconds(false)
                                ->native(false)
                                ->required()
                                ->helperText(__('lecture-session.recurring_end_time_help')),

                            Forms\Components\TextInput::make('qr_refresh_rate')
                                ->label(__('lecture-session.qr_refresh_rate'))
                                ->numeric()
                                ->minValue(AppSetting::MIN_QR_REFRESH_RATE)
                                ->default(fn (): int => AppSetting::defaultQrRefreshRate())
                                ->required()
                                ->suffix(__('lecture-session.seconds')),

                            Forms\Components\Select::make('status')
                                ->label(__('lecture-session.status'))
                                ->options([
                                    'scheduled' => __('lecture-session.status_scheduled'),
                                    'active' => __('lecture-session.status_active'),
                                    'completed' => __('lecture-session.status_completed'),
                                    'cancelled' => __('lecture-session.status_cancelled'),
                                ])
                                ->default('scheduled')
                                ->native(false)
                                ->required(),

                            Forms\Components\Textarea::make('notes')
                                ->label(__('lecture-session.notes'))
                                ->rows(3)
                                ->nullable()
                                ->columnSpanFull(),
                        ]),
                ])
                ->action(function (array $data): void {
                    $data = LectureSessionResource::ensureSubjectCanBeUsedByCurrentUser($data);
                    $result = app(LectureSessionCalendarService::class)->createRecurring($data);

                    $notification = Notification::make()
                        ->title(__('lecture-session.recurring_created_title'))
                        ->body(__('lecture-session.recurring_created_body', [
                            'created' => $result['created'],
                            'skipped' => $result['skipped'],
                            'total' => $result['total'],
                        ]));

                    $result['created'] > 0
                        ? $notification->success()
                        : $notification->warning();

                    $notification->send();
                }),

            CreateAction::make(),
        ];
    }

    protected static function weekdayOptions(): array
    {
        return [
            0 => __('lecture-session.weekday_sunday'),
            1 => __('lecture-session.weekday_monday'),
            2 => __('lecture-session.weekday_tuesday'),
            3 => __('lecture-session.weekday_wednesday'),
            4 => __('lecture-session.weekday_thursday'),
            5 => __('lecture-session.weekday_friday'),
            6 => __('lecture-session.weekday_saturday'),
        ];
    }

    protected static function recurringPreviewText(Get $get): string
    {
        $count = static::estimateRecurringSessionCount($get);

        if ($count === null) {
            return __('lecture-session.recurring_preview_empty');
        }

        return __('lecture-session.recurring_preview_count', ['count' => $count]);
    }

    protected static function estimateRecurringSessionCount(Get $get): ?int
    {
        $dateFrom = $get('date_from');
        $dateTo = $get('date_to');
        $weekdays = collect($get('weekdays') ?? [])
            ->map(fn (mixed $weekday): int => (int) $weekday)
            ->filter(fn (int $weekday): bool => $weekday >= 0 && $weekday <= 6)
            ->unique()
            ->values()
            ->all();

        if (blank($dateFrom) || blank($dateTo) || $weekdays === []) {
            return null;
        }

        try {
            $from = Carbon::parse($dateFrom)->startOfDay();
            $to = Carbon::parse($dateTo)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        if ($to->lt($from)) {
            return null;
        }

        $count = 0;

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            if (in_array($date->dayOfWeek, $weekdays, true)) {
                $count++;
            }
        }

        return $count;
    }
}
