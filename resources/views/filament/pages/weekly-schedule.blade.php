<x-filament-panels::page>
    @php($counts = $this->summaryCounts())

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
