<x-filament-panels::page>
    <form wire:submit="openReport" class="max-w-2xl space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
            <span class="mb-1 block">{{ __('weekly-schedule-reports.select_batch') }}</span>
            <select wire:model="batchId" class="w-full rounded-lg border-gray-300 bg-white text-gray-950 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="">{{ __('weekly-schedule-reports.select_batch_placeholder') }}</option>
                @foreach ($this->batchOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <x-filament::button type="submit" icon="heroicon-o-clipboard-document-check">{{ __('weekly-schedule-reports.open_reconciliation') }}</x-filament::button>
    </form>
</x-filament-panels::page>
