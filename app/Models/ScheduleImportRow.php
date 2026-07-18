<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleImportRow extends Model
{
    public const ORIGINAL_IMPORTED = 'imported';

    public const ORIGINAL_PARTIALLY_IMPORTED = 'partially_imported';

    public const ORIGINAL_REJECTED = 'rejected';

    public const ORIGINAL_UNSCHEDULED = 'unscheduled';

    public const ORIGINAL_WARNING_ONLY = 'warning_only';

    public const STATUS_UNRESOLVED = 'unresolved';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_IGNORED = 'ignored';

    public const STATUS_INTENTIONALLY_UNSCHEDULED = 'intentionally_unscheduled';

    public const STATUS_RETRY_FAILED = 'retry_failed';

    protected $fillable = [
        'import_batch_id',
        'academic_term_id',
        'source_sheet_name',
        'source_row_number',
        'row_fingerprint',
        'source_payload',
        'normalized_payload',
        'original_import_status',
        'current_reconciliation_status',
        'import_result',
    ];

    protected $casts = [
        'source_row_number' => 'integer',
        'source_payload' => 'array',
        'normalized_payload' => 'array',
        'import_result' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $row): void {
            if (! in_array($row->original_import_status, self::originalStatuses(), true)) {
                throw new \InvalidArgumentException('Unsupported original schedule import row status.');
            }

            if (! in_array($row->current_reconciliation_status, self::reconciliationStatuses(), true)) {
                throw new \InvalidArgumentException('Unsupported current schedule reconciliation status.');
            }
        });
    }

    public static function originalStatuses(): array
    {
        return [
            self::ORIGINAL_IMPORTED,
            self::ORIGINAL_PARTIALLY_IMPORTED,
            self::ORIGINAL_REJECTED,
            self::ORIGINAL_UNSCHEDULED,
            self::ORIGINAL_WARNING_ONLY,
        ];
    }

    public static function reconciliationStatuses(): array
    {
        return [
            self::STATUS_UNRESOLVED,
            self::STATUS_RESOLVED,
            self::STATUS_IGNORED,
            self::STATUS_INTENTIONALLY_UNSCHEDULED,
            self::STATUS_RETRY_FAILED,
        ];
    }

    /** @return BelongsTo<ImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    /** @return BelongsTo<AcademicTerm, $this> */
    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    /** @return HasMany<ScheduleImportIssue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(ScheduleImportIssue::class);
    }
}
