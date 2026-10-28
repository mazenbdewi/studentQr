<x-filament-panels::page :dir="app()->getLocale() === 'ar' ? 'rtl' : 'ltr'">
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    <div
        x-data="{
            uploading: false,
            uploadReady: $wire.entangle('uploadReady'),
            verifyingUpload: false,
            uploadFailed: false,
            uploadProgress: 0,
            processing: @js($processingImport),
            progressVisible: false,
            progressUrl: null,
            progressTimer: null,
            progress: { status: 'pending', total_rows: 0, processed_rows: 0, successful_rows: 0, failed_rows: 0, percentage: 0, error: null },
            async pollProgress() {
                if (! this.progressUrl) return;
                const response = await fetch(this.progressUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (response.ok) this.progress = await response.json();
            },
            async startProcessing(url) {
                if (this.processing) return;
                this.processing = true;
                this.progressVisible = true;
                this.progressUrl = url;
                await this.pollProgress();
                this.progressTimer = setInterval(() => this.pollProgress(), 1000);
                try { await $wire.processImport(); } finally {
                    clearInterval(this.progressTimer);
                    await this.pollProgress();
                    this.processing = false;
                }
            },
        }"
        x-on:livewire-upload-start.window="uploading = true; uploadReady = false; verifyingUpload = false; uploadFailed = false; uploadProgress = 0; progressVisible = false"
        x-on:livewire-upload-progress.window="uploadProgress = Math.min(100, Math.max(0, Number($event.detail.progress ?? 0)))"
        x-on:livewire-upload-finish.window="uploading = false; uploadReady = true; verifyingUpload = true; uploadFailed = false; uploadProgress = 100; $wire.verifyUploadedFileReady()"
        x-on:livewire-upload-error.window="uploading = false; uploadReady = false; verifyingUpload = false; uploadFailed = true; uploadProgress = 0"
        x-on:livewire-upload-cancel.window="uploading = false; uploadReady = false; verifyingUpload = false; uploadFailed = false; uploadProgress = 0"
        x-on:manara-upload-state.window="uploadReady = Boolean($event.detail.ready); verifyingUpload = false; if (! uploadReady) { uploading = false; uploadProgress = 0 }"
        x-on:enrollment-import-prepared.window="startProcessing($event.detail.progressUrl)"
        class="space-y-6"
    >
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('manara-import.instructions_title') }}</h2>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('manara-import.used_columns') }}</h3>
                    <ul class="mt-2 list-disc space-y-1 ps-5 text-sm text-gray-600 dark:text-gray-300">
                        @foreach (__('manara-import.used_columns_list') as $column)
                            <li>{{ $column }}</li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('manara-import.ignored_columns') }}</h3>
                    <ul class="mt-2 list-disc space-y-1 ps-5 text-sm text-gray-600 dark:text-gray-300">
                        @foreach (__('manara-import.ignored_columns_list') as $column)
                            <li>{{ $column }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="mt-4 rounded-md bg-primary-50 p-4 text-sm text-primary-800 dark:bg-primary-950 dark:text-primary-200">
                <ul class="list-disc space-y-1 ps-5">
                    @foreach (__('manara-import.rules') as $rule)
                        <li>{{ $rule }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div
            x-cloak
            x-show="uploading"
            x-transition.opacity
            class="rounded-lg border border-primary-200 bg-primary-50 p-4 shadow-sm dark:border-primary-800 dark:bg-primary-950"
        >
            <div class="flex items-center gap-3">
                <x-filament::loading-indicator class="h-5 w-5 text-primary-600 dark:text-primary-300" />
                <div class="min-w-0 flex-1 text-sm font-medium text-primary-900 dark:text-primary-100">
                    <span>{{ __('manara-import.upload_loading') }}</span>
                    <span x-text="`${uploadProgress}%`"></span>
                </div>
            </div>

            <div class="mt-3 h-2 overflow-hidden rounded-full bg-primary-100 dark:bg-primary-900">
                <div
                    class="h-full rounded-full bg-primary-600 transition-all duration-300 dark:bg-primary-300"
                    x-bind:style="`width: ${uploadProgress}%`"
                ></div>
            </div>
        </div>

        <div
            x-cloak
            x-show="uploadReady && ! uploading && ! processing"
            x-transition.opacity
            class="rounded-lg border border-success-200 bg-success-50 p-4 text-sm font-semibold text-success-800 dark:border-success-800 dark:bg-success-950 dark:text-success-200"
        >
            {{ __('manara-import.upload_success') }}
        </div>

        <div
            x-cloak
            x-show="uploadFailed"
            x-transition.opacity
            class="rounded-lg border border-danger-200 bg-danger-50 p-4 text-sm font-semibold text-danger-800 dark:border-danger-800 dark:bg-danger-950 dark:text-danger-200"
        >
            {{ __('manara-import.upload_failed') }}
        </div>

        <div x-cloak x-show="progressVisible" class="rounded-xl border border-primary-200 bg-primary-50 p-4 shadow-sm dark:border-primary-800 dark:bg-primary-950" role="status" aria-live="polite">
            <span class="sr-only">{{ __('manara-import.import_loading') }}</span>
            <div class="flex items-start gap-3">
                <x-filament::loading-indicator x-show="processing" class="mt-0.5 h-5 w-5 shrink-0 text-primary-600 dark:text-primary-300" />
                <div class="min-w-0 flex-1 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm font-semibold text-primary-950 dark:text-primary-100">
                        <span x-text="progress.status === 'failed' ? @js(__('manara-import.processing_failed')) : (processing ? (progress.percentage === 100 ? @js(__('manara-import.processing_finalizing')) : @js(__('manara-import.processing_title'))) : @js(__('manara-import.processing_completed')))"></span>
                        <span x-text="`${progress.percentage}%`"></span>
                    </div>
                    <div class="h-3 overflow-hidden rounded-full bg-primary-100 dark:bg-primary-900" :dir="'ltr'">
                        <div class="h-full rounded-full bg-primary-600 transition-[width] duration-300 dark:bg-primary-300" :class="progress.status === 'failed' ? 'bg-danger-600' : ''" :style="`width: ${progress.percentage}%`"></div>
                    </div>
                    <p class="text-sm text-primary-800 dark:text-primary-200">
                        <span x-text="Number(progress.processed_rows).toLocaleString()"></span>
                        {{ __('manara-import.progress_of') }}
                        <span x-text="Number(progress.total_rows).toLocaleString()"></span>
                    </p>
                    <div class="grid grid-cols-2 gap-2 text-xs text-primary-700 sm:grid-cols-4 dark:text-primary-300">
                        <span>{{ __('manara-import.progress_total') }}: <b x-text="Number(progress.total_rows).toLocaleString()"></b></span>
                        <span>{{ __('manara-import.progress_processed') }}: <b x-text="Number(progress.processed_rows).toLocaleString()"></b></span>
                        <span>{{ __('manara-import.progress_successful') }}: <b x-text="Number(progress.successful_rows).toLocaleString()"></b></span>
                        <span>{{ __('manara-import.progress_failed') }}: <b x-text="Number(progress.failed_rows).toLocaleString()"></b></span>
                    </div>
                    <p x-show="progress.error" class="text-sm text-danger-700 dark:text-danger-300" x-text="progress.error"></p>
                </div>
            </div>
        </div>

        <form wire:submit="startImport" class="space-y-4">
            <fieldset wire:loading.attr="disabled" wire:target="startImport" x-bind:disabled="processing">
                {{ $this->form }}
            </fieldset>

            <div class="flex justify-end">
                <x-filament::button
                    type="submit"
                    icon="heroicon-o-arrow-up-tray"
                    wire:target="startImport"
                    wire:loading.attr="disabled"
                    x-bind:disabled="uploading || verifyingUpload || processing || ! uploadReady"
                >
                    <span wire:loading.remove wire:target="startImport">
                        {{ __('manara-import.import_button') }}
                    </span>
                    <span wire:loading wire:target="startImport">
                        {{ __('manara-import.import_button_loading') }}
                    </span>
                </x-filament::button>
            </div>
        </form>

        @if ($summary)
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('manara-import.summary_title') }}</h2>

                    @if ($errorsUrl)
                        <x-filament::button tag="a" :href="$errorsUrl" target="_blank" color="danger" icon="heroicon-o-arrow-down-tray">
                            {{ __('manara-import.download_errors') }}
                        </x-filament::button>
                    @endif
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach (__('manara-import.summary_labels') as $key => $label)
                        <div class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                            <div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $summary[$key] ?? 0 }}</div>
                        </div>
                    @endforeach
                </div>

                <div @class([
                    'mt-4 rounded-md border p-4 text-sm font-semibold',
                    'border-success-200 bg-success-50 text-success-800 dark:border-success-800 dark:bg-success-950 dark:text-success-200' => ($summary['zero_sections_created'] ?? 0) === 0,
                    'border-danger-200 bg-danger-50 text-danger-800 dark:border-danger-800 dark:bg-danger-950 dark:text-danger-200' => ($summary['zero_sections_created'] ?? 0) !== 0,
                ])>
                    {{ ($summary['zero_sections_created'] ?? 0) === 0
                        ? __('manara-import.no_zero_sections_created')
                        : __('manara-import.zero_sections_created_warning', ['count' => $summary['zero_sections_created']]) }}
                </div>

                @if ($completedBatchUuid)
                    <div class="mt-4 flex justify-end">
                        <x-filament::button
                            tag="a"
                            :href="\App\Filament\Pages\ManaraScheduleImport::getUrl(['source_batch' => $completedBatchUuid])"
                            icon="heroicon-o-calendar-days"
                        >
                            {{ __('manara-schedule-import.continue_from_enrollments') }}
                        </x-filament::button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
