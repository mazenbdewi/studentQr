<?php

namespace App\Filament\Resources\LectureSessions\Pages;

use App\Filament\Pages\AcademicTermManagement;
use App\Filament\Pages\ScheduleImportIssues;
use App\Filament\Resources\LectureSessions\LectureSessionResource;
use App\Models\AcademicTerm;
use App\Models\AppSetting;
use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\Subject;
use App\Services\LectureSessionCalendarService;
use App\Services\LectureSessionGenerationService;
use App\Services\WeeklyScheduleIssueService;
use App\Support\AcademicTermContext;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Alignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;

class ListLectureSessions extends ListRecords
{
    protected static string $resource = LectureSessionResource::class;

    public ?string $lastRefreshedAt = null;

    /** @var list<string> */
    private const CURRENT_TERM_ACTIONS = [
        'generate_from_weekly_schedule',
        'open_weekly_schedule_reconciliation',
    ];

    public function mount(): void
    {
        parent::mount();
        $this->lastRefreshedAt = now()->format('H:i');

        if (static::currentAcademicTermIsMissing()) {
            $this->mountAction('missingAcademicTerm');
        }
    }

    public function hydrate(): void
    {
        // Table polling and every explicit Livewire update use the application
        // timezone and retain all table state held by Livewire.
        $this->lastRefreshedAt = now()->format('H:i');
    }

    public function refreshLectures(): void
    {
        $this->flushCachedTableRecords();
        $this->lastRefreshedAt = now()->format('H:i');

        Notification::make()
            ->title('تم تحديث بيانات المحاضرات.')
            ->success()
            ->send();
    }

    /**
     * Reopens the explanatory modal instead of mounting a term-dependent action.
     *
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $context
     */
    public function mountAction(string $name, array $arguments = [], array $context = []): mixed
    {
        if (in_array($name, self::CURRENT_TERM_ACTIONS, true) && static::currentAcademicTermIsMissing()) {
            return parent::mountAction('missingAcademicTerm');
        }

        return parent::mountAction($name, $arguments, $context);
    }

    public function missingAcademicTermAction(): Action
    {
        return Action::make('missingAcademicTerm')
            ->modalHeading('لم يتم تحديد الفصل الدراسي الحالي')
            ->modalDescription('يجب تحديد الفصل الدراسي الحالي قبل استخدام عمليات الجلسات والمحاضرات.\n\nيرجى الانتقال إلى إدارة الفصل الدراسي الحالي واختيار الفصل المطلوب، ثم العودة لإكمال العملية.')
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->modalWidth('lg')
            ->modalAlignment(Alignment::Center)
            ->requiresConfirmation()
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->modalCloseButton(true)
            ->modalSubmitAction(function (Action $action): Action {
                return $action
                    ->label('تحديد الفصل الدراسي الحالي')
                    ->url(AcademicTermManagement::getUrl());
            })
            ->modalCancelActionLabel('إغلاق')
            ->extraModalWindowAttributes([
                'dir' => 'rtl',
                'class' => 'text-right',
            ])
            ->action(static function (): void {});
    }

    public function getTabs(): array
    {
        $now = now();

        return [
            'today' => Tab::make(__('lecture-session.tab_today'))
                ->icon('heroicon-o-calendar-days')
                ->badge(fn (): int => static::todayTabCount($now))
                ->query(fn (Builder $query): Builder => static::applyTodayTabQuery($query, $now)),

            'today_ended' => Tab::make('محاضرات اليوم المنتهية')
                ->icon('heroicon-o-clock')
                ->badge(fn (): int => static::todayEndedTabCount($now))
                ->query(fn (Builder $query): Builder => static::applyTodayEndedTabQuery($query, $now)),

            'completed' => Tab::make(__('lecture-session.tab_completed'))
                ->icon('heroicon-o-check-circle')
                ->badge(fn (): int => static::completedTabCount($now))
                ->query(fn (Builder $query): Builder => static::applyCompletedTabQuery($query, $now)),

            'upcoming' => Tab::make(__('lecture-session.tab_upcoming'))
                ->icon('heroicon-o-clock')
                ->badge(fn (): int => static::upcomingTabCount($now))
                ->query(fn (Builder $query): Builder => static::applyUpcomingTabQuery($query, $now)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_from_weekly_schedule')
                ->label('توليد الجلسات من البرنامج الأسبوعي')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->visible(fn (): bool => static::canGenerateFromWeeklySchedule())
                ->modalHeading(__('lecture-session.generate_from_weekly_schedule_heading'))
                ->modalDescription(__('lecture-session.generate_from_weekly_schedule_description'))
                ->modalSubmitActionLabel(__('lecture-session.generate_from_weekly_schedule_submit'))
                ->modalWidth('4xl')
                ->requiresConfirmation()
                ->closeModalByClickingAway(false)
                ->closeModalByEscaping(false)
                ->modalCloseButton(false)
                ->extraModalWindowAttributes(['class' => 'relative'])
                ->modalSubmitAction(function (Action $action): Action {
                    return $action
                        ->label(new HtmlString('<span wire:loading.remove wire:target="callMountedAction">توليد الجلسات الجاهزة</span><span wire:loading wire:target="callMountedAction">جارٍ توليد الجلسات...</span>'))
                        ->extraAttributes([
                            'wire:loading.attr' => 'disabled',
                            'wire:target' => 'callMountedAction',
                        ]);
                })
                ->modalCancelAction(function (Action $action): Action {
                    return $action->extraAttributes([
                        'wire:loading.attr' => 'disabled',
                        'wire:target' => 'callMountedAction',
                    ]);
                })
                ->fillForm(function (): array {
                    $currentTerm = app(AcademicTermContext::class)->currentOrNull();

                    return ['academic_term_id' => $currentTerm?->id];
                })
                ->form([
                    Forms\Components\Hidden::make('academic_term_id')
                        ->required(),

                    Forms\Components\Placeholder::make('current_academic_term')
                        ->label(__('lecture-session.lecture_generation.academic_term'))
                        ->content(function (): string {
                            $term = app(AcademicTermContext::class)->currentOrNull();

                            return $term instanceof AcademicTerm
                                ? $term->display_name
                                : static::currentAcademicTermMissingMessage();
                        })
                        ->badge(),

                    Forms\Components\Placeholder::make('weekly_generation_preview')
                        ->label(__('lecture-session.lecture_generation.preview_title'))
                        ->content(fn (Get $get): HtmlString => static::weeklyGenerationPreviewHtml($get))
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make('weekly_generation_loading')
                        ->hiddenLabel()
                        ->content(fn (): HtmlString => new HtmlString(view('filament.components.lecture-session-generation-loading', [
                            'readySessionCount' => static::weeklyGenerationReadySessionCount(),
                        ])->render()))
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $term = app(AcademicTermContext::class)->currentOrNull();

                    if (! $term instanceof AcademicTerm) {
                        Notification::make()
                            ->title(static::currentAcademicTermMissingMessage())
                            ->warning()
                            ->send();

                        return;
                    }
                    $lock = Cache::lock('lecture-session-generation:'.$term->id, 600);

                    if (! $lock->get()) {
                        Notification::make()
                            ->title('توجد عملية توليد جلسات قيد التنفيذ حاليًا. يرجى الانتظار حتى اكتمالها.')
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        $generator = app(LectureSessionGenerationService::class);
                        $result = $generator->generateReadySessions($term, auth()->user());

                        Notification::make()
                            ->title('تم توليد جلسات المحاضرات بنجاح.')
                            ->body(implode("\n", [
                                '• الجلسات الجديدة: '.$result['created_session_count'],
                                '• الجلسات الموجودة مسبقًا: '.($result['already_existing_count'] + $result['manual_existing_count']),
                                '• الحالات التي تحتاج مراجعة: '.$result['conflict_count'],
                            ]))
                            ->success()
                            ->send();

                        $this->resetTable();
                    } catch (\Throwable) {
                        Notification::make()
                            ->title('تعذر إكمال توليد جلسات المحاضرات. لم يتم تنفيذ طلب آخر تلقائيًا. يرجى مراجعة سجل العملية ثم المحاولة مجددًا.')
                            ->danger()
                            ->send();
                    } finally {
                        $lock->release();
                    }
                }),

            ActionGroup::make([
                CreateAction::make()
                    ->label(__('lecture-session.create_manual'))
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->visible(fn (): bool => LectureSessionResource::canCreate()),

                static::recurringHeaderAction(),
            ])
                ->label('إضافة')
                ->icon('heroicon-o-plus')
                ->button()
                ->visible(fn (): bool => LectureSessionResource::canCreate()),

            Action::make('open_weekly_schedule_reconciliation')
                ->label('مراجعة استيراد البرنامج الأسبوعي')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('gray')
                ->visible(fn (): bool => static::canGenerateFromWeeklySchedule())
                ->disabled(fn (): bool => ! static::currentAcademicTermIsMissing() && static::currentWeeklyScheduleBatch() === null)
                ->tooltip(fn (): ?string => static::currentAcademicTermIsMissing()
                    ? null
                    : (static::currentWeeklyScheduleBatch() === null ? 'لا توجد عملية استيراد برنامج أسبوعي للفصل الدراسي الحالي.' : null))
                ->url(function (): ?string {
                    $batch = static::currentWeeklyScheduleBatch();
                    $term = app(AcademicTermContext::class)->currentOrNull();

                    return $batch instanceof ImportBatch && $term instanceof AcademicTerm
                        ? ScheduleImportIssues::getUrl(['batch' => $batch->id, 'term' => $term->id])
                        : null;
                }),

            Action::make('refreshLectures')
                ->label(new HtmlString('<span wire:loading.remove wire:target="refreshLectures">تحديث المحاضرات</span><span wire:loading wire:target="refreshLectures">جارٍ التحديث...</span>'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->extraAttributes([
                    'wire:loading.attr' => 'disabled',
                    'wire:target' => 'refreshLectures',
                ])
                ->action(fn () => $this->refreshLectures()),

            Action::make('lastRefreshedAt')
                ->label(fn (): string => 'آخر تحديث: '.($this->lastRefreshedAt ?? now()->format('H:i')))
                ->color('gray')
                ->disabled(),

        ];
    }

    protected static function recurringHeaderAction(): Action
    {
        return Action::make('create_recurring')
            ->label(__('lecture-session.create_recurring'))
            ->icon('heroicon-o-calendar-days')
            ->color('warning')
            ->visible(fn (): bool => LectureSessionResource::canCreate())
            ->modalHeading(__('lecture-session.create_recurring_heading'))
            ->modalDescription(__('lecture-session.create_recurring_description'))
            ->modalSubmitActionLabel(__('lecture-session.create_recurring_submit'))
            ->modalWidth('7xl')
            ->slideOver()
            ->form([
                Section::make(__('lecture-session.recurring_basic_section'))
                    ->description(__('lecture-session.recurring_basic_description'))
                    ->columns(2)
                    ->schema([
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
                            ->afterStateUpdated(function (callable $set): void {
                                $set('subject_section_id', null);
                                $set('lecturer_id', null);
                            })
                            ->required(),

                        Forms\Components\Select::make('subject_id')
                            ->label(__('lecture-session.subject'))
                            ->options(fn (): array => LectureSessionResource::scopeSubjectQueryForCurrentUser(Subject::query())
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (callable $set, Get $get, mixed $state): void {
                                $set('subject_section_id', null);
                                $set('lecturer_id', LectureSessionResource::resolveLecturerIdForSubjectAndSection(
                                    $state,
                                    null,
                                    $get('academic_term_id'),
                                ));
                            })
                            ->required(),

                        Forms\Components\Select::make('subject_section_id')
                            ->label(__('subjects.section_code'))
                            ->options(fn (Get $get): array => LectureSessionResource::getSectionOptionsForSubject(
                                $get('subject_id'),
                                $get('academic_term_id'),
                            ))
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->disabled(fn (Get $get): bool => blank($get('subject_id')))
                            ->required(fn (Get $get): bool => LectureSessionResource::subjectHasSections(
                                $get('subject_id'),
                                $get('academic_term_id'),
                            ))
                            ->placeholder(__('subjects.select_subject_first'))
                            ->live()
                            ->afterStateUpdated(fn (callable $set, Get $get, mixed $state): mixed => $set(
                                'lecturer_id',
                                LectureSessionResource::resolveLecturerIdForSubjectAndSection(
                                    $get('subject_id'),
                                    $state,
                                    $get('academic_term_id'),
                                ),
                            )),

                        Forms\Components\Select::make('lecturer_id')
                            ->label(__('lecture-session.lecturer'))
                            ->options(fn (Get $get): array => LectureSessionResource::manualLecturerOptions(
                                $get('academic_term_id'),
                                $get('subject_id'),
                                $get('subject_section_id'),
                            ))
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder(__('lecture-session.subject_has_no_lecturer'))
                            ->disabled(fn (): bool => auth()->user()?->hasRole('course_lecturer') === true)
                            ->required()
                            ->dehydrated(),

                        Forms\Components\Select::make('hall_id')
                            ->label(__('lecture-session.hall'))
                            ->options(fn (): array => Hall::query()
                                ->withoutTrashed()
                                ->where('is_active', true)
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required(),

                        Forms\Components\Placeholder::make('missing_lecturer_warning')
                            ->label(__('lecture-session.missing_lecturer_warning_title'))
                            ->content(__('lecture-session.missing_lecturer_warning'))
                            ->visible(fn (Get $get): bool => LectureSessionResource::shouldShowMissingLecturerWarning(
                                $get('subject_id'),
                                $get('subject_section_id'),
                                $get('academic_term_id'),
                            ))
                            ->columnSpanFull()
                            ->extraAttributes([
                                'class' => 'rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm font-medium text-danger-700 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-300',
                            ]),
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
                            ->multiple()
                            ->default([now()->dayOfWeek])
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

                        Forms\Components\TextInput::make('qr_refresh_rate')
                            ->label(__('lecture-session.qr_refresh_rate'))
                            ->numeric()
                            ->minValue(AppSetting::MIN_QR_REFRESH_RATE)
                            ->default(fn (): int => AppSetting::defaultQrRefreshRate())
                            ->required()
                            ->suffix(__('lecture-session.seconds')),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('lecture-session.notes'))
                            ->rows(3)
                            ->nullable()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('lecture-session.recurring_preview'))
                    ->schema([
                        Forms\Components\Placeholder::make('recurring_preview')
                            ->hiddenLabel()
                            ->content(fn (Get $get): string|HtmlString => static::recurringPreviewTable($get))
                            ->columnSpanFull(),
                    ]),
            ])
            ->action(function (array $data): void {
                static::ensureRecurringSubjectCanBeUsedByCurrentUser($data);

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
            });
    }

    protected static function applyTodayTabQuery(Builder $query, Carbon $reference): Builder
    {
        return $query
            ->whereDate('session_date', $reference->toDateString())
            ->whereTime('end_time', '>', $reference->format('H:i:s'))
            ->orderBy('start_time');
    }

    protected static function applyTodayEndedTabQuery(Builder $query, Carbon $reference): Builder
    {
        return $query
            ->whereDate('session_date', $reference->toDateString())
            ->whereTime('end_time', '<=', $reference->format('H:i:s'))
            ->orderByDesc('end_time');
    }

    protected static function todayTabCount(Carbon $reference): int
    {
        return static::applyTodayTabQuery(LectureSessionResource::getEloquentQuery(), $reference)->count();
    }

    protected static function todayEndedTabCount(Carbon $reference): int
    {
        return static::applyTodayEndedTabQuery(LectureSessionResource::getEloquentQuery(), $reference)->count();
    }

    protected static function completedTabCount(Carbon $reference): int
    {
        return static::applyCompletedTabQuery(LectureSessionResource::getEloquentQuery(), $reference)->count();
    }

    protected static function upcomingTabCount(Carbon $reference): int
    {
        return static::applyUpcomingTabQuery(LectureSessionResource::getEloquentQuery(), $reference)->count();
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

    protected static function recurringPreviewTable(Get $get): string|HtmlString
    {
        $data = [
            'academic_term_id' => $get('academic_term_id'),
            'subject_id' => $get('subject_id'),
            'subject_section_id' => $get('subject_section_id'),
            'lecturer_id' => $get('lecturer_id'),
            'hall_id' => $get('hall_id'),
            'date_from' => $get('date_from'),
            'date_to' => $get('date_to'),
            'weekdays' => $get('weekdays'),
            'start_time' => $get('start_time'),
            'end_time' => $get('end_time'),
            'status' => $get('status') ?? 'scheduled',
            'qr_refresh_rate' => $get('qr_refresh_rate') ?? AppSetting::defaultQrRefreshRate(),
            'notes' => $get('notes'),
        ];

        if (blank($data['academic_term_id'])
            || blank($data['subject_id'])
            || blank($data['hall_id'])
            || blank($data['date_from'])
            || blank($data['date_to'])
            || blank($data['weekdays'])
            || blank($data['start_time'])
            || blank($data['end_time'])) {
            return __('lecture-session.recurring_preview_empty');
        }

        try {
            static::ensureRecurringSubjectCanBeUsedByCurrentUser($data);
            $preview = app(LectureSessionCalendarService::class)->previewRecurring($data);
        } catch (\Throwable $exception) {
            if ($exception instanceof \Illuminate\Validation\ValidationException) {
                return collect($exception->errors())->flatten()->first() ?? __('lecture-session.recurring_preview_empty');
            }

            return __('lecture-session.recurring_preview_empty');
        }

        $weekdayOptions = static::weekdayOptions();
        $resultLabels = static::recurringResultLabels();
        $rows = collect($preview['rows'])
            ->map(function (array $row) use ($weekdayOptions, $resultLabels): string {
                $cells = [
                    e($row['date']),
                    e($weekdayOptions[$row['weekday']] ?? $row['weekday']),
                    e(substr((string) $row['start_time'], 0, 5)),
                    e(substr((string) $row['end_time'], 0, 5)),
                    e($row['subject']),
                    e($row['section'] ?? __('lecture-session.not_available')),
                    e($row['lecturer']),
                    e($row['hall']),
                    e($resultLabels[$row['result']] ?? $row['result']),
                ];

                return '<tr><td>'.implode('</td><td>', $cells).'</td></tr>';
            })
            ->implode('');

        $headers = collect([
            __('lecture-session.preview_date'),
            __('lecture-session.preview_weekday'),
            __('lecture-session.start_time'),
            __('lecture-session.end_time'),
            __('lecture-session.subject'),
            __('subjects.section_code'),
            __('lecture-session.lecturer'),
            __('lecture-session.hall'),
            __('lecture-session.preview_result'),
        ])->map(fn (string $header): string => '<th>'.e($header).'</th>')->implode('');

        $summary = __('lecture-session.recurring_preview_summary', [
            'total' => $preview['total_count'],
            'existing' => $preview['existing_count'],
            'conflicts' => $preview['conflict_count'],
            'ready' => $preview['ready_count'],
            'skipped' => $preview['skipped_count'],
        ]);

        return new HtmlString(
            '<div dir="rtl" class="space-y-3">'
            .'<p class="text-sm font-medium">'.e($summary).'</p>'
            .'<div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">'
            .'<table class="min-w-full divide-y divide-gray-200 text-right text-sm dark:divide-gray-700">'
            .'<thead class="bg-gray-50 dark:bg-gray-800"><tr>'.$headers.'</tr></thead>'
            .'<tbody class="divide-y divide-gray-100 dark:divide-gray-800">'.$rows.'</tbody>'
            .'</table></div></div>',
        );
    }

    protected static function recurringResultLabels(): array
    {
        return [
            'ready' => __('lecture-session.recurring_result_ready'),
            'existing' => __('lecture-session.recurring_result_existing'),
            'lecturer_conflict' => __('lecture-session.recurring_result_lecturer_conflict'),
            'hall_conflict' => __('lecture-session.recurring_result_hall_conflict'),
            'section_conflict' => __('lecture-session.recurring_result_section_conflict'),
            'outside_teaching_period' => __('lecture-session.recurring_result_outside_teaching_period'),
        ];
    }

    protected static function ensureRecurringSubjectCanBeUsedByCurrentUser(array $data): void
    {
        $subjectId = $data['subject_id'] ?? null;

        if (blank($subjectId)) {
            return;
        }

        $isAllowed = LectureSessionResource::scopeSubjectQueryForCurrentUser(Subject::query())
            ->whereKey($subjectId)
            ->exists();

        if (! $isAllowed) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'subject_id' => __('lecture-session.subject_not_assigned_to_lecturer'),
            ]);
        }
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

    protected static function currentAcademicTermIsMissing(): bool
    {
        return ! app(AcademicTermContext::class)->currentOrNull() instanceof AcademicTerm;
    }

    protected static function currentAcademicTermMissingMessage(): string
    {
        return 'لا يوجد فصل دراسي حالي محدد. يرجى تعيين الفصل الدراسي الحالي من إدارة الفصل الدراسي الحالي.';
    }

    protected static function currentWeeklyScheduleBatch(): ?ImportBatch
    {
        $term = app(AcademicTermContext::class)->currentOrNull();

        if (! $term instanceof AcademicTerm) {
            return null;
        }

        $batches = ImportBatch::query()
            ->where('import_type', ImportBatch::TYPE_WEEKLY_SCHEDULE)
            ->whereIn('status', [ImportBatch::STATUS_COMPLETED, ImportBatch::STATUS_COMPLETED_WITH_ERRORS])
            ->whereHas('academicTerms', fn (Builder $query): Builder => $query->whereKey($term->id))
            ->whereHas('scheduleSlots', fn (Builder $query): Builder => $query->where('academic_term_id', $term->id))
            ->get();

        return $batches->count() === 1 ? $batches->first() : null;
    }

    protected static function weeklyGenerationPreviewHtml(Get $get): HtmlString
    {
        $termId = $get('academic_term_id');

        if (blank($termId)) {
            return new HtmlString(view('filament.components.lecture-session-generation-preview', [
                'preview' => null,
                'issues' => [],
            ])->render());
        }

        $term = AcademicTerm::query()->find($termId);

        if (! $term) {
            return new HtmlString(view('filament.components.lecture-session-generation-preview', [
                'preview' => null,
                'issues' => [],
            ])->render());
        }

        $issueResult = app(WeeklyScheduleIssueService::class)->result($term);
        $preview = $issueResult->preview;

        return new HtmlString(view('filament.components.lecture-session-generation-preview', [
            'preview' => $preview,
            'issues' => static::weeklyGenerationIssues($preview),
            'issuesUrl' => static::weeklyScheduleIssuesUrl($term, $issueResult->importBatchId),
        ])->render());
    }

    protected static function weeklyGenerationReadySessionCount(): int
    {
        $term = app(AcademicTermContext::class)->currentOrNull();

        if (! $term instanceof AcademicTerm) {
            return 0;
        }

        return (int) app(WeeklyScheduleIssueService::class)->result($term)->preview['to_create_count'];
    }

    /** @return array<int, array{code: string, label: string, count: int}> */
    protected static function weeklyGenerationIssues(array $preview): array
    {
        $counts = collect($preview['prerequisite_errors'] ?? [])
            ->merge(collect($preview['blocked_slots'])->flatMap(fn (array $slot): array => $slot['reasons'] ?? []))
            ->merge(collect($preview['conflicts'])->pluck('reason')->filter())
            ->reject(fn (string $reason): bool => in_array($reason, ['missing_lecturer_identity', 'missing_hall'], true))
            ->countBy();

        return $counts
            ->map(fn (int $count, string $reason): array => [
                'code' => $reason,
                'label' => static::weeklyGenerationReasonLabel($reason),
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    protected static function weeklyGenerationReasonLabel(string $reason): string
    {
        $key = 'lecture-session.lecture_generation.reasons.'.$reason;
        $label = __($key);

        return $label === $key
            ? __('lecture-session.lecture_generation.reasons.unknown')
            : $label;
    }

    protected static function weeklyScheduleIssuesUrl(AcademicTerm $term, ?int $importBatchId): string
    {
        return ScheduleImportIssues::getUrl([
            'term' => $term->id,
            'batch' => $importBatchId,
        ]);
    }
}
