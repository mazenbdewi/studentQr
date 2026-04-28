<x-filament-panels::page>
    <div class="space-y-4">
        <div class="p-4 bg-white border border-gray-200 rounded-xl dark:border-white/10 dark:bg-gray-900">
            <h2 class="text-lg font-bold">
                {{ $record->name }}
            </h2>
            <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                <div>{{ __('student.student_number') }}: {{ $record->student_number ?: __('lecture-session.not_available') }}</div>
                @if($record->national_number)
                    <div>{{ __('student.national_number') }}: {{ $record->national_number }}</div>
                @endif
                <div>{{ __('student.department_id') }}: {{ $record->department?->name ?? __('lecture-session.not_available') }}</div>
                <div>{{ __('student.year') }}: {{ filled($record->year) ? __("student.year_options.{$record->year}") : __('lecture-session.not_available') }}</div>
                <div>{{ __('student.phone') }}: {{ $record->phone ?: __('lecture-session.not_available') }}</div>
                <div>{{ __('student.status') }}: {{ filled($record->status) ? __("student.status_{$record->status}") : __('student.status_active') }}</div>
            </div>
        </div>

        <div class="p-4 bg-white border border-gray-200 rounded-xl dark:border-white/10 dark:bg-gray-900">
            <h3 class="mb-4 text-xl font-bold">
                {{ __('student.attendance_history') }}
            </h3>

            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
