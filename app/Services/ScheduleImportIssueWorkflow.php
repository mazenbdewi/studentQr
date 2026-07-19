<?php

namespace App\Services;

use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportRow;
use App\Models\Subject;
use Illuminate\Support\Collection;

class ScheduleImportIssueWorkflow
{
    public const SUBJECT_ISSUES = [
        ScheduleImportIssue::TYPE_SUBJECT_NOT_FOUND,
        ScheduleImportIssue::TYPE_SUBJECT_NOT_UNIQUE,
        ScheduleImportIssue::TYPE_NON_AUTHORITATIVE_SUBJECT_CODE,
        ScheduleImportIssue::TYPE_ZERO_STUDENT_SUBJECT_MISSING,
    ];

    public const SECTION_ISSUES = [
        ScheduleImportIssue::TYPE_SECTION_NOT_FOUND,
        ScheduleImportIssue::TYPE_ZERO_STUDENT_SECTION_MISSING,
    ];

    public const TIME_ISSUES = [
        ScheduleImportIssue::TYPE_NO_WEEKLY_TIME,
        ScheduleImportIssue::TYPE_INVALID_WEEKDAY_TIME,
    ];

    public const LECTURER_ISSUES = [
        ScheduleImportIssue::TYPE_LECTURER_MISSING,
        ScheduleImportIssue::TYPE_LECTURER_AMBIGUOUS,
    ];

    public const HALL_ISSUES = [
        ScheduleImportIssue::TYPE_HALL_MISSING,
        ScheduleImportIssue::TYPE_HALL_AMBIGUOUS,
    ];

    public const CONFLICT_ISSUES = [ScheduleImportIssue::TYPE_DUPLICATE_CONFLICT];

    public const RETRY_BLOCKING_ISSUES = [
        ScheduleImportIssue::TYPE_CORE_VALIDATION,
        ScheduleImportIssue::TYPE_DUPLICATE_CONFLICT,
    ];

    public const ZERO_STUDENT_ISSUES = [
        ScheduleImportIssue::TYPE_ZERO_STUDENT_SUBJECT_MISSING,
        ScheduleImportIssue::TYPE_ZERO_STUDENT_SECTION_MISSING,
    ];

    public function __construct(private readonly ScheduleImportRowResolutionContext $resolutionContext) {}

    public function hasUnresolvedIssue(ScheduleImportRow $row, array $types): bool
    {
        return $this->unresolvedIssues($row, $types)->isNotEmpty();
    }

    /** @return Collection<int, ScheduleImportIssue> */
    public function unresolvedIssues(ScheduleImportRow $row, array $types = []): Collection
    {
        $row->loadMissing('issues');

        return $row->issues
            ->filter(fn (ScheduleImportIssue $issue): bool => in_array($issue->resolution_status, [
                ScheduleImportIssue::STATUS_UNRESOLVED,
                ScheduleImportIssue::STATUS_RETRY_FAILED,
            ], true))
            ->when($types !== [], fn (Collection $issues): Collection => $issues->whereIn('issue_type', $types))
            ->values();
    }

    public function subjectForRow(ScheduleImportRow $row): ?Subject
    {
        return $this->resolutionContext->effectiveSubject($row);
    }

    public function subjectResolved(ScheduleImportRow $row): bool
    {
        return $this->subjectForRow($row) instanceof Subject;
    }

    public function sectionResolved(ScheduleImportRow $row): bool
    {
        return $this->resolutionContext->effectiveSubjectSection($row) !== null;
    }

    public function dependencyMessage(ScheduleImportRow $row, string $action): ?string
    {
        if ($action === 'time' && $row->isExcludedFromWeeklySchedule()) {
            return __('schedule-import-reconciliation.dependencies.exclusion_review_required');
        }

        if ($action === 'exclude' && ($row->timeOverrides()->exists() || $row->relatedScheduleSlotIds() !== [])) {
            return __('schedule-import-reconciliation.dependencies.schedule_decision_review_required');
        }

        if ($action === 'exclude' && $this->hasUnresolvedIssue($row, [...self::SUBJECT_ISSUES, ...self::SECTION_ISSUES])) {
            return __('schedule-import-reconciliation.dependencies.resolve_catalog_issues_first');
        }

        if ($action !== 'subject' && ! $this->subjectResolved($row)) {
            return __('schedule-import-reconciliation.dependencies.subject_first');
        }

        if (in_array($action, ['lecturer', 'hall', 'time', 'exclude', 'retry'], true) && ! $this->sectionResolved($row)) {
            return __('schedule-import-reconciliation.dependencies.section_first');
        }

        if ($action === 'retry' && $this->hasUnresolvedIssue($row, self::RETRY_BLOCKING_ISSUES)) {
            return __('schedule-import-reconciliation.dependencies.resolve_issues_first');
        }

        if ($action === 'retry'
            && $this->hasUnresolvedIssue($row, self::TIME_ISSUES)
            && ! $row->timeOverrides()->exists()) {
            return __('schedule-import-reconciliation.dependencies.resolve_issues_first');
        }

        return null;
    }

    public function requiredActionLabel(ScheduleImportRow $row): string
    {
        return match (true) {
            $this->hasUnresolvedIssue($row, self::SUBJECT_ISSUES) => __('schedule-import-reconciliation.required_actions.subject'),
            $this->hasUnresolvedIssue($row, self::SECTION_ISSUES) => __('schedule-import-reconciliation.required_actions.section'),
            $this->hasUnresolvedIssue($row, self::LECTURER_ISSUES) => __('schedule-import-reconciliation.required_actions.lecturer'),
            $this->hasUnresolvedIssue($row, self::HALL_ISSUES) => __('schedule-import-reconciliation.required_actions.hall'),
            $this->hasUnresolvedIssue($row, self::TIME_ISSUES) => __('schedule-import-reconciliation.required_actions.time'),
            $this->hasUnresolvedIssue($row, self::CONFLICT_ISSUES) => __('schedule-import-reconciliation.required_actions.conflict'),
            default => __('schedule-import-reconciliation.required_actions.retry'),
        };
    }
}
