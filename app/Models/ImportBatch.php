<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ImportBatch extends Model
{
    public const TYPE_ENROLLMENTS = 'enrollments';

    public const TYPE_WEEKLY_SCHEDULE = 'weekly_schedule';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'deduplication_key',
        'import_type',
        'source_filename',
        'source_file_path',
        'source_fingerprint',
        'source_import_batch_id',
        'status',
        'total_rows',
        'imported_rows',
        'rejected_rows',
        'summary',
        'error_file_path',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'summary' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_rows' => 'integer',
        'imported_rows' => 'integer',
        'rejected_rows' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $batch): void {
            $batch->uuid ??= (string) Str::uuid();
        });
    }

    public static function deduplicationKey(
        string $importType,
        ?string $sourceFingerprint,
        int|string|null $sourceImportBatchId = null,
        ?string $fallbackIdentity = null,
    ): string {
        $identity = $sourceFingerprint ?: $fallbackIdentity;

        if (blank($identity)) {
            throw new \InvalidArgumentException('A source fingerprint or fallback identity is required.');
        }

        return hash('sha256', implode('|', [
            $importType,
            (string) ($sourceImportBatchId ?? 'none'),
            $identity,
        ]));
    }

    public function scopeEligibleEnrollmentSource(Builder $query): Builder
    {
        return $query
            ->where('import_type', self::TYPE_ENROLLMENTS)
            ->whereIn('status', [self::STATUS_COMPLETED, self::STATUS_COMPLETED_WITH_ERRORS])
            ->where('imported_rows', '>', 0);
    }

    public function isEligibleEnrollmentSource(): bool
    {
        return $this->import_type === self::TYPE_ENROLLMENTS
            && in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_COMPLETED_WITH_ERRORS], true)
            && $this->imported_rows > 0;
    }

    /** @return BelongsTo<ImportBatch, $this> */
    public function sourceImportBatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_import_batch_id');
    }

    public function derivedImportBatches(): HasMany
    {
        return $this->hasMany(self::class, 'source_import_batch_id');
    }

    /** @return BelongsToMany<AcademicTerm, $this> */
    public function academicTerms(): BelongsToMany
    {
        return $this->belongsToMany(AcademicTerm::class, 'import_batch_academic_term')
            ->withPivot('row_count');
    }

    public function scheduleSlots(): HasMany
    {
        return $this->hasMany(SubjectSectionScheduleSlot::class);
    }

    /** @return HasMany<ScheduleImportRow, $this> */
    public function scheduleImportRows(): HasMany
    {
        return $this->hasMany(ScheduleImportRow::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
