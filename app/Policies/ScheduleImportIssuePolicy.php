<?php

namespace App\Policies;

use App\Models\ScheduleImportIssue;
use App\Models\User;

class ScheduleImportIssuePolicy
{
    public const RESOLVE = 'resolve schedule-import issues';

    public const IGNORE = 'ignore schedule-import issues';

    public const RESOLVE_SUBJECT_MAPPING = 'resolve schedule-import subject mapping';

    public const RESOLVE_SECTION_MAPPING = 'resolve schedule-import section mapping';

    public const ASSIGN_WEEKLY_TIME = 'assign schedule-import weekly time';

    public const ASSIGN_LECTURER = 'assign schedule-import lecturer';

    public const CREATE_LECTURER_IDENTITY = 'create schedule-import lecturer identity';

    public const ASSIGN_HALL = 'assign schedule-import hall';

    public const CREATE_HALL = 'create schedule-import hall';

    public const RESOLVE_CONFLICT = 'resolve schedule-import conflict';

    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function resolve(User $user, ScheduleImportIssue $issue): bool
    {
        return $user->can(self::RESOLVE);
    }

    public function ignore(User $user, ScheduleImportIssue $issue): bool
    {
        return $user->can(self::IGNORE);
    }

    public function excludeFromBatchSchedule(User $user, ScheduleImportIssue $issue): bool
    {
        return $user->can(self::IGNORE);
    }

    public function resolveSubjectMapping(User $user, ScheduleImportIssue $issue): bool
    {
        return $user->can(self::RESOLVE_SUBJECT_MAPPING);
    }

    public function resolveSectionMapping(User $user, ScheduleImportIssue $issue): bool
    {
        return $user->can(self::RESOLVE_SECTION_MAPPING);
    }

    public function assignWeeklyTime(User $user, ScheduleImportIssue $issue): bool
    {
        return $user->can(self::ASSIGN_WEEKLY_TIME);
    }

    public function assignLecturer(User $user, ScheduleImportIssue $issue): bool
    {
        return $user->can(self::ASSIGN_LECTURER);
    }

    public function createLecturerIdentity(User $user, ScheduleImportIssue $issue): bool
    {
        return $user->can(self::CREATE_LECTURER_IDENTITY);
    }

    public function assignHall(User $user, ScheduleImportIssue $issue): bool
    {
        return $user->can(self::ASSIGN_HALL);
    }

    public function createHall(User $user, ScheduleImportIssue $issue): bool
    {
        return $user->can(self::CREATE_HALL);
    }

    public function resolveConflict(User $user, ScheduleImportIssue $issue): bool
    {
        return $user->can(self::RESOLVE_CONFLICT);
    }
}
