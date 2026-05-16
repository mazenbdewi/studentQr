<x-filament-panels::page>
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    <div
        x-data="{ uploading: false, progress: 0 }"
        x-on:livewire-upload-start.window="uploading = true; progress = 0"
        x-on:livewire-upload-progress.window="progress = $event.detail.progress || progress"
        x-on:livewire-upload-finish.window="uploading = false; progress = 100"
        x-on:livewire-upload-error.window="uploading = false"
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

                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-primary-900 dark:text-primary-100">
                        {{ __('manara-import.upload_loading') }}
                    </div>
                    <div class="mt-1 text-sm text-primary-700 dark:text-primary-200">
                        {{ __('manara-import.upload_loading_description') }}
                    </div>
                </div>

                <div class="text-sm font-semibold text-primary-800 dark:text-primary-100" x-text="`${progress}%`"></div>
            </div>

            <div class="mt-3 h-2 overflow-hidden rounded-full bg-primary-100 dark:bg-primary-900">
                <div
                    class="h-full rounded-full bg-primary-600 transition-all duration-300 dark:bg-primary-300"
                    x-bind:style="`width: ${progress}%`"
                ></div>
            </div>
        </div>

        <div
            wire:loading.delay.class.remove="hidden"
            wire:loading.delay.class="flex"
            wire:target="import"
            class="hidden items-center gap-3 rounded-lg border border-warning-200 bg-warning-50 p-4 shadow-sm dark:border-warning-800 dark:bg-warning-950"
        >
            <x-filament::loading-indicator class="h-5 w-5 text-warning-600 dark:text-warning-300" />

            <div>
                <div class="text-sm font-medium text-warning-900 dark:text-warning-100">
                    {{ __('manara-import.import_loading') }}
                </div>
                <div class="mt-1 text-sm text-warning-700 dark:text-warning-200">
                    {{ __('manara-import.import_loading_description') }}
                </div>
            </div>
        </div>

        <form wire:submit="import" class="space-y-4">
            {{ $this->form }}

            <div class="flex justify-end">
                <x-filament::button
                    type="submit"
                    icon="heroicon-o-arrow-up-tray"
                    wire:target="import"
                    wire:loading.attr="disabled"
                    x-bind:disabled="uploading"
                >
                    <span wire:loading.remove wire:target="import">
                        {{ __('manara-import.import_button') }}
                    </span>
                    <span wire:loading wire:target="import">
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
            </div>
        @endif
    </div>
</x-filament-panels::page>
