<x-filament-panels::page>
    <div class="space-y-4">
        <div class="p-4 bg-white border border-gray-200 rounded-xl dark:border-white/10 dark:bg-gray-900">
            <h2 class="text-lg font-bold">
                {{ $this->record->name }}
            </h2>

            <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                <div>{{ __('student.student_number') }}: {{ $this->record->student_number ?: __('lecture-session.not_available') }}</div>
                <div>{{ __('lecture-session.subject') }}: {{ $this->getSelectedSubjectLabel() }}</div>
                @if($this->record->national_number)
                    <div>{{ __('student.national_number') }}: {{ $this->record->national_number }}</div>
                @endif
            </div>
        </div>

        @php($summary = $this->getSummary())

        <div class="grid gap-4 md:grid-cols-4">
            <div class="p-4 bg-white border border-gray-200 rounded-xl dark:border-white/10 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('student.total_lectures') }}</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $summary['total_lectures'] }}</div>
            </div>

            <div class="p-4 bg-white border border-green-200 rounded-xl dark:border-green-900/40 dark:bg-gray-900">
                <div class="text-sm text-green-600 dark:text-green-400">{{ __('student.total_present') }}</div>
                <div class="mt-2 text-2xl font-semibold text-green-700 dark:text-green-300">{{ $summary['total_present'] }}</div>
            </div>

            <div class="p-4 bg-white border border-red-200 rounded-xl dark:border-red-900/40 dark:bg-gray-900">
                <div class="text-sm text-red-600 dark:text-red-400">{{ __('student.total_absent') }}</div>
                <div class="mt-2 text-2xl font-semibold text-red-700 dark:text-red-300">{{ $summary['total_absent'] }}</div>
            </div>

            <div class="p-4 bg-white border border-sky-200 rounded-xl dark:border-sky-900/40 dark:bg-gray-900">
                <div class="text-sm text-sky-600 dark:text-sky-400">{{ __('student.overall_attendance') }}</div>
                <div class="mt-2 text-2xl font-semibold text-sky-700 dark:text-sky-300">{{ $summary['attendance_percentage'] }}%</div>
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
