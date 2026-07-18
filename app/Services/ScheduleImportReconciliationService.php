<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportIssueAction;
use App\Models\ScheduleImportRow;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class ScheduleImportReconciliationService
{
    public function __construct(private readonly ScheduleImportRowRetryService $retryService) {}

    public function link(ScheduleImportIssue $issue, int $subjectId, int $sectionId, User $actor, ?string $note = null): ScheduleImportIssue
    {
        Gate::forUser($actor)->authorize('resolve', $issue);

        return DB::transaction(function () use ($issue, $subjectId, $sectionId, $actor, $note): ScheduleImportIssue {
            $locked = $this->lockIssue($issue);
            $subject = Subject::query()->withoutTrashed()->findOrFail($subjectId);
            $section = SubjectSection::query()->findOrFail($sectionId);

            if ($section->subject_id !== $subject->id || $section->academic_term_id !== $locked->importRow->academic_term_id) {
                throw new RuntimeException('الشعبة المختارة لا تنتمي للمقرر والفصل الدراسي المرتبطين.');
            }

            $before = $this->issueSnapshot($locked, $actor);
            $locked->update([
                'resolved_subject_id' => $subject->id,
                'resolved_subject_section_id' => $section->id,
                'resolution_status' => ScheduleImportIssue::STATUS_RESOLVED,
                'resolution_action' => ScheduleImportIssueAction::ACTION_LINK,
                'resolution_note' => $note,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
            ]);
            $this->recordAction($locked, $actor, ScheduleImportIssueAction::ACTION_LINK, $before, $note);
            $this->refreshRowAndSummary($locked->importRow);

            return $locked->fresh();
        });
    }

    public function ignore(ScheduleImportIssue $issue, User $actor, ?string $note = null): ScheduleImportIssue
    {
        Gate::forUser($actor)->authorize('ignore', $issue);

        return $this->changeStatus($issue, $actor, ScheduleImportIssue::STATUS_IGNORED, ScheduleImportIssueAction::ACTION_IGNORE, $note);
    }

    public function acknowledge(ScheduleImportIssue $issue, User $actor, ?string $note = null): ScheduleImportIssue
    {
        Gate::forUser($actor)->authorize('resolve', $issue);

        if ($issue->severity !== ScheduleImportIssue::SEVERITY_WARNING) {
            throw new RuntimeException('يمكن اعتماد التحذيرات فقط.');
        }

        return $this->changeStatus($issue, $actor, ScheduleImportIssue::STATUS_RESOLVED, ScheduleImportIssueAction::ACTION_ACKNOWLEDGE, $note);
    }

    public function intentionallyUnscheduled(ScheduleImportIssue $issue, User $actor, ?string $note = null): ScheduleImportIssue
    {
        Gate::forUser($actor)->authorize('resolve', $issue);

        if ($issue->issue_type !== ScheduleImportIssue::TYPE_NO_WEEKLY_TIME) {
            throw new RuntimeException('هذا الإجراء متاح فقط للمواد التي لا تملك موعداً أسبوعياً.');
        }

        return $this->changeStatus($issue, $actor, ScheduleImportIssue::STATUS_INTENTIONALLY_UNSCHEDULED, ScheduleImportIssueAction::ACTION_INTENTIONALLY_UNSCHEDULE, $note);
    }

    public function retry(ScheduleImportIssue $issue, User $actor, ?string $note = null): ScheduleImportIssue
    {
        $issue->loadMissing('importRow');
        Gate::forUser($actor)->authorize('retry', $issue->importRow);

        return DB::transaction(function () use ($issue, $actor, $note): ScheduleImportIssue {
            $locked = $this->lockIssue($issue);
            $before = $this->issueSnapshot($locked, $actor);

            try {
                $result = $this->retryService->retry($locked);
                $status = ($result['conflicts'] ?? []) === []
                    ? ScheduleImportIssue::STATUS_RESOLVED
                    : ScheduleImportIssue::STATUS_RETRY_FAILED;
            } catch (\Throwable $exception) {
                $result = ['status' => 'failed', 'error' => $exception->getMessage()];
                $status = ScheduleImportIssue::STATUS_RETRY_FAILED;
            }

            $locked->update([
                'resolution_status' => $status,
                'resolution_action' => ScheduleImportIssueAction::ACTION_RETRY,
                'resolution_note' => $note,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
                'retry_result' => $result,
            ]);
            $rowResult = $locked->importRow->import_result ?? [];
            $rowResult['reconciliation_slot_ids'] = array_values(array_unique(array_merge(
                $rowResult['reconciliation_slot_ids'] ?? [],
                $result['created_slot_ids'] ?? [],
                $result['already_existing_slot_ids'] ?? [],
            )));
            $locked->importRow->update(['import_result' => $rowResult]);
            $this->recordAction($locked, $actor, ScheduleImportIssueAction::ACTION_RETRY, $before, $note, $result);
            $this->refreshRowAndSummary($locked->importRow);

            return $locked->fresh();
        });
    }

    private function changeStatus(ScheduleImportIssue $issue, User $actor, string $status, string $action, ?string $note): ScheduleImportIssue
    {
        return DB::transaction(function () use ($issue, $actor, $status, $action, $note): ScheduleImportIssue {
            $locked = $this->lockIssue($issue);
            $before = $this->issueSnapshot($locked, $actor);
            $locked->update([
                'resolution_status' => $status,
                'resolution_action' => $action,
                'resolution_note' => $note,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
            ]);
            $this->recordAction($locked, $actor, $action, $before, $note);
            $this->refreshRowAndSummary($locked->importRow);

            return $locked->fresh();
        });
    }

    private function lockIssue(ScheduleImportIssue $issue): ScheduleImportIssue
    {
        $locked = ScheduleImportIssue::query()->lockForUpdate()->findOrFail($issue->id);
        $locked->load('importRow');
        ScheduleImportRow::query()->lockForUpdate()->findOrFail($locked->schedule_import_row_id);

        return $locked;
    }

    private function recordAction(ScheduleImportIssue $issue, User $actor, string $action, array $before, ?string $note, ?array $result = null): void
    {
        $after = $this->issueSnapshot($issue->fresh(), $actor);
        ScheduleImportIssueAction::query()->create([
            'schedule_import_issue_id' => $issue->id,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'previous_status' => $before['status'],
            'new_status' => $after['status'],
            'previous_subject_id' => $before['subject']['id'] ?? null,
            'previous_subject_section_id' => $before['section']['id'] ?? null,
            'selected_subject_id' => $after['subject']['id'] ?? null,
            'selected_subject_section_id' => $after['section']['id'] ?? null,
            'previous_state' => $before,
            'new_state' => $after,
            'result' => $result,
            'note' => $note,
            'performed_at' => now(),
        ]);
    }

    private function issueSnapshot(ScheduleImportIssue $issue, ?User $actor = null): array
    {
        $issue->loadMissing(['resolvedSubject', 'resolvedSubjectSection', 'importRow.academicTerm']);

        return [
            'issue_id' => $issue->id,
            'status' => $issue->resolution_status,
            'issue_type' => $issue->issue_type,
            'subject' => $issue->resolvedSubject ? [
                'id' => $issue->resolvedSubject->id,
                'code' => $issue->resolvedSubject->code,
                'name' => $issue->resolvedSubject->name,
            ] : null,
            'section' => $issue->resolvedSubjectSection ? [
                'id' => $issue->resolvedSubjectSection->id,
                'code' => $issue->resolvedSubjectSection->code,
                'academic_term_id' => $issue->resolvedSubjectSection->academic_term_id,
            ] : null,
            'academic_term' => [
                'id' => $issue->importRow->academicTerm->id,
                'display_name' => $issue->importRow->academicTerm->display_name,
            ],
            'actor' => $actor ? ['id' => $actor->id, 'name' => $actor->name] : null,
        ];
    }

    private function refreshRowAndSummary(ScheduleImportRow $row): void
    {
        $row->load('issues');
        $issues = $row->issues;
        $hasBlocking = $issues->contains(fn (ScheduleImportIssue $issue): bool => $issue->severity === ScheduleImportIssue::SEVERITY_ERROR
            && in_array($issue->resolution_status, [ScheduleImportIssue::STATUS_UNRESOLVED, ScheduleImportIssue::STATUS_RETRY_FAILED], true));
        $hasWarnings = $issues->contains(fn (ScheduleImportIssue $issue): bool => $issue->severity === ScheduleImportIssue::SEVERITY_WARNING
            && $issue->resolution_status === ScheduleImportIssue::STATUS_UNRESOLVED);
        $importResult = $row->import_result ?? [];
        $hasSlotEvidence = ($importResult['slot_ids'] ?? []) !== []
            || ($importResult['reconciliation_slot_ids'] ?? []) !== [];
        $mappedButNotRetried = ! $hasSlotEvidence
            && $issues->contains(fn (ScheduleImportIssue $issue): bool => $issue->resolved_subject_section_id !== null
                && $issue->resolution_status === ScheduleImportIssue::STATUS_RESOLVED);

        $status = $hasBlocking || $hasWarnings || $mappedButNotRetried
            ? ScheduleImportRow::STATUS_UNRESOLVED
            : ($issues->contains('resolution_status', ScheduleImportIssue::STATUS_INTENTIONALLY_UNSCHEDULED)
                ? ScheduleImportRow::STATUS_INTENTIONALLY_UNSCHEDULED
                : ($issues->every(fn (ScheduleImportIssue $issue): bool => $issue->resolution_status === ScheduleImportIssue::STATUS_IGNORED)
                    ? ScheduleImportRow::STATUS_IGNORED
                    : ScheduleImportRow::STATUS_RESOLVED));
        $row->update(['current_reconciliation_status' => $status]);

        $batch = ImportBatch::query()->findOrFail($row->import_batch_id);
        $summary = $batch->summary ?? [];
        $reconciliation = $summary['reconciliation'] ?? [];
        $batchIssues = ScheduleImportIssue::query()->whereHas('importRow', fn ($query) => $query->where('import_batch_id', $batch->id));
        $batchRows = ScheduleImportRow::query()->where('import_batch_id', $batch->id);
        $reconciliation['unresolved_errors'] = (clone $batchIssues)->where('severity', ScheduleImportIssue::SEVERITY_ERROR)->whereIn('resolution_status', [ScheduleImportIssue::STATUS_UNRESOLVED, ScheduleImportIssue::STATUS_RETRY_FAILED])->count();
        $reconciliation['resolved_errors'] = (clone $batchIssues)->where('severity', ScheduleImportIssue::SEVERITY_ERROR)->where('resolution_status', ScheduleImportIssue::STATUS_RESOLVED)->count();
        $reconciliation['ignored_rows'] = (clone $batchRows)->where('current_reconciliation_status', ScheduleImportRow::STATUS_IGNORED)->count();
        $reconciliation['intentionally_unscheduled_rows'] = (clone $batchRows)->where('current_reconciliation_status', ScheduleImportRow::STATUS_INTENTIONALLY_UNSCHEDULED)->count();
        $reconciliation['remaining_warnings'] = (clone $batchIssues)->where('severity', ScheduleImportIssue::SEVERITY_WARNING)->where('resolution_status', ScheduleImportIssue::STATUS_UNRESOLVED)->count();
        $reconciliation['newly_created_slots'] = ScheduleImportIssueAction::query()->whereHas('issue.importRow', fn ($query) => $query->where('import_batch_id', $batch->id))->get()->sum(fn (ScheduleImportIssueAction $action): int => count($action->result['created_slot_ids'] ?? []));
        $reconciliation['last_action_at'] = now()->toISOString();
        $summary['reconciliation'] = $reconciliation;
        $batch->update(['summary' => $summary]);
    }
}
