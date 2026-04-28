<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use App\Models\AuditLog;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('activity-logs:prune {--days=}', function () {
    /** @var ClosureCommand $this */
    $days = (int) ($this->option('days') ?: config('activity-log.retention_days', 180));
    $cutoff = Carbon::now()->subDays($days);

    $deleted = AuditLog::query()
        ->where('created_at', '<', $cutoff)
        ->delete();

    $this->info("Pruned {$deleted} activity logs older than {$days} days.");
})->purpose('Delete old activity logs based on the configured retention period.');
