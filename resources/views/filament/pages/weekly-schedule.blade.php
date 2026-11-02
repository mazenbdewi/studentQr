<x-filament-panels::page>
    @if ($warning = $this->currentTermWarning())
        <div class="rounded-lg border border-warning-300 bg-warning-50 p-4 text-warning-900 dark:bg-warning-950 dark:text-warning-100">{{ $warning }}</div>
    @else
        <div class="rounded-lg border border-primary-200 bg-primary-50 p-3 text-sm text-primary-900 dark:bg-primary-950 dark:text-primary-100">{{ __('academic-term.current_label') }}: {{ $this->resolvedAcademicTerm()?->display_name }}</div>
    @endif
    @php($counts = $this->summaryCounts())
    @php($readiness = $this->readinessSummary())

    @php($statusStyles = [
        'complete' => 'border-success-300 bg-success-50 text-success-900 dark:border-success-700 dark:bg-success-950 dark:text-success-100',
        'placeholders' => 'border-warning-300 bg-warning-50 text-warning-900 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-100',
        'blocked' => 'border-danger-300 bg-danger-50 text-danger-900 dark:border-danger-700 dark:bg-danger-950 dark:text-danger-100',
        'no_term' => 'border-warning-300 bg-warning-50 text-warning-900 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-100',
    ])
    <div class="rounded-lg border p-5 {{ $statusStyles[$readiness['status']] ?? $statusStyles['blocked'] }}">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="text-lg font-bold">{{ __('weekly-schedule.readiness.status.'.$readiness['status']) }}</div>
                <div class="mt-1 text-sm opacity-90">
                    {{ __('weekly-schedule.readiness.context', ['term' => $this->resolvedAcademicTerm()?->display_name ?? __('weekly-schedule.not_specified')]) }}
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($readiness['status'] === 'blocked')
                    <a href="{{ $this->scheduleIssuesUrl() }}" class="rounded-md bg-white/80 px-3 py-2 text-sm font-semibold shadow-sm hover:bg-white dark:bg-gray-900/70 dark:hover:bg-gray-900">
                        {{ __('weekly-schedule.readiness.actions.schedule_issues') }}
                    </a>
                @endif
                @if ($reconciliationUrl = $this->reconciliationUrl())
                    <a href="{{ $reconciliationUrl }}" class="rounded-md bg-white/80 px-3 py-2 text-sm font-semibold shadow-sm hover:bg-white dark:bg-gray-900/70 dark:hover:bg-gray-900">
                        {{ __('weekly-schedule.readiness.actions.catalog_review') }}
                    </a>
                @endif
                @if ($readiness['teaching_dates_missing'] || $readiness['teaching_dates_invalid'])
                    <a href="{{ \App\Filament\Pages\AcademicTermManagement::getUrl() }}" class="rounded-md bg-white/80 px-3 py-2 text-sm font-semibold shadow-sm hover:bg-white dark:bg-gray-900/70 dark:hover:bg-gray-900">
                        {{ __('weekly-schedule.readiness.actions.teaching_dates') }}
                    </a>
                @endif
            </div>
        </div>
        <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
            @foreach ([
                'missing_lecturer_slots', 'missing_hall_slots', 'placeholder_lecturer_slots', 'placeholder_hall_slots',
                'real_conflict_slots', 'invalid_day_time_slots', 'missing_subject_rows', 'missing_section_rows',
                'excluded_rows', 'ready_slots', 'total_weekly_slots',
            ] as $key)
                <div class="rounded-md bg-white/60 p-3 dark:bg-gray-900/40">
                    <div class="text-xs opacity-80">{{ __('weekly-schedule.readiness.counters.'.$key) }}</div>
                    <div class="mt-1 text-xl font-bold">{{ $readiness[$key] }}</div>
                </div>
            @endforeach
        </div>
        @if ($readiness['missing_subject_codes'] > 0)
            <div class="mt-3 text-sm opacity-90">
                {{ __('weekly-schedule.readiness.unique_subject_codes', ['count' => $readiness['missing_subject_codes']]) }}
            </div>
        @endif
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
        @foreach (__('weekly-schedule.summary') as $key => $label)
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $counts[$key] ?? 0 }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-3 md:grid-cols-2">
        <div class="rounded-lg border border-success-200 bg-success-50 p-4 dark:border-success-800 dark:bg-success-950">
            <div class="font-semibold text-success-800 dark:text-success-200">{{ __('weekly-schedule.weekly_status_title') }}</div>
            <div class="mt-1 text-sm text-success-700 dark:text-success-300">{{ __('weekly-schedule.weekly_status_imported') }}</div>
        </div>
        <div class="rounded-lg border border-warning-200 bg-warning-50 p-4 dark:border-warning-800 dark:bg-warning-950">
            <div class="font-semibold text-warning-800 dark:text-warning-200">{{ __('weekly-schedule.dated_status_title') }}</div>
            <div class="mt-1 text-sm text-warning-700 dark:text-warning-300">{{ __('weekly-schedule.dated_status_pending') }}</div>
        </div>
    </div>

    <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('weekly-schedule.recurring_explanation') }}</p>

    {{ $this->table }}
</x-filament-panels::page>
