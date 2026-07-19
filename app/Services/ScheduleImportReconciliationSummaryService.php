<?php

namespace App\Services;

use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportIssueAction;
use App\Models\ScheduleImportRow;
use Illuminate\Database\Eloquent\Builder;

class ScheduleImportReconciliationSummaryService
{
    /** @return array<string, int> */
    public function forBatch(int $batchId): array
    {
        $issues = ScheduleImportIssue::query()->whereHas('importRow', fn (Builder $query): Builder => $query->where('import_batch_id', $batchId));
        $unresolved = fn (Builder $query): Builder => $query->whereIn('resolution_status', [
            ScheduleImportIssue::STATUS_UNRESOLVED,
            ScheduleImportIssue::STATUS_RETRY_FAILED,
        ]);

        $createdSlotIds = ScheduleImportIssueAction::query()
            ->whereHas('issue.importRow', fn (Builder $query): Builder => $query->where('import_batch_id', $batchId))
            ->get(['result'])
            ->flatMap(fn (ScheduleImportIssueAction $action): array => $action->result['created_slot_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->unique();

        return [
            'unresolved_subjects' => $this->countTypes($issues, ScheduleImportIssueWorkflow::SUBJECT_ISSUES, $unresolved),
            'unresolved_sections' => $this->countTypes($issues, ScheduleImportIssueWorkflow::SECTION_ISSUES, $unresolved),
            'rows_without_time' => $this->countTypes($issues, ScheduleImportIssueWorkflow::TIME_ISSUES, $unresolved),
            'missing_lecturers' => $this->countTypes($issues, ScheduleImportIssueWorkflow::LECTURER_ISSUES, $unresolved),
            'missing_halls' => $this->countTypes($issues, ScheduleImportIssueWorkflow::HALL_ISSUES, $unresolved),
            'conflicts' => $this->countTypes($issues, ScheduleImportIssueWorkflow::CONFLICT_ISSUES, $unresolved),
            'resolved_issues' => (clone $issues)->where('resolution_status', ScheduleImportIssue::STATUS_RESOLVED)->count(),
            'ignored_issues' => (clone $issues)->where('resolution_status', ScheduleImportIssue::STATUS_IGNORED)->count(),
            'intentionally_unscheduled_rows' => ScheduleImportRow::query()
                ->where('import_batch_id', $batchId)
                ->where('current_reconciliation_status', ScheduleImportRow::STATUS_INTENTIONALLY_UNSCHEDULED)
                ->count(),
            'reconciliation_created_slots' => $createdSlotIds->count(),
        ];
    }

    private function countTypes(Builder $issues, array $types, callable $scope): int
    {
        return (clone $issues)->whereIn('issue_type', $types)->where($scope)->count();
    }
}
