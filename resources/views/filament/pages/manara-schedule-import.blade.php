<x-filament-panels::page>
    <style>[x-cloak] { display: none !important; }</style>

    <div
        x-data="{
            uploading: false,
            uploadReady: $wire.entangle('uploadReady'),
            sourceBatchReady: @js($sourceBatchReady),
            verifyingUpload: false,
            uploadFailed: false,
            uploadProgress: 0,
        }"
        x-on:livewire-upload-start.window="uploading = true; uploadReady = false; verifyingUpload = false; uploadFailed = false; uploadProgress = 0"
        x-on:livewire-upload-progress.window="uploadProgress = Math.min(100, Math.max(0, Number($event.detail.progress ?? 0)))"
        x-on:livewire-upload-finish.window="uploading = false; uploadReady = true; verifyingUpload = true; uploadFailed = false; uploadProgress = 100; $wire.verifyUploadedFileReady()"
        x-on:livewire-upload-error.window="uploading = false; uploadReady = false; verifyingUpload = false; uploadFailed = true; uploadProgress = 0"
        x-on:livewire-upload-cancel.window="uploading = false; uploadReady = false; verifyingUpload = false; uploadFailed = false; uploadProgress = 0"
        x-on:schedule-upload-state.window="uploadReady = Boolean($event.detail.ready); verifyingUpload = false; if (! uploadReady) { uploading = false; uploadProgress = 0 }"
        class="space-y-6"
    >
        <section class="rounded-xl border border-primary-300 bg-primary-50 p-5 text-primary-950 shadow-sm dark:border-primary-700 dark:bg-primary-950 dark:text-primary-100">
            <h2 class="text-lg font-bold">{{ __('manara-schedule-import.title') }}</h2>
            <p class="mt-2 text-sm leading-6 text-primary-900 dark:text-primary-200">{{ __('manara-schedule-import.prerequisite_explanation') }}</p>
            <div class="mt-4 text-sm font-semibold">{{ __('manara-schedule-import.phase_sequence') }}</div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-lg border border-primary-200 bg-white/70 p-3 dark:border-primary-800 dark:bg-gray-900/60">
                    <div class="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">{{ __('manara-schedule-import.stage_one') }}</div>
                    <div class="mt-1 font-semibold">{{ __('manara-schedule-import.stage_one_label') }}</div>
                </div>
                <div class="rounded-lg border-2 border-primary-500 bg-primary-100 p-3 shadow-sm dark:border-primary-400 dark:bg-primary-900">
                    <div class="text-xs font-semibold uppercase tracking-wide text-primary-800 dark:text-primary-200">{{ __('manara-schedule-import.stage_two') }}</div>
                    <div class="mt-1 font-bold">{{ __('manara-schedule-import.stage_two_label') }}</div>
                </div>
            </div>
        </section>

        @if ($sourceResolutionError)
            <div class="rounded-lg border border-danger-200 bg-danger-50 p-4 text-sm text-danger-800 dark:border-danger-800 dark:bg-danger-950 dark:text-danger-200">
                <div class="font-semibold">{{ __('manara-schedule-import.source_batch_unavailable') }}</div>
                <div class="mt-1">{{ $sourceResolutionError }}</div>
                <div class="mt-2 text-danger-900 dark:text-danger-100">{{ __('manara-schedule-import.prerequisite_unavailable') }}</div>
                <x-filament::button class="mt-3" tag="a" :href="\App\Filament\Pages\ManaraEnrollmentImport::getUrl()" color="danger" icon="heroicon-o-arrow-right">
                    {{ __('manara-schedule-import.go_to_stage_one') }}
                </x-filament::button>
            </div>
        @elseif ($sourceBatchReady && $sourceBatchUuid)
            <div class="rounded-lg border border-success-200 bg-success-50 p-4 text-sm text-success-800 dark:border-success-800 dark:bg-success-950 dark:text-success-200">
                <div class="font-semibold">{{ __('manara-schedule-import.prerequisite_ready') }}</div>
                @if ($resolvedAcademicTermName)
                    <div class="mt-1">{{ __('manara-schedule-import.resolved_academic_term', ['term' => $resolvedAcademicTermName]) }}</div>
                @endif
                @if ($sourceBatchFilename)
                    <div class="mt-1">{{ __('manara-schedule-import.source_filename', ['filename' => $sourceBatchFilename]) }}</div>
                @endif
                @if ($sourceBatchImportedRows !== null)
                    <div class="mt-1">{{ __('manara-schedule-import.source_imported_rows', ['count' => number_format($sourceBatchImportedRows)]) }}</div>
                @endif
            </div>
        @endif

        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('manara-schedule-import.instructions_title') }}</h2>
            <ul class="mt-3 list-disc space-y-1 ps-5 text-sm text-gray-600 dark:text-gray-300">
                @foreach (__('manara-schedule-import.instructions') as $instruction)
                    <li>{{ $instruction }}</li>
                @endforeach
            </ul>
        </div>

        <div x-cloak x-show="uploading" class="rounded-lg border border-primary-200 bg-primary-50 p-4 dark:border-primary-800 dark:bg-primary-950">
            <div class="flex items-center gap-3">
                <x-filament::loading-indicator class="h-5 w-5" />
                <span class="text-sm font-medium">{{ __('manara-schedule-import.upload_loading') }} <span x-text="`${uploadProgress}%`"></span></span>
            </div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-primary-100 dark:bg-primary-900">
                <div class="h-full rounded-full bg-primary-600" x-bind:style="`width: ${uploadProgress}%`"></div>
            </div>
        </div>

        <div x-cloak x-show="uploadReady && ! uploading" class="rounded-lg border border-success-200 bg-success-50 p-4 text-sm font-semibold text-success-800">
            {{ __('manara-schedule-import.upload_success') }}
        </div>

        <div x-cloak x-show="uploadFailed" class="rounded-lg border border-danger-200 bg-danger-50 p-4 text-sm font-semibold text-danger-800">
            {{ __('manara-schedule-import.upload_failed') }}
        </div>

        <div
            wire:loading.delay.class.remove="hidden"
            wire:loading.delay.class="flex"
            wire:target="import"
            role="status"
            aria-live="polite"
            class="hidden items-start gap-3 rounded-xl border border-primary-200 bg-primary-50 p-4 text-start text-primary-950 shadow-sm dark:border-primary-800 dark:bg-primary-950 dark:text-primary-100"
        >
            <x-filament::loading-indicator class="mt-0.5 h-5 w-5 shrink-0 text-primary-600 dark:text-primary-300" />
            <div class="space-y-1">
                <div class="text-sm font-semibold">{{ __('manara-schedule-import.import_loading') }}</div>
                <div class="text-xs text-primary-700 dark:text-primary-300">{{ __('manara-schedule-import.import_loading_wait') }}</div>
            </div>
        </div>

        <form wire:submit="import" class="space-y-4">
            <fieldset wire:loading.attr="disabled" wire:target="import" @disabled(! $sourceBatchReady)>{{ $this->form }}</fieldset>
            <div class="flex justify-end">
                <x-filament::button
                    type="submit"
                    icon="heroicon-o-arrow-up-tray"
                    wire:target="import"
                    wire:loading.attr="disabled"
                    x-bind:disabled="uploading || verifyingUpload || ! uploadReady || ! sourceBatchReady"
                >
                    <span wire:loading.remove wire:target="import">{{ __('manara-schedule-import.import_button') }}</span>
                    <span wire:loading wire:target="import">{{ __('manara-schedule-import.import_button_loading') }}</span>
                </x-filament::button>
            </div>
        </form>

        @if ($summary)
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('manara-schedule-import.summary_title') }}</h2>
                        @if ($resultStatusLabel)
                            <span @class([
                                'mt-2 inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                                'bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200' => $resultStatus === \App\Models\ImportBatch::STATUS_COMPLETED,
                                'bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200' => $resultStatus === \App\Models\ImportBatch::STATUS_COMPLETED_WITH_ERRORS,
                                'bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200' => $resultStatus === \App\Models\ImportBatch::STATUS_FAILED,
                            ])>{{ $resultStatusLabel }}</span>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($weeklyScheduleUrl)
                            <x-filament::button tag="a" :href="$weeklyScheduleUrl" color="success" icon="heroicon-o-calendar-days">
                                {{ __('manara-schedule-import.open_weekly_schedule') }}
                            </x-filament::button>
                        @endif
                        @if ($reconciliationUrl)
                            <x-filament::button tag="a" :href="$reconciliationUrl" color="warning" icon="heroicon-o-clipboard-document-check">
                                {{ __('manara-schedule-import.open_reconciliation') }}
                            </x-filament::button>
                        @endif
                        @if ($errorsUrl)
                            <x-filament::button tag="a" :href="$errorsUrl" target="_blank" color="danger" icon="heroicon-o-arrow-down-tray">
                                {{ __('manara-schedule-import.download_errors') }}
                            </x-filament::button>
                        @endif
                    </div>
                </div>

                @if ($resultHasPersistedSchedule)
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <div class="rounded-lg border border-success-200 bg-success-50 p-4 dark:border-success-800 dark:bg-success-950">
                            <div class="font-semibold text-success-800 dark:text-success-200">{{ __('manara-schedule-import.weekly_status_title') }}</div>
                            <div class="mt-1 text-sm text-success-700 dark:text-success-300">{{ __('manara-schedule-import.weekly_status_imported') }}</div>
                        </div>
                        <div class="rounded-lg border border-warning-200 bg-warning-50 p-4 dark:border-warning-800 dark:bg-warning-950">
                            <div class="font-semibold text-warning-800 dark:text-warning-200">{{ __('manara-schedule-import.dated_status_title') }}</div>
                            <div class="mt-1 text-sm text-warning-700 dark:text-warning-300">{{ __('manara-schedule-import.dated_status_pending') }}</div>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ __('manara-schedule-import.recurring_explanation') }}</p>
                @endif

                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach (__('manara-schedule-import.summary_labels') as $key => $label)
                        <div class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                            <div class="mt-1 break-words text-lg font-semibold text-gray-950 dark:text-white">{{ $summary[$key] ?? 0 }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
