<?php

use App\Models\ImportBatch;
use App\Models\ScheduleImportRow;
use App\Models\SubjectSectionScheduleSlot;

require_once __DIR__.'/../Support/ScheduleImportReconciliationFixtures.php';

it('builds metadata for completed-with-errors and corrects the original filename idempotently', function (): void {
    $path = reconciliationWorkbook([
        ['SCH101', 'T', 1, null, null, 20, 18, 'مقرر الجدولة', null, null, null, '-', '-', '-'],
    ]);

    try {
        config(['filesystems.disks.local.root' => dirname($path)]);
        [, , $batch] = reconciliationSource($path, ImportBatch::STATUS_COMPLETED_WITH_ERRORS);
        $batch->update(['source_filename' => basename($path), 'source_file_path' => basename($path)]);

        $this->artisan('schedule-import-reconciliation:build', [
            '--batch' => $batch->uuid,
            '--original-filename' => 'summer2026_schedule.xlsx',
        ])->assertSuccessful();
        $this->artisan('schedule-import-reconciliation:build', [
            '--batch' => $batch->uuid,
            '--original-filename' => 'summer2026_schedule.xlsx',
        ])->assertSuccessful();

        expect($batch->fresh()->source_filename)->toBe('summer2026_schedule.xlsx')
            ->and($batch->fresh()->source_file_path)->toBe(basename($path))
            ->and(ScheduleImportRow::query()->count())->toBe(1)
            ->and(SubjectSectionScheduleSlot::query()->count())->toBe(0);
    } finally {
        @unlink($path);
    }
});

it('rejects failed and enrollment batches', function (): void {
    $path = reconciliationWorkbook([]);

    try {
        config(['filesystems.disks.local.root' => dirname($path)]);
        [, , $batch] = reconciliationSource($path, ImportBatch::STATUS_FAILED);
        $batch->update(['source_file_path' => basename($path)]);
        $this->artisan('schedule-import-reconciliation:build', ['--batch' => $batch->uuid])->assertFailed();

        $batch->update(['status' => ImportBatch::STATUS_COMPLETED, 'import_type' => ImportBatch::TYPE_ENROLLMENTS]);
        $this->artisan('schedule-import-reconciliation:build', ['--batch' => $batch->uuid])->assertFailed();
    } finally {
        @unlink($path);
    }
});
