<?php

namespace App\Filament\Pages;

use App\Services\DatabaseBackupService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Throwable;

class DatabaseBackups extends Page
{
    protected static ?string $slug = 'database-backups';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CircleStack;

    protected string $view = 'filament.pages.database-backups';

    public ?string $latestCreatedBackupFileName = null;

    public static function canAccess(): bool
    {
        return app(DatabaseBackupService::class)->canManageBackups(Filament::auth()->user());
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-dashboard.navigation.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('database-backups.title');
    }

    public static function getNavigationSort(): ?int
    {
        return 95;
    }

    public function getTitle(): string
    {
        return __('database-backups.title');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createBackup')
                ->label(__('database-backups.create_now'))
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->action('createBackup'),
        ];
    }

    public function createBackup(DatabaseBackupService $backups): void
    {
        $user = Filament::auth()->user();

        abort_unless($backups->canManageBackups($user), 403);

        try {
            $backup = $backups->create($user);
            $this->latestCreatedBackupFileName = $backup['file_name'];

            Notification::make()
                ->title(__('database-backups.created_success_title'))
                ->body(__('database-backups.created_success_body'))
                ->success()
                ->send();
        } catch (Throwable) {
            Notification::make()
                ->title(__('database-backups.created_failed_title'))
                ->body(__('database-backups.created_failed_body'))
                ->danger()
                ->send();
        }
    }

    public function deleteBackup(string $fileName, DatabaseBackupService $backups): void
    {
        $user = Filament::auth()->user();

        abort_unless($backups->canManageBackups($user), 403);

        try {
            $deleted = $backups->delete($fileName, $user);

            $notification = Notification::make()
                ->title($deleted ? __('database-backups.deleted_success') : __('database-backups.deleted_failed_title'))
                ->body($deleted ? null : __('database-backups.deleted_failed_body'));

            $deleted ? $notification->success() : $notification->danger();

            $notification->send();
        } catch (Throwable) {
            Notification::make()
                ->title(__('database-backups.deleted_failed_title'))
                ->body(__('database-backups.deleted_failed_body'))
                ->danger()
                ->send();
        }
    }

    public function getBackupsProperty(): array
    {
        return app(DatabaseBackupService::class)->all();
    }

    public function getLatestBackupProperty(): ?array
    {
        return app(DatabaseBackupService::class)->latest();
    }
}
