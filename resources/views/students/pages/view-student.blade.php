<x-filament-panels::page>
    <div class="space-y-4">
        <div class="p-4 bg-white border border-gray-200 rounded-xl dark:border-white/10 dark:bg-gray-900">
            <h2 class="text-lg font-bold">
                {{ $record->name }}
            </h2>
            <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                <div>Student Number: {{ $record->student_number }}</div>
                @if($record->national_number)
                    <div>National Number: {{ $record->national_number }}</div>
                @endif
                <div>Department: {{ $record->department?->name ?? '-' }}</div>
                <div>Year: {{ $record->year }}</div>
                <div>Phone: {{ $record->phone ?? '-' }}</div>
                <div>Status: {{ $record->status ?? 'active' }}</div>
            </div>
        </div>

        <div class="p-4 bg-white border border-gray-200 rounded-xl dark:border-white/10 dark:bg-gray-900">
            <h3 class="mb-4 text-xl font-bold">
                {{ app()->getLocale() === 'ar' ? 'سجل الحضور' : 'Attendance History' }}
            </h3>

            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>

