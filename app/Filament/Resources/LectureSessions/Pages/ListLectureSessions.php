<?php

namespace App\Filament\Resources\LectureSessions\Pages;

use App\Filament\Pages\LecturerAccountPreparation;
use App\Filament\Pages\ScheduleImportReconciliationIndex;
use App\Filament\Resources\LectureSessions\LectureSessionResource;
use App\Models\AcademicTerm;
use App\Models\AppSetting;
use App\Models\Hall;
use App\Models\Subject;
use App\Services\LectureSessionCalendarService;
use App\Services\LectureSessionGenerationService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

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
            Action::make('configure_teaching_period')
                ->label(__('lecture-session.configure_teaching_period'))
                ->icon('heroicon-o-calendar')
                ->color('gray')
                ->visible(fn (): bool => static::canGenerateFromWeeklySchedule())
                ->modalHeading(__('lecture-session.configure_teaching_period_heading'))
                ->modalDescription(__('lecture-session.configure_teaching_period_description'))
                ->modalSubmitActionLabel(__('lecture-session.configure_teaching_period_submit'))
                ->form([
                    Forms\Components\Select::make('academic_term_id')
                        ->label(__('lecture-session.academic_term'))
                        ->options(fn (): array => AcademicTerm::query()
                            ->orderByDesc('id')
                            ->pluck('display_name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function (callable $set, mixed $state): void {
                            $term = $state ? AcademicTerm::query()->find($state) : null;

                            $set('teaching_start_date', $term?->teaching_start_date?->toDateString());
                            $set('teaching_end_date', $term?->teaching_end_date?->toDateString());
                        })
                        ->required(),

                    Forms\Components\DatePicker::make('teaching_start_date')
                        ->label(__('lecture-session.teaching_start_date'))
                        ->native(false)
                        ->required(),

                    Forms\Components\DatePicker::make('teaching_end_date')
                        ->label(__('lecture-session.teaching_end_date'))
                        ->native(false)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    AcademicTerm::query()
                        ->findOrFail($data['academic_term_id'])
                        ->update([
                            'teaching_start_date' => $data['teaching_start_date'],
                            'teaching_end_date' => $data['teaching_end_date'],
                        ]);

                    Notification::make()
                        ->title(__('lecture-session.teaching_period_saved_title'))
                        ->success()
                        ->send();
                }),

            Action::make('open_lecturer_account_preparation')
                ->label(__('lecture-session.open_lecturer_account_preparation'))
                ->icon('heroicon-o-user-group')
                ->color('gray')
                ->visible(fn (): bool => static::canGenerateFromWeeklySchedule())
                ->url(fn (): string => LecturerAccountPreparation::getUrl()),

            Action::make('open_weekly_schedule_reconciliation')
                ->label(__('lecture-session.open_weekly_schedule_reconciliation'))
                ->icon('heroicon-o-clipboard-document-check')
                ->color('gray')
                ->visible(fn (): bool => static::canGenerateFromWeeklySchedule())
                ->url(fn (): string => ScheduleImportReconciliationIndex::getUrl()),

            Action::make('generate_from_weekly_schedule')
                ->label(__('lecture-session.generate_from_weekly_schedule'))
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->visible(fn (): bool => static::canGenerateFromWeeklySchedule())
                ->modalHeading(__('lecture-session.generate_from_weekly_schedule_heading'))
                ->modalDescription(__('lecture-session.generate_from_weekly_schedule_description'))
                ->modalSubmitActionLabel(__('lecture-session.generate_from_weekly_schedule_submit'))
                ->modalWidth('4xl')
                ->requiresConfirmation()
                ->form([
                    Forms\Components\Select::make('academic_term_id')
                        ->label(__('lecture-session.academic_term'))
                        ->options(fn (): array => AcademicTerm::query()
                            ->orderByDesc('id')
                            ->pluck('display_name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->live()
                        ->required(),

                    Forms\Components\Placeholder::make('weekly_generation_preview')
                        ->label(__('lecture-session.weekly_generation_preview'))
                        ->content(fn (Get $get): string => static::weeklyGenerationPreviewText($get))
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $term = AcademicTerm::query()->findOrFail($data['academic_term_id']);
                    $generator = app(LectureSessionGenerationService::class);
                    $preview = $generator->preview($term);

                    if (! $preview['ready']) {
                        Notification::make()
                            ->title(__('lecture-session.weekly_generation_not_ready_title'))
                            ->body(static::weeklyGenerationPreviewTextForResult($preview))
                            ->danger()
                            ->send();

                        return;
                    }

                    $result = $generator->generate($term, auth()->user());

                    Notification::make()
                        ->title(__('lecture-session.weekly_generation_completed_title'))
                        ->body(__('lecture-session.weekly_generation_completed_body', [
                            'created' => $result['created_session_count'],
                            'skipped' => $result['skipped_session_count'],
                            'total' => $result['candidate_session_count'],
                        ]))
                        ->success()
                        ->send();
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

    protected static function canGenerateFromWeeklySchedule(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['super-admin', 'admin']);
    }

    protected static function weeklyGenerationPreviewText(Get $get): string
    {
        $termId = $get('academic_term_id');

        if (blank($termId)) {
            return __('lecture-session.weekly_generation_preview_empty');
        }

        $term = AcademicTerm::query()->find($termId);

        if (! $term) {
            return __('lecture-session.weekly_generation_preview_empty');
        }

        return static::weeklyGenerationPreviewTextForResult(
            app(LectureSessionGenerationService::class)->preview($term),
        );
    }

    protected static function weeklyGenerationPreviewTextForResult(array $preview): string
    {
        $summary = __('lecture-session.weekly_generation_preview_summary', [
            'source' => $preview['source_slot_count'],
            'total' => $preview['candidate_session_count'],
            'create' => $preview['to_create_count'],
            'existing' => $preview['already_existing_count'] + $preview['manual_existing_count'],
            'blocked' => $preview['blocked_slot_count'],
            'conflicts' => $preview['conflict_count'],
        ]);
        $structural = $preview['structural_readiness'] ?? [];

        if ($structural !== []) {
            $summary .= "\n".__('lecture-session.weekly_generation_structural_readiness', [
                'valid' => $structural['valid_subject_and_section'] ?? 0,
                'with_lecturer' => $structural['slots_with_lecturer_identity'] ?? 0,
                'without_lecturer' => $structural['slots_without_lecturer_identity'] ?? 0,
                'with_login' => $structural['slots_with_valid_linked_lecturer_account_and_role'] ?? 0,
                'with_halls' => $structural['slots_with_halls'] ?? 0,
                'without_halls' => $structural['slots_without_halls'] ?? 0,
                'ready' => $structural['ready_slots'] ?? 0,
                'blocked' => $structural['blocked_slots'] ?? 0,
            ]);
        }

        if ($preview['ready']) {
            return $summary."\n".__('lecture-session.weekly_generation_preview_ready');
        }

        $issues = collect($preview['prerequisite_errors'])
            ->merge(collect($preview['blocked_slots'])->flatMap(fn (array $slot): array => $slot['reasons'] ?? []))
            ->merge(collect($preview['conflicts'])->pluck('reason')->filter())
            ->unique()
            ->values()
            ->implode(', ');

        return $summary."\n".__('lecture-session.weekly_generation_preview_blocked', [
            'issues' => $issues !== '' ? $issues : __('lecture-session.not_available'),
        ]);
    }
}
