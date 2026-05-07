<x-filament-panels::page>
    <div class="mx-auto max-w-3xl space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="space-y-3">
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                    {{ __('user-guide.page.title') }}
                </h2>

                <p class="text-sm leading-7 text-gray-600 dark:text-gray-300">
                    {{ __('user-guide.page.description') }}
                </p>
            </div>

            <div class="mt-6">
                <x-filament::button
                    tag="a"
                    :href="route('admin.user-guide.download')"
                    icon="heroicon-o-arrow-down-tray"
                >
                    {{ __('user-guide.page.download_button') }}
                </x-filament::button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
