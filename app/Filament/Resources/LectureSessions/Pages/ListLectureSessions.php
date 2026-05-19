<?php

namespace App\Filament\Resources\LectureSessions\Pages;

use App\Filament\Resources\LectureSessions\LectureSessionResource;
use App\Models\AppSetting;
use App\Models\Hall;
use App\Models\Subject;
use App\Services\LectureSessionCalendarService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Filament\Notifications\Notification;

class ListLectureSessions extends ListRecords
{
    protected static string $resource = LectureSessionResource::class;

    public function getTabs(): array
    {
        $now = now();

        return [
            'today' => Tab::make(__('lecture-session.tab_today'))
                ->icon('heroicon-o-calendar-days')
                ->query(fn (Builder $query): Builder => static::applyTodayTabQuery($query, $now)),

            'completed' => Tab::make(__('lecture-session.tab_completed'))
                ->icon('heroicon-o-check-circle')
                ->query(fn (Builder $query): Builder => static::applyCompletedTabQuery($query, $now)),

            'upcoming' => Tab::make(__('lecture-session.tab_upcoming'))
                ->icon('heroicon-o-clock')
                ->query(fn (Builder $query): Builder => static::applyUpcomingTabQuery($query, $now)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
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
                                ->afterStateUpdated(function (callable $set, mixed $state): void {
                                    $set('subject_section_id', null);
                                    $set('lecturer_id', LectureSessionResource::resolveLecturerIdForSubjectAndSection($state, null));
                                })
                                ->required(),

                            Forms\Components\Select::make('subject_section_id')
                                ->label(__('subjects.section_code'))
                                ->options(fn (Get $get): array => LectureSessionResource::getSectionOptionsForSubject($get('subject_id')))
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->disabled(fn (Get $get): bool => blank($get('subject_id')))
                                ->required(fn (Get $get): bool => LectureSessionResource::subjectHasSections($get('subject_id')))
                                ->placeholder(__('subjects.select_subject_first'))
                                ->live()
                                ->afterStateUpdated(fn (callable $set, Get $get, mixed $state): mixed => $set(
                                    'lecturer_id',
                                    LectureSessionResource::resolveLecturerIdForSubjectAndSection($get('subject_id'), $state),
                                )),

                            Forms\Components\Select::make('lecturer_id')
                                ->label(__('lecture-session.lecturer'))
                                ->options(fn (): array => \App\Models\User::query()
                                    ->withoutTrashed()
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->placeholder(__('lecture-session.subject_has_no_lecturer'))
                                ->disabled()
                                ->dehydrated(),

                            Forms\Components\Placeholder::make('missing_lecturer_warning')
                                ->label(__('lecture-session.missing_lecturer_warning_title'))
                                ->content(__('lecture-session.missing_lecturer_warning'))
                                ->visible(fn (Get $get): bool => LectureSessionResource::shouldShowMissingLecturerWarning(
                                    $get('subject_id'),
                                    $get('subject_section_id'),
                                ))
                                ->columnSpanFull()
                                ->extraAttributes([
                                    'class' => 'rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm font-medium text-danger-700 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-300',
                                ]),

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

    protected static function applyTodayTabQuery(Builder $query, Carbon $reference): Builder
    {
        return $query
            ->whereDate('session_date', $reference->toDateString())
            ->orderBy('start_time');
    }

    protected static function applyCompletedTabQuery(Builder $query, Carbon $reference): Builder
    {
        return $query
            ->where(function (Builder $query) use ($reference): void {
                $query
                    ->where('status', 'completed')
                    ->orWhereDate('session_date', '<', $reference->toDateString())
                    ->orWhere(function (Builder $query) use ($reference): void {
                        $query
                            ->whereDate('session_date', $reference->toDateString())
                            ->whereTime('end_time', '<=', $reference->format('H:i:s'));
                    });
            })
            ->orderByDesc('session_date')
            ->orderByDesc('end_time');
    }

    protected static function applyUpcomingTabQuery(Builder $query, Carbon $reference): Builder
    {
        return $query
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where(function (Builder $query) use ($reference): void {
                $query
                    ->whereDate('session_date', '>', $reference->toDateString())
                    ->orWhere(function (Builder $query) use ($reference): void {
                        $query
                            ->whereDate('session_date', $reference->toDateString())
                            ->whereTime('start_time', '>', $reference->format('H:i:s'));
                    });
            })
            ->orderBy('session_date')
            ->orderBy('start_time');
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
