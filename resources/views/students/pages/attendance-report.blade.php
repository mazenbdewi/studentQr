<x-filament-panels::page>
    <div class="space-y-4">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 bg-gray-50 px-5 py-4 dark:border-white/10 dark:bg-white/5">
                <div class="text-sm font-medium text-primary-600 dark:text-primary-400">
                    {{ __('student.attendance_report') }}
                </div>
                <h2 class="mt-2 max-w-full break-words text-2xl font-semibold leading-tight text-gray-950 dark:text-white">
                    {{ $this->record->name }}
                </h2>
            </div>

            <div class="grid gap-3 px-5 py-4 text-sm text-gray-600 dark:text-gray-300 md:grid-cols-2">
                <div class="min-w-0 break-words">
                    <span class="font-medium text-gray-900 dark:text-white">{{ __('student.student_number') }}:</span>
                    {{ $this->record->student_number ?: __('lecture-session.not_available') }}
                </div>
                <div class="min-w-0 break-words">
                    <span class="font-medium text-gray-900 dark:text-white">{{ __('lecture-session.subject') }}:</span>
                    {{ $this->getSelectedSubjectLabel() }}
                </div>
                @if($this->record->national_number)
                    <div class="min-w-0 break-words">
                        <span class="font-medium text-gray-900 dark:text-white">{{ __('student.national_number') }}:</span>
                        {{ $this->record->national_number }}
                    </div>
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
