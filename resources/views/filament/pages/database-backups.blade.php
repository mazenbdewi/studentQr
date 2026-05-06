<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                {{ __('database-backups.latest_backup') }}
            </x-slot>

            @if ($this->latestBackup)
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('database-backups.file_name') }}</p>
                        <p class="mt-1 break-all font-medium text-gray-950 dark:text-white">{{ $this->latestBackup['file_name'] }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('database-backups.created_date') }}</p>
                        <p class="mt-1 font-medium text-gray-950 dark:text-white">{{ $this->latestBackup['created_at']?->translatedFormat('Y-m-d H:i') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('database-backups.file_size') }}</p>
                        <p class="mt-1 font-medium text-gray-950 dark:text-white">{{ $this->latestBackup['size_for_humans'] }}</p>
                    </div>
                </div>

                <div class="mt-4">
                    <x-filament::button
                        tag="a"
                        href="{{ route('admin.database-backups.download', $this->latestBackup['file_name']) }}"
                        icon="heroicon-o-arrow-down-tray"
                    >
                        {{ __('database-backups.download') }}
                    </x-filament::button>
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('database-backups.no_backups') }}</p>
            @endif
        </x-filament::section>

        @if ($latestCreatedBackupFileName)
            <x-filament::section>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm font-medium text-gray-950 dark:text-white">
                        {{ __('database-backups.created_success_body') }}
                    </p>

                    <x-filament::button
                        tag="a"
                        href="{{ route('admin.database-backups.download', $latestCreatedBackupFileName) }}"
                        icon="heroicon-o-arrow-down-tray"
                    >
                        {{ __('database-backups.download_now') }}
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section>
            <x-slot name="heading">
                {{ __('database-backups.previous_backups') }}
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-start dark:divide-white/10">
                    <thead>
                        <tr class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 text-start">{{ __('database-backups.file_name') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('database-backups.created_date') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('database-backups.file_size') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('database-backups.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse ($this->backups as $backup)
                            <tr wire:key="database-backup-{{ $backup['file_name'] }}">
                                <td class="px-4 py-3 text-sm font-medium text-gray-950 dark:text-white">
                                    <span class="break-all">{{ $backup['file_name'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $backup['created_at']?->translatedFormat('Y-m-d H:i') }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $backup['size_for_humans'] }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <x-filament::button
                                            tag="a"
                                            size="sm"
                                            color="gray"
                                            href="{{ route('admin.database-backups.download', $backup['file_name']) }}"
                                            icon="heroicon-o-arrow-down-tray"
                                        >
                                            {{ __('database-backups.download') }}
                                        </x-filament::button>

                                        <x-filament::button
                                            size="sm"
                                            color="danger"
                                            icon="heroicon-o-trash"
                                            wire:click="deleteBackup(@js($backup['file_name']))"
                                            wire:confirm="{{ __('database-backups.delete_confirmation') }}"
                                        >
                                            {{ __('database-backups.delete') }}
                                        </x-filament::button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('database-backups.no_backups') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
