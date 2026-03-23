<x-filament-panels::page>
    <div class="space-y-4">
        <div class="p-4 bg-white border border-gray-200 rounded-xl dark:border-white/10 dark:bg-gray-900">
            <h2 class="text-lg font-bold">
                {{ $this->record->name }}
            </h2>

            <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                <div>Student Number: {{ $this->record->student_number }}</div>
                @if($this->record->national_number)
                    <div>National Number: {{ $this->record->national_number }}</div>
                @endif
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>

