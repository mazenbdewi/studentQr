<x-filament-panels::page>
    <div wire:loading class="flex items-center justify-center gap-3 rounded-xl border border-primary-200 bg-primary-50 p-4 text-primary-900 dark:border-primary-800 dark:bg-primary-950 dark:text-primary-100" role="status" dir="rtl">
        <div class="h-5 w-5 animate-spin rounded-full border-2 border-primary-200 border-t-primary-600"></div>
        <span class="font-semibold">جاري تحميل تقرير مراجعة الاستيراد...</span>
    </div>

    @php($counts = $this->tabCounts())
    @php($summary = $this->remediationSummary())

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

    <section aria-label="{{ __('schedule-import-reconciliation.summary_title') }}">
        <h2 class="mb-3 text-base font-semibold text-gray-950 dark:text-white">{{ __('schedule-import-reconciliation.summary_title') }}</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @foreach (__('schedule-import-reconciliation.summary') as $key => $label)
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                    <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $summary[$key] ?? 0 }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-700 dark:bg-gray-900">
        <span class="font-semibold">{{ __('schedule-import-reconciliation.batch') }}:</span>
        {{ $batchRecord->uuid }}
        <span class="mx-2">•</span>
        <span class="font-semibold">{{ __('schedule-import-reconciliation.academic_term') }}:</span>
        {{ $batchRecord->academicTerms->sole()->display_name }}
    </div>

    {{ $this->table }}
</x-filament-panels::page>
