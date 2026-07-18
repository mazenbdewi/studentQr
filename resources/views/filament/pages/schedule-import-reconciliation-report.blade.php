<x-filament-panels::page>
    @php($counts = $this->tabCounts())

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($this->tabLabels() as $key => $label)
            <button
                type="button"
                wire:click="selectTab('{{ $key }}')"
                @class([
                    'rounded-lg border p-4 text-start transition',
                    'border-primary-500 bg-primary-50 text-primary-800 dark:bg-primary-950' => $activeTab === $key,
                    'border-gray-200 bg-white text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200' => $activeTab !== $key,
                ])
            >
                <div class="text-sm font-semibold">{{ $label }}</div>
                <div class="mt-1 text-2xl font-bold">{{ $counts[$key] ?? 0 }}</div>
            </button>
        @endforeach
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-700 dark:bg-gray-900">
        <span class="font-semibold">{{ __('schedule-import-reconciliation.batch') }}:</span>
        {{ $batchRecord->uuid }}
        <span class="mx-2">•</span>
        <span class="font-semibold">{{ __('schedule-import-reconciliation.academic_term') }}:</span>
        {{ $batchRecord->academicTerms->sole()->display_name }}
    </div>

    {{ $this->table }}
</x-filament-panels::page>
