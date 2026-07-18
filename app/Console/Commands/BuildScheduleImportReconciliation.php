<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Services\ScheduleImportReconciliationBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BuildScheduleImportReconciliation extends Command
{
    private const CURRENT_BATCH_UUID = 'a527f480-d2ec-48cb-8e17-1fbe97bf26ec';

    private const CURRENT_TERM = 'الفصل الصيفي 2025/2026';

    private const CURRENT_FINGERPRINT = '227d9c44bb0f257eca180314a3f6345691ec7c57befbe97a61cf9276036bd495';

    protected $signature = 'schedule-import-reconciliation:build
        {--batch= : Weekly schedule import batch UUID}
        {--original-filename= : Original client filename to store as display metadata}';

    protected $description = 'Build structured reconciliation metadata without rerunning a weekly schedule import';

    public function handle(ScheduleImportReconciliationBuilder $builder): int
    {
        $uuid = trim((string) $this->option('batch'));

        if ($uuid === '') {
            $this->error('The --batch option is required.');

            return self::FAILURE;
        }

        $batch = ImportBatch::query()->with(['academicTerms', 'sourceImportBatch.academicTerms'])->where('uuid', $uuid)->first();

        if (! $batch) {
            $this->error('Schedule import batch was not found.');

            return self::FAILURE;
        }

        $storedPath = $batch->source_file_path ?: $batch->source_filename;
        $disk = config('filament.default_filesystem_disk', config('filesystems.default'));
        $workbookPath = filled($storedPath) ? Storage::disk($disk)->path((string) $storedPath) : '';

        if ($uuid === self::CURRENT_BATCH_UUID) {
            if ($batch->source_fingerprint !== self::CURRENT_FINGERPRINT) {
                $this->error('The retained workbook fingerprint metadata does not match the approved current batch.');

                return self::FAILURE;
            }

            if ($batch->academicTerms->count() !== 1 || $batch->academicTerms->sole()->display_name !== self::CURRENT_TERM) {
                $this->error('The current batch is no longer linked to the approved academic term.');

                return self::FAILURE;
            }
        }

        try {
            $result = $builder->build(
                $batch,
                $workbookPath,
                filled($this->option('original-filename')) ? (string) $this->option('original-filename') : null,
                (string) $storedPath,
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Metric', 'Count'], collect($result)
            ->except('filename_correction')
            ->map(fn ($value, $key): array => [(string) $key, is_scalar($value) ? (string) $value : json_encode($value)])
            ->values()
            ->all());

        if (is_array($result['filename_correction'] ?? null)) {
            $this->info('Filename metadata corrected: '.$result['filename_correction']['previous'].' → '.$result['filename_correction']['current']);
        } else {
            $this->info('Filename metadata unchanged.');
        }

        return self::SUCCESS;
    }
}
