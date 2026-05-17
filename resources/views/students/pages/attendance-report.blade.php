<x-filament-panels::page>
    @php
        $summary = $this->getSummary();
        $percentage = (float) $summary['attendance_percentage'];
        $percentageTone = match (true) {
            $percentage >= 75 => [
                'text' => 'text-green-700 dark:text-green-300',
                'badge' => 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-400/30',
                'bar' => 'bg-green-500',
            ],
            $percentage >= 50 => [
                'text' => 'text-amber-700 dark:text-amber-300',
                'badge' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/30',
                'bar' => 'bg-amber-500',
            ],
            default => [
                'text' => 'text-red-700 dark:text-red-300',
                'badge' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-400/30',
                'bar' => 'bg-red-500',
            ],
        };
        $subjectLabels = $this->getReportSubjectLabels();
        $registrationDateEntries = $this->getRegistrationDateEntries();
        $notAvailable = __('lecture-session.not_available');
    @endphp

    <div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-4 border-b border-gray-200 bg-gray-50 px-5 py-5 dark:border-white/10 dark:bg-white/5 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 text-sm font-medium text-primary-600 dark:text-primary-400">
                        <x-filament::icon icon="heroicon-o-chart-bar" class="h-5 w-5 shrink-0" />
                        <span>{{ __('student.attendance_report') }}</span>
                    </div>
                    <h2 class="mt-2 max-w-full break-words text-2xl font-semibold leading-tight text-gray-950 dark:text-white">
                        {{ $this->record->name }}
                    </h2>
                </div>

                <div class="flex shrink-0 flex-col gap-2 sm:flex-row sm:items-center">
                    <x-filament::button
                        color="danger"
                        icon="heroicon-o-document-arrow-down"
                        wire:click="exportPdf"
                        wire:loading.attr="disabled"
                        wire:target="exportPdf"
                    >
                        {{ __('student.export_pdf') }}
                    </x-filament::button>
                </div>
            </div>

            <div class="grid gap-4 px-5 py-5 sm:grid-cols-2 xl:grid-cols-4">
                <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-950/40">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('student.student_name') }}</p>
                    <p class="mt-1 break-words text-base font-semibold text-gray-950 dark:text-white">{{ $this->record->name }}</p>
                </div>

                <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-950/40">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('student.student_number') }}</p>
                    <p class="mt-1 break-words text-base font-semibold text-gray-950 dark:text-white" dir="ltr">{{ $this->record->student_number ?: $notAvailable }}</p>
                </div>

                <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-950/40">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('student.faculty_id') }}</p>
                    <p class="mt-1 break-words text-base font-semibold text-gray-950 dark:text-white">{{ $this->record->faculty?->name ?? $notAvailable }}</p>
                </div>

                <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-950/40">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('student.department_id') }}</p>
                    <p class="mt-1 break-words text-base font-semibold text-gray-950 dark:text-white">{{ $this->record->department?->name ?? $notAvailable }}</p>
                </div>

                @if($this->record->phone)
                    <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-950/40">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('student.phone') }}</p>
                        <p class="mt-1 break-words text-base font-semibold text-gray-950 dark:text-white" dir="ltr">{{ $this->record->phone }}</p>
                    </div>
                @endif

                <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-950/40 sm:col-span-2 {{ $this->record->phone ? 'xl:col-span-3' : 'xl:col-span-4' }}">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('lecture-session.subject') }}</p>
                            <p class="mt-1 text-base font-semibold text-gray-950 dark:text-white">{{ $this->getSelectedSubjectLabel() }}</p>
                        </div>

                        @if(count($subjectLabels) > 0)
                            <div class="flex max-w-full flex-wrap gap-2 sm:justify-end">
                                @foreach($subjectLabels as $subjectLabel)
                                    <span class="inline-flex max-w-full items-center rounded-md bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/30">
                                        <span class="truncate">{{ $subjectLabel }}</span>
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $notAvailable }}</p>
                        @endif
                    </div>
                </div>

                <div class="min-w-0 rounded-lg border border-primary-200 bg-primary-50/60 p-4 dark:border-primary-500/30 dark:bg-primary-500/10 sm:col-span-2 xl:col-span-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-primary-700 dark:text-primary-300">{{ __('student.registration_date') }}</p>
                            <p class="mt-1 text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('student.attendance_calculated_from_registration_date') }}</p>
                        </div>

                        @if(count($registrationDateEntries) > 0)
                            <div class="flex max-w-full flex-wrap gap-2 lg:justify-end">
                                @foreach($registrationDateEntries as $entry)
                                    <span class="inline-flex max-w-full items-center gap-1 rounded-md bg-white px-2.5 py-1 text-xs font-medium text-gray-700 ring-1 ring-inset ring-primary-600/20 dark:bg-gray-900 dark:text-gray-200 dark:ring-primary-400/30">
                                        <span class="truncate">{{ $entry['subject'] }}</span>
                                        <span class="text-gray-400 dark:text-gray-500">-</span>
                                        <span dir="ltr">{{ $entry['date'] }}</span>
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $notAvailable }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-primary-200 bg-white p-5 shadow-sm dark:border-primary-500/30 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-medium text-primary-700 dark:text-primary-300">{{ __('student.total_lectures') }}</p>
                    <span class="rounded-lg bg-primary-50 p-2 text-primary-600 dark:bg-primary-500/10 dark:text-primary-300">
                        <x-filament::icon icon="heroicon-o-academic-cap" class="h-5 w-5" />
                    </span>
                </div>
                <p class="mt-4 text-3xl font-semibold text-gray-950 dark:text-white">{{ $summary['total_lectures'] }}</p>
            </div>

            <div class="rounded-xl border border-green-200 bg-white p-5 shadow-sm dark:border-green-500/30 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-medium text-green-700 dark:text-green-300">{{ __('student.total_present') }}</p>
                    <span class="rounded-lg bg-green-50 p-2 text-green-600 dark:bg-green-500/10 dark:text-green-300">
                        <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5" />
                    </span>
                </div>
                <p class="mt-4 text-3xl font-semibold text-green-700 dark:text-green-300">{{ $summary['total_present'] }}</p>
            </div>

            <div class="rounded-xl border border-red-200 bg-white p-5 shadow-sm dark:border-red-500/30 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-medium text-red-700 dark:text-red-300">{{ __('student.total_absent') }}</p>
                    <span class="rounded-lg bg-red-50 p-2 text-red-600 dark:bg-red-500/10 dark:text-red-300">
                        <x-filament::icon icon="heroicon-o-x-circle" class="h-5 w-5" />
                    </span>
                </div>
                <p class="mt-4 text-3xl font-semibold text-red-700 dark:text-red-300">{{ $summary['total_absent'] }}</p>
            </div>

            <div class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm dark:border-amber-500/30 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-medium text-amber-700 dark:text-amber-300">{{ __('student.overall_attendance') }}</p>
                    <span class="inline-flex items-center rounded-md px-2 py-1 text-sm font-semibold ring-1 ring-inset {{ $percentageTone['badge'] }}">
                        {{ $percentage }}%
                    </span>
                </div>
                <p class="mt-4 text-3xl font-semibold {{ $percentageTone['text'] }}">{{ $percentage }}%</p>
                <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                    <div class="h-full rounded-full {{ $percentageTone['bar'] }}" style="width: {{ min(100, max(0, $percentage)) }}%"></div>
                </div>
            </div>
        </section>

        @if($summary['total_lectures'] === 0)
            <section class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 text-center dark:border-white/10 dark:bg-white/5">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white text-gray-500 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
                    <x-filament::icon icon="heroicon-o-calendar-days" class="h-6 w-6" />
                </div>
                <h3 class="mt-4 text-base font-semibold text-gray-950 dark:text-white">{{ __('student.no_attendance_data') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('student.no_attendance_records') }}</p>
            </section>
        @endif

        <section class="space-y-3">
            <div>
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('student.detailed_attendance_history') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('student.overall_attendance_summary') }}</p>
            </div>

            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
