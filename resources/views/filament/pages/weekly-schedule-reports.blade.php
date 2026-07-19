<x-filament-panels::page>
    @php
        $filterOptions = [
            'academicTermId' => [__('weekly-schedule-reports.filters.academic_term'), $this->academicTermOptions()],
            'importBatchId' => [__('weekly-schedule-reports.filters.import_batch'), $this->importBatchOptions()],
            'facultyId' => [__('weekly-schedule-reports.filters.faculty'), $this->facultyOptions()],
            'departmentId' => [__('weekly-schedule-reports.filters.department'), $this->departmentOptions()],
            'subjectId' => [__('weekly-schedule-reports.filters.subject'), $this->subjectOptions()],
            'sectionType' => [__('weekly-schedule-reports.filters.section_type'), \App\Models\Subject::subjectTypeOptions()],
            'subjectSectionId' => [__('weekly-schedule-reports.filters.subject_section'), $this->subjectSectionOptions()],
            'lecturerId' => [__('weekly-schedule-reports.filters.lecturer'), $this->lecturerOptions()],
            'hallId' => [__('weekly-schedule-reports.filters.hall'), $this->hallOptions()],
            'weekday' => [__('weekly-schedule-reports.filters.weekday'), __('weekly-schedule.weekdays')],
        ];
        $summary = $this->summaryCounts();
        $activeFilters = $this->activeFilterLabels();
    @endphp

    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900" aria-label="{{ __('weekly-schedule-reports.filters_title') }}">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ($filterOptions as $property => [$label, $options])
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                    <span class="mb-1 block">{{ $label }}</span>
                    <select wire:model.live="{{ $property }}" class="w-full rounded-lg border-gray-300 bg-white text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">{{ __('weekly-schedule-reports.all') }}</option>
                        @foreach ($options as $value => $optionLabel)
                            <option value="{{ $value }}">{{ $optionLabel }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
        </div>
        <div class="mt-4 flex justify-end">
            <x-filament::button wire:click="clearFilters" color="gray" icon="heroicon-o-x-mark">{{ __('weekly-schedule-reports.clear_filters') }}</x-filament::button>
        </div>
    </section>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach (__('weekly-schedule-reports.summary') as $key => $label)
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $summary[$key] ?? 0 }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @foreach (\App\Services\WeeklyScheduleReportService::reportTypes() as $type => $label)
            <button type="button" wire:click="selectReport('{{ $type }}')" class="rounded-xl border p-4 text-start transition {{ $reportType === $type ? 'border-primary-500 bg-primary-50 text-primary-950 dark:bg-primary-950 dark:text-primary-100' : 'border-gray-200 bg-white text-gray-900 hover:border-primary-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white' }}">
                <span class="font-semibold">{{ $label }}</span>
                <span class="mt-2 block text-sm opacity-75">{{ __('weekly-schedule-reports.show_report') }}</span>
            </button>
        @endforeach
    </div>

    <section class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-gray-950 dark:text-white">{{ \App\Services\WeeklyScheduleReportService::reportTypes()[$reportType] }}</h2>
                <div class="mt-2 flex flex-wrap gap-2">
                    @forelse ($activeFilters as $label => $value)
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $label }}: {{ $value }}</span>
                    @empty
                        <span class="text-sm text-gray-500">{{ __('weekly-schedule-reports.all_records') }}</span>
                    @endforelse
                </div>
            </div>
            @can('export', \App\Models\SubjectSectionScheduleSlot::class)
                <div class="flex flex-wrap gap-2">
                    <x-filament::button tag="a" :href="$this->excelUrl()" icon="heroicon-o-arrow-down-tray">{{ __('weekly-schedule-reports.download_excel') }}</x-filament::button>
                    <x-filament::button tag="a" :href="$this->pdfUrl()" color="gray" icon="heroicon-o-printer">{{ __('weekly-schedule-reports.download_pdf') }}</x-filament::button>
                </div>
            @endcan
        </div>

        @if ($reportType === \App\Services\WeeklyScheduleReportService::RECONCILIATION)
            @php($review = $this->reviewCounts())
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (__('weekly-schedule-reports.reconciliation') as $key => $label)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</div>
                        <div class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $review[$key] ?? 0 }}</div>
                    </div>
                @endforeach
            </div>
            <x-filament::button tag="a" :href="$this->reconciliationUrl()" color="warning" icon="heroicon-o-clipboard-document-check">{{ __('weekly-schedule-reports.full_reconciliation') }}</x-filament::button>
        @else
            {{ $this->table }}
        @endif
    </section>
</x-filament-panels::page>
