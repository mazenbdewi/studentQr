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

    public const MANAGE_HALL_METADATA = 'manage hall metadata';

    public const EXPORT_HALL_METADATA = 'export hall metadata';

    public const IMPORT_HALL_METADATA = 'import hall metadata';

    public const PREVIEW_HALL_METADATA_IMPORT = 'preview hall metadata import';

    public const PREVIEW_GROUPED_HALL_ASSIGNMENT = 'preview grouped hall assignment';

    public const CONFIRM_GROUPED_HALL_ASSIGNMENT_WITH_WARNING = 'confirm grouped hall assignment with warning';

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

    public function manageHallMetadata(User $user): bool
    {
        return $user->can(self::MANAGE_HALL_METADATA);
    }

    public function exportHallMetadata(User $user): bool
    {
        return $user->can(self::EXPORT_HALL_METADATA);
    }

    public function importHallMetadata(User $user): bool
    {
        return $user->can(self::IMPORT_HALL_METADATA);
    }

    public function previewHallMetadataImport(User $user): bool
    {
        return $user->can(self::PREVIEW_HALL_METADATA_IMPORT);
    }

    public function previewGroupedHallAssignment(User $user): bool
    {
        return $user->can(self::PREVIEW_GROUPED_HALL_ASSIGNMENT);
    }

    public function confirmGroupedHallAssignmentWithWarning(User $user): bool
    {
        return $user->can(self::CONFIRM_GROUPED_HALL_ASSIGNMENT_WITH_WARNING);
    }
}
