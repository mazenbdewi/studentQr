<?php

use App\Models\AcademicTerm;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Role;

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

it('clamps persisted import progress and reports one hundred percent on completion', function (): void {
    $batch = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'progress-payload'),
        'import_type' => ImportBatch::TYPE_ENROLLMENTS,
        'status' => ImportBatch::STATUS_PROCESSING,
        'total_rows' => 10,
        'processed_rows' => 14,
        'imported_rows' => 9,
        'rejected_rows' => 1,
    ]);

    expect($batch->progressPayload())->toMatchArray([
        'total_rows' => 10,
        'processed_rows' => 10,
        'percentage' => 100,
    ]);

    $batch->update([
        'status' => ImportBatch::STATUS_COMPLETED,
        'processed_rows' => 10,
        'completed_at' => now(),
    ]);

    expect($batch->fresh()->progressPayload()['percentage'])->toBe(100);
});

it('returns lightweight progress only to an authorized batch owner', function (): void {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $owner = User::factory()->create(['role' => 'admin', 'type' => 'admin', 'status' => 'active']);
    $owner->assignRole('admin');
    $other = User::factory()->create(['role' => 'admin', 'type' => 'admin', 'status' => 'active']);
    $other->assignRole('admin');
    $batch = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'progress-endpoint'),
        'import_type' => ImportBatch::TYPE_ENROLLMENTS,
        'status' => ImportBatch::STATUS_PROCESSING,
        'total_rows' => 200,
        'processed_rows' => 50,
        'imported_rows' => 48,
        'rejected_rows' => 2,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->getJson(route('admin.import-batches.progress', $batch, false))
        ->assertOk()
        ->assertExactJson($batch->progressPayload());

    $this->actingAs($other)
        ->getJson(route('admin.import-batches.progress', $batch, false))
        ->assertForbidden();
});

it('allows only successful enrollment batches with imported rows as schedule sources', function (): void {
    $term = AcademicTerm::query()->create([
        'display_name' => 'الفصل الصيفي 2025/2026',
        'canonical_name' => 'الفصل الصيفي 2025/2026',
    ]);
    $incomplete = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'eligible'),
        'import_type' => ImportBatch::TYPE_ENROLLMENTS,
        'status' => ImportBatch::STATUS_COMPLETED_WITH_ERRORS,
        'imported_rows' => 9,
    ]);
    $incomplete->academicTerms()->attach($term->id, ['row_count' => 9]);
    $eligible = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'completed'),
        'import_type' => ImportBatch::TYPE_ENROLLMENTS,
        'status' => ImportBatch::STATUS_COMPLETED,
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
