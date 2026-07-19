<?php

namespace App\Services;

use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportRow;
use App\Models\Subject;
use App\Models\SubjectSectionScheduleSlot;
use App\Support\WeeklyScheduleRowNormalizer;
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

    public const HALL_ISSUES = [ScheduleImportIssue::TYPE_HALL_MISSING];

    public const CONFLICT_ISSUES = [ScheduleImportIssue::TYPE_DUPLICATE_CONFLICT];

    public const ZERO_STUDENT_ISSUES = [
        ScheduleImportIssue::TYPE_ZERO_STUDENT_SUBJECT_MISSING,
        ScheduleImportIssue::TYPE_ZERO_STUDENT_SECTION_MISSING,
    ];

    public function __construct(private readonly WeeklyScheduleRowNormalizer $normalizer) {}

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
        if ($row->resolved_subject_id) {
            return Subject::withTrashed()->find($row->resolved_subject_id);
        }

        $legacySubjectIds = $row->issues()
            ->whereNotNull('resolved_subject_id')
            ->pluck('resolved_subject_id')
            ->unique();

        if ($legacySubjectIds->count() === 1) {
            return Subject::withTrashed()->find($legacySubjectIds->sole());
        }

        $slotSubjectIds = SubjectSectionScheduleSlot::query()
            ->whereIn('id', $row->relatedScheduleSlotIds())
            ->pluck('subject_id')
            ->unique();

        if ($slotSubjectIds->count() === 1) {
            return Subject::withTrashed()->find($slotSubjectIds->sole());
        }

        $sourceKey = (string) ($row->normalized_payload['subject_code_key'] ?? '');

        if ($sourceKey === '') {
            return null;
        }

        $matches = Subject::query()->withoutTrashed()->get(['id', 'code', 'name', 'department_id'])
            ->filter(fn (Subject $subject): bool => $this->normalizer->normalizeKey($subject->code) === $sourceKey)
            ->values();

        return $matches->count() === 1 ? $matches->sole() : null;
    }

    public function subjectResolved(ScheduleImportRow $row): bool
    {
        return $this->subjectForRow($row) instanceof Subject;
    }

    public function sectionResolved(ScheduleImportRow $row): bool
    {
        if ($row->resolved_subject_section_id) {
            return true;
        }

        return SubjectSectionScheduleSlot::query()
            ->whereIn('id', $row->relatedScheduleSlotIds())
            ->pluck('subject_section_id')
            ->unique()
            ->count() === 1;
    }

    public function dependencyMessage(ScheduleImportRow $row, string $action): ?string
    {
        if ($action !== 'subject' && ! $this->subjectResolved($row)) {
            return __('schedule-import-reconciliation.dependencies.subject_first');
        }

        if (in_array($action, ['lecturer', 'hall', 'time', 'retry'], true) && ! $this->sectionResolved($row)) {
            return __('schedule-import-reconciliation.dependencies.section_first');
        }

        if ($action === 'time' && $this->hasUnresolvedIssue($row, self::LECTURER_ISSUES)) {
            return __('schedule-import-reconciliation.dependencies.lecturer_first');
        }

        if ($action === 'time' && $this->hasUnresolvedIssue($row, self::HALL_ISSUES)) {
            return __('schedule-import-reconciliation.dependencies.hall_first');
        }

        if ($action === 'retry' && $this->hasUnresolvedIssue($row, [
            ...self::SUBJECT_ISSUES,
            ...self::SECTION_ISSUES,
            ...self::LECTURER_ISSUES,
            ...self::HALL_ISSUES,
            ...self::TIME_ISSUES,
            ...self::CONFLICT_ISSUES,
        ])) {
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
