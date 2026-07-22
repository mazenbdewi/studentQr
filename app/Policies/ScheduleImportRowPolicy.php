<?php

namespace App\Policies;

use App\Models\ScheduleImportRow;
use App\Models\User;

class ScheduleImportRowPolicy
{
    public const VIEW = 'view schedule-import reconciliation';

    public const RETRY = 'retry schedule-import rows';

    public const EXPORT = 'export schedule-import reconciliation';

    public const PREVIEW_BLOCKED_WEEKLY_SLOT_RECONCILIATION = 'preview blocked weekly slot reconciliation';

    public const RECONCILE_BLOCKED_WEEKLY_SLOTS = 'reconcile blocked weekly slots';

    public const CREATE_LECTURER_IDENTITY_FROM_SOURCE = 'create lecturer identity from source';

    public const CHANGE_RECONCILED_LECTURER = 'change reconciled lecturer';

    public const CHANGE_RECONCILED_HALL = 'change reconciled hall';

    public const CHANGE_RECONCILED_WEEKLY_TIME = 'change reconciled weekly time';

    public const EXCLUDE_WEEKLY_SLOT_FROM_CURRENT_BATCH = 'exclude weekly slot from current batch';

    public const VIEW_RECONCILIATION_AUDIT_HISTORY = 'view reconciliation audit history';

    public const EXPORT_BLOCKED_WEEKLY_SLOT_REPORTS = 'export blocked weekly slot reports';

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

    public function previewBlockedWeeklySlotReconciliation(User $user): bool
    {
        return $user->can(self::PREVIEW_BLOCKED_WEEKLY_SLOT_RECONCILIATION);
    }

    public function reconcileBlockedWeeklySlots(User $user): bool
    {
        return $user->can(self::RECONCILE_BLOCKED_WEEKLY_SLOTS);
    }

    public function createLecturerIdentityFromSource(User $user): bool
    {
        return $user->can(self::CREATE_LECTURER_IDENTITY_FROM_SOURCE);
    }

    public function changeReconciledLecturer(User $user): bool
    {
        return $user->can(self::CHANGE_RECONCILED_LECTURER);
    }

    public function changeReconciledHall(User $user): bool
    {
        return $user->can(self::CHANGE_RECONCILED_HALL);
    }

    public function changeReconciledWeeklyTime(User $user): bool
    {
        return $user->can(self::CHANGE_RECONCILED_WEEKLY_TIME);
    }

    public function excludeWeeklySlotFromCurrentBatch(User $user): bool
    {
        return $user->can(self::EXCLUDE_WEEKLY_SLOT_FROM_CURRENT_BATCH);
    }

    public function viewReconciliationAuditHistory(User $user): bool
    {
        return $user->can(self::VIEW_RECONCILIATION_AUDIT_HISTORY);
    }

    public function exportBlockedWeeklySlotReports(User $user): bool
    {
        return $user->can(self::EXPORT_BLOCKED_WEEKLY_SLOT_REPORTS);
    }
}
