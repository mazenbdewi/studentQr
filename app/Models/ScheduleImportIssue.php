<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleImportIssue extends Model
{
    public const SEVERITY_ERROR = 'error';

    public const SEVERITY_WARNING = 'warning';

    public const STATUS_UNRESOLVED = 'unresolved';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_IGNORED = 'ignored';

    public const STATUS_INTENTIONALLY_UNSCHEDULED = 'intentionally_unscheduled';

    public const STATUS_RETRY_FAILED = 'retry_failed';

    public const TYPE_SUBJECT_NOT_FOUND = 'subject_not_found';

    public const TYPE_SUBJECT_NOT_UNIQUE = 'subject_not_unique';

    public const TYPE_NON_AUTHORITATIVE_SUBJECT_CODE = 'non_authoritative_subject_code';

    public const TYPE_SECTION_NOT_FOUND = 'section_not_found';

    public const TYPE_ZERO_STUDENT_SUBJECT_MISSING = 'zero_student_subject_missing';

    public const TYPE_ZERO_STUDENT_SECTION_MISSING = 'zero_student_section_missing';

    public const TYPE_NO_WEEKLY_TIME = 'no_weekly_time';

    public const TYPE_LECTURER_MISSING = 'lecturer_missing';

    public const TYPE_LECTURER_AMBIGUOUS = 'lecturer_ambiguous';

    public const TYPE_HALL_MISSING = 'hall_missing';

    public const TYPE_DUPLICATE_CONFLICT = 'duplicate_conflict';

    public const TYPE_INVALID_WEEKDAY_TIME = 'invalid_weekday_time';

    public const TYPE_CORE_VALIDATION = 'core_validation';

    protected $fillable = [
        'schedule_import_row_id',
        'deduplication_key',
        'issue_type',
        'severity',
        'reason_ar',
        'suggested_matches',
        'resolved_subject_id',
        'resolved_subject_section_id',
        'resolution_status',
        'resolution_action',
        'resolution_note',
        'resolved_by',
        'resolved_at',
        'retry_result',
    ];

    protected $casts = [
        'suggested_matches' => 'array',
        'resolved_at' => 'datetime',
        'retry_result' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $issue): void {
            if (! in_array($issue->issue_type, self::issueTypes(), true)) {
                throw new \InvalidArgumentException('Unsupported schedule import issue type.');
            }

            if (! in_array($issue->severity, [self::SEVERITY_ERROR, self::SEVERITY_WARNING], true)) {
                throw new \InvalidArgumentException('Unsupported schedule import issue severity.');
            }

            if (! in_array($issue->resolution_status, self::statuses(), true)) {
                throw new \InvalidArgumentException('Unsupported schedule reconciliation status.');
            }
        });
    }

    public static function issueTypes(): array
    {
        return [
            self::TYPE_SUBJECT_NOT_FOUND,
            self::TYPE_SUBJECT_NOT_UNIQUE,
            self::TYPE_NON_AUTHORITATIVE_SUBJECT_CODE,
            self::TYPE_SECTION_NOT_FOUND,
            self::TYPE_ZERO_STUDENT_SUBJECT_MISSING,
            self::TYPE_ZERO_STUDENT_SECTION_MISSING,
            self::TYPE_NO_WEEKLY_TIME,
            self::TYPE_LECTURER_MISSING,
            self::TYPE_LECTURER_AMBIGUOUS,
            self::TYPE_HALL_MISSING,
            self::TYPE_DUPLICATE_CONFLICT,
            self::TYPE_INVALID_WEEKDAY_TIME,
            self::TYPE_CORE_VALIDATION,
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_UNRESOLVED,
            self::STATUS_RESOLVED,
            self::STATUS_IGNORED,
            self::STATUS_INTENTIONALLY_UNSCHEDULED,
            self::STATUS_RETRY_FAILED,
        ];
    }

    /** @return BelongsTo<ScheduleImportRow, $this> */
    public function importRow(): BelongsTo
    {
        return $this->belongsTo(ScheduleImportRow::class, 'schedule_import_row_id');
    }

    /** @return BelongsTo<Subject, $this> */
    public function resolvedSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'resolved_subject_id')->withTrashed();
    }

    /** @return BelongsTo<SubjectSection, $this> */
    public function resolvedSubjectSection(): BelongsTo
    {
        return $this->belongsTo(SubjectSection::class, 'resolved_subject_section_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by')->withTrashed();
    }

    /** @return HasMany<ScheduleImportIssueAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(ScheduleImportIssueAction::class);
    }
}
