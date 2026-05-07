<x-filament-panels::page>
    @php
        $backups = $this->backups;
        $latestBackup = $backups[0] ?? null;
        $backupCount = count($backups);
        $totalBytes = array_sum(array_column($backups, 'size'));
        $totalSize = app(\App\Services\DatabaseBackupService::class)->humanReadableSize($totalBytes);
        $isRtl = app()->getLocale() === 'ar';
    @endphp

    <style>
        .database-backups-page {
            --backup-surface: #ffffff;
            --backup-muted-surface: #f8fafc;
            --backup-border: #e5e7eb;
            --backup-text: #111827;
            --backup-muted: #6b7280;
            --backup-primary: #2563eb;
            --backup-primary-soft: #eff6ff;
            --backup-success: #059669;
            --backup-success-soft: #ecfdf5;
            max-width: 1120px;
            margin-inline: auto;
        }

        .database-backups-page * {
            box-sizing: border-box;
        }

        .database-backups-page__stack {
            display: grid;
            gap: 16px;
        }

        .database-backups-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .database-backups-stat,
        .database-backups-panel,
        .database-backups-alert {
            background: var(--backup-surface);
            border: 1px solid var(--backup-border);
            border-radius: 14px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05);
        }

        .database-backups-stat {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 88px;
            padding: 16px;
        }

        .database-backups-stat__icon,
        .database-backups-empty__icon,
        .database-backups-file-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            border-radius: 12px;
        }

        .database-backups-stat__icon {
            width: 42px;
            height: 42px;
            color: var(--backup-primary);
            background: var(--backup-primary-soft);
        }

        .database-backups-title svg,
        .database-backups-stat__icon svg,
        .database-backups-alert svg,
        .database-backups-file-icon svg {
            width: 20px;
            height: 20px;
        }

        .database-backups-empty__icon svg {
            width: 24px;
            height: 24px;
        }

        .database-backups-title svg {
            color: var(--backup-primary);
        }

        .database-backups-stat__icon--success {
            color: var(--backup-success);
            background: var(--backup-success-soft);
        }

        .database-backups-stat__icon--neutral {
            color: #4b5563;
            background: #f3f4f6;
        }

        .database-backups-label {
            margin: 0;
            color: var(--backup-muted);
            font-size: 13px;
            line-height: 1.5;
        }

        .database-backups-value {
            margin: 2px 0 0;
            color: var(--backup-text);
            font-size: 22px;
            font-weight: 700;
            line-height: 1.25;
        }

        .database-backups-panel {
            overflow: hidden;
        }

        .database-backups-panel__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 20px;
            border-bottom: 1px solid var(--backup-border);
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        }

        .database-backups-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            color: var(--backup-text);
            font-size: 16px;
            font-weight: 700;
            line-height: 1.4;
        }

        .database-backups-subtitle {
            margin: 4px 0 0;
            color: var(--backup-muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .database-backups-latest {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(160px, 0.65fr) minmax(120px, 0.45fr);
            gap: 12px;
            padding: 16px 20px 20px;
        }

        .database-backups-detail {
            min-width: 0;
            padding: 14px;
            background: var(--backup-muted-surface);
            border: 1px solid var(--backup-border);
            border-radius: 12px;
        }

        .database-backups-detail__value {
            margin: 6px 0 0;
            color: var(--backup-text);
            font-size: 14px;
            font-weight: 700;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .database-backups-empty {
            padding: 34px 20px;
            text-align: center;
        }

        .database-backups-empty__icon {
            width: 48px;
            height: 48px;
            margin-inline: auto;
            color: var(--backup-primary);
            background: var(--backup-primary-soft);
        }

        .database-backups-empty__title {
            margin: 12px 0 0;
            color: var(--backup-text);
            font-size: 15px;
            font-weight: 700;
        }

        .database-backups-empty__body {
            max-width: 420px;
            margin: 4px auto 0;
            color: var(--backup-muted);
            font-size: 13px;
            line-height: 1.7;
        }

        .database-backups-alert {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 14px 16px;
            color: #065f46;
            background: var(--backup-success-soft);
            border-color: #a7f3d0;
        }

        .database-backups-alert__message {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.7;
        }

        .database-backups-table-wrap {
            overflow-x: auto;
        }

        .database-backups-table {
            width: 100%;
            border-collapse: collapse;
            text-align: start;
        }

        .database-backups-table th {
            padding: 12px 16px;
            color: var(--backup-muted);
            background: var(--backup-muted-surface);
            border-bottom: 1px solid var(--backup-border);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0;
            white-space: nowrap;
        }

        .database-backups-table td {
            padding: 14px 16px;
            color: #374151;
            border-bottom: 1px solid var(--backup-border);
            font-size: 14px;
            vertical-align: middle;
        }

        .database-backups-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .database-backups-table tbody tr:hover {
            background: #fafafa;
        }

        .database-backups-file {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 280px;
            color: var(--backup-text);
            font-weight: 700;
        }

        .database-backups-file-icon {
            width: 36px;
            height: 36px;
            color: var(--backup-primary);
            background: var(--backup-primary-soft);
        }

        .database-backups-file__name {
            overflow-wrap: anywhere;
        }

        .database-backups-size {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            padding: 3px 9px;
            color: #374151;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .database-backups-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
        }

        .database-backups-nowrap {
            white-space: nowrap;
        }

        .dark .database-backups-page {
            --backup-surface: #111827;
            --backup-muted-surface: #0f172a;
            --backup-border: rgba(255, 255, 255, 0.1);
            --backup-text: #f9fafb;
            --backup-muted: #9ca3af;
            --backup-primary-soft: rgba(37, 99, 235, 0.16);
            --backup-success-soft: rgba(5, 150, 105, 0.16);
        }

        .dark .database-backups-panel__header {
            background: #111827;
        }

        .dark .database-backups-stat__icon--neutral,
        .dark .database-backups-size {
            color: #d1d5db;
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .dark .database-backups-table td {
            color: #d1d5db;
        }

        .dark .database-backups-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.04);
        }

        @media (max-width: 900px) {
            .database-backups-page {
                max-width: none;
            }

            .database-backups-stats,
            .database-backups-latest {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .database-backups-panel__header,
            .database-backups-alert {
                align-items: stretch;
                flex-direction: column;
            }

            .database-backups-actions {
                justify-content: flex-start;
            }
        }
    </style>

    <div class="database-backups-page" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
        <div class="database-backups-page__stack">
            <div class="database-backups-stats">
                <div class="database-backups-stat">
                    <span class="database-backups-stat__icon">
                        <x-filament::icon icon="heroicon-o-circle-stack" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="database-backups-label">{{ __('database-backups.total_backups') }}</p>
                        <p class="database-backups-value">{{ $backupCount }}</p>
                    </div>
                </div>

                <div class="database-backups-stat">
                    <span class="database-backups-stat__icon database-backups-stat__icon--success">
                        <x-filament::icon icon="heroicon-o-archive-box" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="database-backups-label">{{ __('database-backups.total_storage') }}</p>
                        <p class="database-backups-value">{{ $totalSize }}</p>
                    </div>
                </div>

                <div class="database-backups-stat">
                    <span class="database-backups-stat__icon database-backups-stat__icon--neutral">
                        <x-filament::icon icon="heroicon-o-lock-closed" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="database-backups-label">{{ __('database-backups.storage_status') }}</p>
                        <p class="database-backups-value">{{ __('database-backups.protected') }}</p>
                    </div>
                </div>
            </div>

            <section class="database-backups-panel">
                <header class="database-backups-panel__header">
                    <div>
                        <h2 class="database-backups-title">
                            <x-filament::icon icon="heroicon-o-shield-check" class="h-5 w-5 text-primary-600" />
                            {{ __('database-backups.latest_backup') }}
                        </h2>
                        <p class="database-backups-subtitle">
                            {{ $latestBackup ? __('database-backups.latest_ready') : __('database-backups.no_backups') }}
                        </p>
                    </div>

                    @if ($latestBackup)
                        <x-filament::button
                            tag="a"
                            href="{{ route('admin.database-backups.download', $latestBackup['file_name']) }}"
                            icon="heroicon-o-arrow-down-tray"
                        >
                            {{ __('database-backups.download_latest') }}
                        </x-filament::button>
                    @endif
                </header>

                @if ($latestBackup)
                    <div class="database-backups-latest">
                        <div class="database-backups-detail">
                            <p class="database-backups-label">{{ __('database-backups.file_name') }}</p>
                            <p class="database-backups-detail__value">{{ $latestBackup['file_name'] }}</p>
                        </div>

                        <div class="database-backups-detail">
                            <p class="database-backups-label">{{ __('database-backups.created_date') }}</p>
                            <p class="database-backups-detail__value">{{ $latestBackup['created_at']?->translatedFormat('Y-m-d H:i') }}</p>
                        </div>

                        <div class="database-backups-detail">
                            <p class="database-backups-label">{{ __('database-backups.file_size') }}</p>
                            <p class="database-backups-detail__value">{{ $latestBackup['size_for_humans'] }}</p>
                        </div>
                    </div>
                @else
                    <div class="database-backups-empty">
                        <span class="database-backups-empty__icon">
                            <x-filament::icon icon="heroicon-o-circle-stack" class="h-6 w-6" />
                        </span>
                        <h3 class="database-backups-empty__title">{{ __('database-backups.empty_title') }}</h3>
                        <p class="database-backups-empty__body">{{ __('database-backups.empty_body') }}</p>
                    </div>
                @endif
            </section>

            @if ($latestCreatedBackupFileName)
                <div class="database-backups-alert">
                    <p class="database-backups-alert__message">
                        <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5 shrink-0" />
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
            @endif

            <section class="database-backups-panel">
                <header class="database-backups-panel__header">
                    <div>
                        <h2 class="database-backups-title">
                            <x-filament::icon icon="heroicon-o-clock" class="h-5 w-5 text-primary-600" />
                            {{ __('database-backups.previous_backups') }}
                        </h2>
                    </div>
                </header>

                <div class="database-backups-table-wrap">
                    <table class="database-backups-table">
                        <thead>
                            <tr>
                                <th>{{ __('database-backups.file_name') }}</th>
                                <th>{{ __('database-backups.created_date') }}</th>
                                <th>{{ __('database-backups.file_size') }}</th>
                                <th style="text-align: end;">{{ __('database-backups.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($backups as $backup)
                                <tr wire:key="database-backup-{{ $backup['file_name'] }}">
                                    <td>
                                        <div class="database-backups-file">
                                            <span class="database-backups-file-icon">
                                                <x-filament::icon icon="heroicon-o-document-arrow-down" class="h-5 w-5" />
                                            </span>
                                            <span class="database-backups-file__name">{{ $backup['file_name'] }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="database-backups-nowrap">
                                            {{ $backup['created_at']?->translatedFormat('Y-m-d H:i') }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="database-backups-size">{{ $backup['size_for_humans'] }}</span>
                                    </td>
                                    <td>
                                        <div class="database-backups-actions">
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
                                    <td colspan="4">
                                        <div class="database-backups-empty">
                                            <span class="database-backups-empty__icon">
                                                <x-filament::icon icon="heroicon-o-archive-box" class="h-6 w-6" />
                                            </span>
                                            <h3 class="database-backups-empty__title">{{ __('database-backups.empty_title') }}</h3>
                                            <p class="database-backups-empty__body">{{ __('database-backups.empty_body') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
