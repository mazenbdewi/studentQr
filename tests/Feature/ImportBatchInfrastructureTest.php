<?php

use App\Models\AcademicTerm;
use App\Models\ImportBatch;
use Illuminate\Database\QueryException;

it('deduplicates batches independently of nullable source batch columns', function (): void {
    $fingerprint = hash('sha256', 'same-enrollment-file');
    $key = ImportBatch::deduplicationKey(ImportBatch::TYPE_ENROLLMENTS, $fingerprint);

    ImportBatch::query()->create([
        'deduplication_key' => $key,
        'import_type' => ImportBatch::TYPE_ENROLLMENTS,
        'source_filename' => 'enrollments.xlsx',
        'source_fingerprint' => $fingerprint,
        'source_import_batch_id' => null,
        'status' => ImportBatch::STATUS_COMPLETED,
        'imported_rows' => 10,
    ]);

    expect(fn () => ImportBatch::query()->create([
        'deduplication_key' => $key,
        'import_type' => ImportBatch::TYPE_ENROLLMENTS,
        'source_filename' => 'enrollments.xlsx',
        'source_fingerprint' => $fingerprint,
        'source_import_batch_id' => null,
        'status' => ImportBatch::STATUS_COMPLETED,
        'imported_rows' => 10,
    ]))->toThrow(QueryException::class);
});

it('includes the source enrollment batch in schedule deduplication identity', function (): void {
    $fingerprint = hash('sha256', 'same-schedule-file');

    expect(ImportBatch::deduplicationKey(ImportBatch::TYPE_WEEKLY_SCHEDULE, $fingerprint, 10))
        ->not->toBe(ImportBatch::deduplicationKey(ImportBatch::TYPE_WEEKLY_SCHEDULE, $fingerprint, 11));
});

it('allows only successful enrollment batches with imported rows as schedule sources', function (): void {
    $term = AcademicTerm::query()->create([
        'display_name' => 'الفصل الصيفي 2025/2026',
        'canonical_name' => 'الفصل الصيفي 2025/2026',
    ]);
    $eligible = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'eligible'),
        'import_type' => ImportBatch::TYPE_ENROLLMENTS,
        'status' => ImportBatch::STATUS_COMPLETED_WITH_ERRORS,
        'imported_rows' => 9,
    ]);
    $eligible->academicTerms()->attach($term->id, ['row_count' => 9]);
    ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'failed'),
        'import_type' => ImportBatch::TYPE_ENROLLMENTS,
        'status' => ImportBatch::STATUS_FAILED,
        'imported_rows' => 9,
    ]);
    ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'empty'),
        'import_type' => ImportBatch::TYPE_ENROLLMENTS,
        'status' => ImportBatch::STATUS_COMPLETED,
        'imported_rows' => 0,
    ]);

    expect(ImportBatch::query()->eligibleEnrollmentSource()->pluck('id')->all())
        ->toBe([$eligible->id]);
});
