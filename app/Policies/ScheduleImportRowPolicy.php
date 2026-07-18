<?php

namespace App\Policies;

use App\Models\ScheduleImportRow;
use App\Models\User;

class ScheduleImportRowPolicy
{
    public const VIEW = 'view schedule-import reconciliation';

    public const RETRY = 'retry schedule-import rows';

    public const EXPORT = 'export schedule-import reconciliation';

    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::VIEW);
    }

    public function view(User $user, ScheduleImportRow $row): bool
    {
        return $user->can(self::VIEW);
    }

    public function retry(User $user, ScheduleImportRow $row): bool
    {
        return $user->can(self::RETRY);
    }

    public function export(User $user): bool
    {
        return $user->can(self::EXPORT);
    }
}
