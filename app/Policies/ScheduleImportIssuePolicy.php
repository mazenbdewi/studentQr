<?php

namespace App\Policies;

use App\Models\ScheduleImportIssue;
use App\Models\User;

class ScheduleImportIssuePolicy
{
    public const RESOLVE = 'resolve schedule-import issues';

    public const IGNORE = 'ignore schedule-import issues';

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
}
