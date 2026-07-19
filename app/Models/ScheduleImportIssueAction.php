<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleImportIssueAction extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_LINK = 'link';

    public const ACTION_LINK_SUBJECT = 'link_subject';

    public const ACTION_LINK_SECTION = 'link_section';

    public const ACTION_ASSIGN_WEEKLY_TIME = 'assign_weekly_time';

    public const ACTION_ASSIGN_LECTURER = 'assign_lecturer';

    public const ACTION_CREATE_LECTURER = 'create_lecturer_identity';

    public const ACTION_ASSIGN_HALL = 'assign_hall';

    public const ACTION_CREATE_HALL = 'create_hall';

    public const ACTION_RESOLVE_CONFLICT = 'resolve_conflict';

    public const ACTION_KEEP_UNRESOLVED = 'keep_unresolved';

    public const ACTION_IGNORE = 'ignore';

    public const ACTION_ACKNOWLEDGE = 'acknowledge';

    public const ACTION_INTENTIONALLY_UNSCHEDULE = 'intentionally_unschedule';

    public const ACTION_RETRY = 'retry';

    protected $fillable = [
        'schedule_import_issue_id',
        'actor_user_id',
        'action',
        'previous_status',
        'new_status',
        'previous_subject_id',
        'previous_subject_section_id',
        'selected_subject_id',
        'selected_subject_section_id',
        'previous_state',
        'new_state',
        'result',
        'note',
        'performed_at',
    ];

    protected $casts = [
        'previous_state' => 'array',
        'new_state' => 'array',
        'result' => 'array',
        'performed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $action): void {
            if (! in_array($action->action, [
                self::ACTION_LINK,
                self::ACTION_LINK_SUBJECT,
                self::ACTION_LINK_SECTION,
                self::ACTION_ASSIGN_WEEKLY_TIME,
                self::ACTION_ASSIGN_LECTURER,
                self::ACTION_CREATE_LECTURER,
                self::ACTION_ASSIGN_HALL,
                self::ACTION_CREATE_HALL,
                self::ACTION_RESOLVE_CONFLICT,
                self::ACTION_KEEP_UNRESOLVED,
                self::ACTION_IGNORE,
                self::ACTION_ACKNOWLEDGE,
                self::ACTION_INTENTIONALLY_UNSCHEDULE,
                self::ACTION_RETRY,
            ], true)) {
                throw new \InvalidArgumentException('Unsupported schedule reconciliation action.');
            }
        });
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    /** @return BelongsTo<ScheduleImportIssue, $this> */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(ScheduleImportIssue::class, 'schedule_import_issue_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withTrashed();
    }
}
