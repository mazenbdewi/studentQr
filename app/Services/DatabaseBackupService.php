<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Notifications\EventHandler;
use Spatie\Backup\Tasks\Backup\BackupJobFactory;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Throwable;

class DatabaseBackupService
{
    public const DISK = 'database_backups';

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function canManageBackups(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->email === 'super@admin.com'
            || $user->role === 'super_admin'
            || $user->hasRole('super-admin')
            || $user->hasRole('super_admin');
    }

    public function create(User $user): array
    {
        $this->ensureDirectoryExists();

        $fileName = $this->newBackupFileName();
        $startedAt = now();

        EventHandler::disable();

        try {
            $backupJob = BackupJobFactory::createFromConfig(Config::fromArray(config('backup')))
                ->dontBackupFilesystem()
                ->onlyBackupTo(self::DISK)
                ->setDestinationPath('.')
                ->setFilename($fileName);

            $backupJob->run();

            $backup = $this->findByFileName($fileName);

            $this->logBackupActivity($user, 'backup_created', 'info', [
                'file_name' => $fileName,
                'created_at' => $backup['created_at']?->toDateTimeString(),
                'size' => $backup['size'],
                'succeeded' => true,
                'started_at' => $startedAt->toDateTimeString(),
                'finished_at' => now()->toDateTimeString(),
            ]);

            return $backup;
        } catch (Throwable $exception) {
            Log::error('Database backup failed.', [
                'user_id' => $user->id,
                'file_name' => $fileName,
                'exception' => $exception,
            ]);

            $this->logBackupActivity($user, 'backup_failed', 'error', [
                'file_name' => $fileName,
                'succeeded' => false,
                'started_at' => $startedAt->toDateTimeString(),
                'finished_at' => now()->toDateTimeString(),
            ]);

            throw $exception;
        } finally {
            EventHandler::enable();
        }
    }

    public function all(): array
    {
        $this->ensureDirectoryExists();

        return collect(File::files($this->directory()))
            ->filter(fn ($file): bool => $this->isAllowedFileName($file->getFilename()))
            ->map(fn ($file): array => $this->formatFile($file->getPathname()))
            ->sortByDesc(fn (array $backup): int => $backup['created_at']?->getTimestamp() ?? 0)
            ->values()
            ->all();
    }

    public function latest(): ?array
    {
        return $this->all()[0] ?? null;
    }

    public function findByFileName(string $fileName): array
    {
        $path = $this->pathFor($fileName);

        if (! File::exists($path)) {
            throw new FileNotFoundException($fileName);
        }

        return $this->formatFile($path);
    }

    public function delete(string $fileName, User $user): bool
    {
        $backup = $this->findByFileName($fileName);
        $deleted = File::delete($backup['path']);

        $this->logBackupActivity($user, $deleted ? 'backup_deleted' : 'backup_delete_failed', $deleted ? 'warning' : 'error', [
            'file_name' => $backup['file_name'],
            'size' => $backup['size'],
            'succeeded' => $deleted,
        ]);

        return $deleted;
    }

    public function pathFor(string $fileName): string
    {
        $fileName = basename($fileName);

        if (! $this->isAllowedFileName($fileName)) {
            throw new FileNotFoundException($fileName);
        }

        return $this->directory().DIRECTORY_SEPARATOR.$fileName;
    }

    public function directory(): string
    {
        return config('filesystems.disks.'.self::DISK.'.root', storage_path('app/backups/database'));
    }

    public function humanReadableSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $size = $bytes / 1024;

        foreach ($units as $unit) {
            if ($size < 1024) {
                return number_format($size, 2).' '.$unit;
            }

            $size /= 1024;
        }

        return number_format($size, 2).' PB';
    }

    private function ensureDirectoryExists(): void
    {
        File::ensureDirectoryExists($this->directory(), 0750);
    }

    private function newBackupFileName(): string
    {
        return 'database-backup-'.CarbonImmutable::now()->format('Y-m-d-H-i-s').'.zip';
    }

    private function formatFile(string $path): array
    {
        $createdAt = File::lastModified($path)
            ? CarbonImmutable::createFromTimestamp(File::lastModified($path))
            : null;
        $size = File::size($path);

        return [
            'file_name' => basename($path),
            'path' => $path,
            'created_at' => $createdAt,
            'size' => $size,
            'size_for_humans' => $this->humanReadableSize($size),
        ];
    }

    private function isAllowedFileName(string $fileName): bool
    {
        return preg_match('/\Adatabase-backup-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}(-\d{2})?\.(zip|sql)\z/', $fileName) === 1;
    }

    private function logBackupActivity(User $user, string $action, string $severity, array $values): void
    {
        $this->activityLogger->log([
            'user_id' => $user->id,
            'category' => 'backups',
            'action' => $action,
            'description' => $action,
            'new_values' => $values,
            'severity' => $severity,
        ]);
    }
}
