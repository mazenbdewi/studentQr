<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('lecture-session.todays_lectures')"
        :description="$todayLabel"
        icon="heroicon-o-calendar-days"
    >
        <div wire:poll.60s class="space-y-5" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('lecture-session.today_total') }}</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $summary['total'] }}</div>
                </div>

                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 shadow-sm dark:border-emerald-500/20 dark:bg-emerald-500/10">
                    <div class="text-xs font-medium text-emerald-700 dark:text-emerald-300">{{ __('lecture-session.active_now') }}</div>
                    <div class="mt-1 text-2xl font-semibold text-emerald-900 dark:text-emerald-100">{{ $summary['active'] }}</div>
                </div>

                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 shadow-sm dark:border-amber-500/20 dark:bg-amber-500/10">
                    <div class="text-xs font-medium text-amber-700 dark:text-amber-300">{{ __('lecture-session.next_lecture') }}</div>
                    <div class="mt-1 truncate text-sm font-semibold text-amber-950 dark:text-amber-100">
                        {{ $summary['next']['subject'] ?? __('lecture-session.no_lectures_today') }}
                    </div>
                    @if ($summary['next'] ?? null)
                        <div class="mt-1 text-xs text-amber-800 dark:text-amber-200">
                            {{ $summary['next']['timeRange'] }}
                        </div>
                    @endif
                </div>

                <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 shadow-sm dark:border-sky-500/20 dark:bg-sky-500/10">
                    <div class="text-xs font-medium text-sky-700 dark:text-sky-300">{{ __('lecture-session.finished_today') }}</div>
                    <div class="mt-1 text-2xl font-semibold text-sky-900 dark:text-sky-100">{{ $summary['completed'] }}</div>
                </div>
            </div>

            @if ($lectures->isEmpty())
                <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-5 py-8 text-center dark:border-white/10 dark:bg-white/5">
                    <x-filament::icon icon="heroicon-o-calendar" class="mx-auto h-10 w-10 text-gray-400" />
                    <div class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ __('lecture-session.no_lectures_today') }}
                    </div>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($lectures as $lecture)
                        @php
                            $toneClasses = [
                                'active' => 'border-emerald-300 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10',
                                'upcoming' => 'border-amber-300 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10',
                                'completed' => 'border-sky-200 bg-sky-50 dark:border-sky-500/20 dark:bg-sky-500/10',
                                'cancelled' => 'border-rose-200 bg-rose-50 dark:border-rose-500/20 dark:bg-rose-500/10',
                                'neutral' => 'border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900',
                            ][$lecture['statusTone']];

                            $badgeClasses = [
                                'active' => 'bg-emerald-600 text-white',
                                'upcoming' => 'bg-amber-500 text-white',
                                'completed' => 'bg-sky-600 text-white',
                                'cancelled' => 'bg-rose-600 text-white',
                                'neutral' => 'bg-gray-600 text-white',
                            ][$lecture['statusTone']];
                        @endphp

                        <article class="rounded-lg border p-4 shadow-sm {{ $toneClasses }}">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-md px-2.5 py-1 text-xs font-semibold {{ $badgeClasses }}">
                                            {{ $lecture['statusLabel'] }}
                                        </span>

                                        <span class="inline-flex items-center gap-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                            <x-filament::icon icon="heroicon-o-clock" class="h-4 w-4 text-gray-500" />
                                            {{ $lecture['timeRange'] }}
                                        </span>

                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $lecture['startsIn'] }}
                                        </span>
                                    </div>

                                    <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                                        <div class="min-w-0">
                                            <h3 class="truncate text-base font-semibold text-gray-950 dark:text-white">
                                                {{ $lecture['subject'] }}
                                            </h3>

                                            <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-600 dark:text-gray-300">
                                                <span class="inline-flex items-center gap-1">
                                                    <x-filament::icon icon="heroicon-o-map-pin" class="h-4 w-4 text-gray-500" />
                                                    {{ $lecture['hall'] }}
                                                </span>

                                                <span class="inline-flex items-center gap-1">
                                                    <x-filament::icon icon="heroicon-o-academic-cap" class="h-4 w-4 text-gray-500" />
                                                    {{ $lecture['type'] }}
                                                </span>

                                                @if ($lecture['section'])
                                                    <span class="inline-flex items-center gap-1">
                                                        <x-filament::icon icon="heroicon-o-tag" class="h-4 w-4 text-gray-500" />
                                                        {{ $lecture['section'] }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="w-full sm:w-52">
                                            <div class="flex items-center justify-between text-xs font-medium text-gray-600 dark:text-gray-300">
                                                <span>{{ __('lecture-session.actual_attendance') }}</span>
                                                <span>
                                                    {{ $lecture['attendanceCount'] }}
                                                    @if ($lecture['expectedStudents'] > 0)
                                                        / {{ $lecture['expectedStudents'] }}
                                                    @endif
                                                </span>
                                            </div>

                                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/80 ring-1 ring-gray-200 dark:bg-gray-950/60 dark:ring-white/10">
                                                <div
                                                    class="h-full rounded-full bg-primary-600"
                                                    style="width: {{ $lecture['attendancePercent'] ?? 0 }}%"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2 lg:justify-end">
                                    @if ($lecture['qrUrl'])
                                        <a
                                            href="{{ $lecture['qrUrl'] }}"
                                            target="_blank"
                                            class="inline-flex items-center gap-2 rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                                        >
                                            <x-filament::icon icon="heroicon-o-qr-code" class="h-4 w-4" />
                                            {{ __('lecture-session.view_qr') }}
                                        </a>
                                    @endif

                                    <a
                                        href="{{ $lecture['detailsUrl'] }}"
                                        class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-white/10 dark:bg-white/10 dark:text-gray-100 dark:hover:bg-white/15 dark:focus:ring-offset-gray-900"
                                    >
                                        <x-filament::icon icon="heroicon-o-eye" class="h-4 w-4" />
                                        {{ __('lecture-session.view') }}
                                    </a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
