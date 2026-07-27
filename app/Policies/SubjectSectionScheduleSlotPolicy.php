<?php

namespace App\Policies;

use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;

class SubjectSectionScheduleSlotPolicy
{
    public const VIEW = 'view weekly schedule';

    public const VIEW_REPORTS = 'view weekly schedule reports';

    public const EXPORT_REPORTS = 'export weekly schedule reports';

    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::VIEW);
    }

    public function view(User $user, SubjectSectionScheduleSlot $slot): bool
    {
        return $user->can(self::VIEW);
    }

    public function viewReports(User $user): bool
    {
        return $user->can(self::VIEW_REPORTS);
    }

    public function export(User $user): bool
    {
        return $user->can(self::EXPORT_REPORTS);
    }
}
