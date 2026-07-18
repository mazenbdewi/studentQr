<?php

namespace App\Console\Commands;

use App\Models\AcademicTerm;
use App\Models\Enrollment;
use App\Models\ImportBatch;
use App\Support\AcademicTermNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RegisterExistingEnrollmentImportBatch extends Command
{
    protected $signature = 'import-batches:register-existing-enrollments
        {--term= : Existing academic-term display name}
        {--expected= : Exact enrollment count required before metadata is created}
        {--source= : Original enrollment workbook filename}';

    protected $description = 'Register a local-development enrollment import batch for existing term-aware data';

    public function handle(AcademicTermNormalizer $normalizer): int
    {
        if (app()->environment() !== 'local') {
            $this->error('This command is restricted to APP_ENV=local.');

            return self::FAILURE;
        }

        $canonicalTerm = $normalizer->canonicalName($this->option('term'));
        $expected = filter_var($this->option('expected'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $source = trim((string) $this->option('source'));

        if ($canonicalTerm === null || $expected !== 5813 || $source === '') {
            $this->error('The --term and --source options are required, and --expected must be exactly 5813.');

            return self::FAILURE;
        }

        $term = AcademicTerm::query()->where('canonical_name', $canonicalTerm)->first();

        if (! $term) {
            $this->error("Academic term '{$canonicalTerm}' does not exist.");

            return self::FAILURE;
        }

        $actual = Enrollment::query()->where('academic_term_id', $term->id)->count();

        if ($actual !== $expected) {
            $this->error("Expected {$expected} enrollments for '{$term->display_name}', found {$actual}. No metadata was created.");

            return self::FAILURE;
        }

        $deduplicationKey = ImportBatch::deduplicationKey(
            ImportBatch::TYPE_ENROLLMENTS,
            null,
            null,
            "legacy|{$term->canonical_name}|{$source}|{$expected}",
        );

        $existing = ImportBatch::query()->where('deduplication_key', $deduplicationKey)->first();

        if ($existing) {
            $this->info("Existing batch reused: {$existing->uuid}");

            return self::SUCCESS;
        }

        $batch = DB::transaction(function () use ($deduplicationKey, $source, $expected, $term): ImportBatch {
            $batch = ImportBatch::query()->create([
                'deduplication_key' => $deduplicationKey,
                'import_type' => ImportBatch::TYPE_ENROLLMENTS,
                'source_filename' => basename($source),
                'source_fingerprint' => null,
                'status' => ImportBatch::STATUS_COMPLETED,
                'total_rows' => $expected,
                'imported_rows' => $expected,
                'rejected_rows' => 0,
                'summary' => [
                    'registered_existing_data' => true,
                    'academic_term' => $term->display_name,
                    'imported_rows' => $expected,
                ],
                'started_at' => now(),
                'completed_at' => now(),
                'created_by' => null,
            ]);

            $batch->academicTerms()->attach($term->id, ['row_count' => $expected]);

            return $batch;
        });

        $this->info("Enrollment batch created: {$batch->uuid}");

        return self::SUCCESS;
    }
}
