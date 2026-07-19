<?php

namespace App\Services;

use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\Lecturer;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportIssueAction;
use App\Models\ScheduleImportRow;
use App\Models\ScheduleImportRowTimeOverride;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Support\WeeklyScheduleRowNormalizer;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class ScheduleImportReconciliationService
{
    public function __construct(
        private readonly ScheduleImportRowRetryService $retryService,
        private readonly ScheduleImportIssueWorkflow $workflow,
        private readonly ScheduleImportRowResolutionContext $resolutionContext,
        private readonly WeeklyScheduleSlotConflictDetector $conflictDetector,
        private readonly ScheduleImportReconciliationSummaryService $summaryService,
        private readonly WeeklyScheduleRowNormalizer $normalizer,
    ) {}

    public function mapSubject(
        ScheduleImportRow $row,
        int $subjectId,
        int $sectionId,
        User $actor,
        ?string $note = null,
    ): ScheduleImportRow {
        $issue = $this->firstIssue($row, ScheduleImportIssueWorkflow::SUBJECT_ISSUES);
        Gate::forUser($actor)->authorize('resolveSubjectMapping', $issue);

        return $this->mapCatalog($row, $subjectId, $sectionId, $actor, ScheduleImportIssueWorkflow::SUBJECT_ISSUES, ScheduleImportIssueAction::ACTION_LINK_SUBJECT, $note);
    }

    public function mapSection(ScheduleImportRow $row, int $sectionId, User $actor, ?string $note = null): ScheduleImportRow
    {
        $issue = $this->firstIssue($row, ScheduleImportIssueWorkflow::SECTION_ISSUES);
        Gate::forUser($actor)->authorize('resolveSectionMapping', $issue);

        return DB::transaction(function () use ($row, $sectionId, $actor, $note): ScheduleImportRow {
            $locked = $this->lockRow($row);
            $subject = $this->ensureCanonicalSubject($locked);

            if (! $subject) {
                throw new RuntimeException(__('schedule-import-reconciliation.validation.subject_required'));
            }

            return $this->mapCatalogLocked(
                $locked,
                $subject->id,
                $sectionId,
                $actor,
                ScheduleImportIssueWorkflow::SECTION_ISSUES,
                ScheduleImportIssueAction::ACTION_LINK_SECTION,
                $note,
            );
        });
    }

    public function assignLecturer(ScheduleImportRow $row, int $lecturerId, User $actor, ?string $note = null): array
    {
        $issue = $this->firstAnyIssue($row, ScheduleImportIssueWorkflow::LECTURER_ISSUES);
        Gate::forUser($actor)->authorize('assignLecturer', $issue);

        return DB::transaction(function () use ($row, $lecturerId, $actor, $note): array {
            $locked = $this->lockRow($row);
            $this->canonicalCatalog($locked);
            $lecturer = Lecturer::query()->findOrFail($lecturerId);
            $before = $this->rowSnapshot($locked, $actor);
            $locked->update($this->resolutionUpdate($actor, ['resolved_lecturer_id' => $lecturer->id]));
            $result = $this->fillRelatedSlotIdentity($locked, 'lecturer_id', $lecturer->id);
            $issues = $this->setIssueStatuses(
                $locked,
                ScheduleImportIssueWorkflow::LECTURER_ISSUES,
                $result['conflicts'] === [] ? ScheduleImportIssue::STATUS_RESOLVED : ScheduleImportIssue::STATUS_RETRY_FAILED,
                ScheduleImportIssueAction::ACTION_ASSIGN_LECTURER,
                $actor,
                $note,
                $result,
                includeResolved: true,
            );
            $this->finishAction($locked, $issues, ScheduleImportIssueAction::ACTION_ASSIGN_LECTURER, $actor, $before, $result, $note);

            return $result;
        });
    }

    public function createLecturerIdentity(ScheduleImportRow $row, string $name, User $actor, ?string $note = null): array
    {
        $issue = $this->firstIssue($row, [ScheduleImportIssue::TYPE_LECTURER_MISSING]);
        Gate::forUser($actor)->authorize('createLecturerIdentity', $issue);
        $this->assertValidIdentity($name);

        return DB::transaction(function () use ($row, $name, $actor, $note): array {
            $locked = $this->lockRow($row);
            $this->canonicalCatalog($locked);
            $this->assertNoDifferentStoredIdentity($locked, 'lecturer_id');
            $key = $this->normalizer->normalizeKey($name);

            if (Lecturer::query()->get(['id', 'name', 'canonical_name'])->contains(
                fn (Lecturer $lecturer): bool => $this->normalizer->normalizeKey($lecturer->canonical_name ?: $lecturer->name) === $key,
            )) {
                throw new RuntimeException(__('schedule-import-reconciliation.validation.identity_already_exists'));
            }

            $lecturer = Lecturer::query()->create([
                'user_id' => null,
                'lecturer_id' => null,
                'name' => trim($name),
                'canonical_name' => $key,
                'email' => null,
                'is_active' => true,
            ]);
            $before = $this->rowSnapshot($locked, $actor);
            $locked->update($this->resolutionUpdate($actor, ['resolved_lecturer_id' => $lecturer->id]));
            $result = $this->fillRelatedSlotIdentity($locked, 'lecturer_id', $lecturer->id);
            $result['created_lecturer_id'] = $lecturer->id;
            $issues = $this->setIssueStatuses($locked, ScheduleImportIssueWorkflow::LECTURER_ISSUES, ScheduleImportIssue::STATUS_RESOLVED, ScheduleImportIssueAction::ACTION_CREATE_LECTURER, $actor, $note, $result);
            $this->finishAction($locked, $issues, ScheduleImportIssueAction::ACTION_CREATE_LECTURER, $actor, $before, $result, $note);

            return $result;
        });
    }

    public function assignHall(ScheduleImportRow $row, int $hallId, User $actor, ?string $note = null): array
    {
        $issue = $this->firstAnyIssue($row, ScheduleImportIssueWorkflow::HALL_ISSUES);
        Gate::forUser($actor)->authorize('assignHall', $issue);

        return DB::transaction(function () use ($row, $hallId, $actor, $note): array {
            $locked = $this->lockRow($row);
            $this->canonicalCatalog($locked);
            $hall = Hall::query()->withoutTrashed()->findOrFail($hallId);
            $before = $this->rowSnapshot($locked, $actor);
            $locked->update($this->resolutionUpdate($actor, ['resolved_hall_id' => $hall->id]));
            $result = $this->fillRelatedSlotIdentity($locked, 'hall_id', $hall->id);
            $issues = $this->setIssueStatuses(
                $locked,
                ScheduleImportIssueWorkflow::HALL_ISSUES,
                $result['conflicts'] === [] ? ScheduleImportIssue::STATUS_RESOLVED : ScheduleImportIssue::STATUS_RETRY_FAILED,
                ScheduleImportIssueAction::ACTION_ASSIGN_HALL,
                $actor,
                $note,
                $result,
                includeResolved: true,
            );
            $this->finishAction($locked, $issues, ScheduleImportIssueAction::ACTION_ASSIGN_HALL, $actor, $before, $result, $note);

            return $result;
        });
    }

    public function createHall(ScheduleImportRow $row, string $code, string $name, User $actor, ?string $note = null): array
    {
        $issue = $this->firstIssue($row, ScheduleImportIssueWorkflow::HALL_ISSUES);
        Gate::forUser($actor)->authorize('createHall', $issue);
        $this->assertValidIdentity($code);
        $this->assertValidIdentity($name);

        return DB::transaction(function () use ($row, $code, $name, $actor, $note): array {
            $locked = $this->lockRow($row);
            $this->canonicalCatalog($locked);
            $this->assertNoDifferentStoredIdentity($locked, 'hall_id');

            if (Hall::withTrashed()->where('code', trim($code))->exists()) {
                throw new RuntimeException(__('schedule-import-reconciliation.validation.hall_code_exists'));
            }

            $hall = Hall::query()->create([
                'code' => trim($code),
                'name' => trim($name),
                'floor' => null,
                'is_active' => true,
            ]);
            $before = $this->rowSnapshot($locked, $actor);
            $locked->update($this->resolutionUpdate($actor, ['resolved_hall_id' => $hall->id]));
            $result = $this->fillRelatedSlotIdentity($locked, 'hall_id', $hall->id);
            $result['created_hall_id'] = $hall->id;
            $issues = $this->setIssueStatuses($locked, ScheduleImportIssueWorkflow::HALL_ISSUES, ScheduleImportIssue::STATUS_RESOLVED, ScheduleImportIssueAction::ACTION_CREATE_HALL, $actor, $note, $result);
            $this->finishAction($locked, $issues, ScheduleImportIssueAction::ACTION_CREATE_HALL, $actor, $before, $result, $note);

            return $result;
        });
    }

    /** @param array<int, array<string, mixed>> $entries */
    public function addWeeklyTimes(ScheduleImportRow $row, array $entries, User $actor, ?string $note = null): array
    {
        $issue = $this->firstAnyIssue($row, ScheduleImportIssueWorkflow::TIME_ISSUES);
        Gate::forUser($actor)->authorize('assignWeeklyTime', $issue);

        return DB::transaction(function () use ($row, $entries, $actor, $note): array {
            $locked = $this->lockRow($row);
            $this->ensureOptionalIdentityIssues($locked);
            [$subject, $section] = $this->canonicalCatalog($locked);

            if (! $subject || ! $section) {
                throw new RuntimeException(__('schedule-import-reconciliation.validation.subject_section_required'));
            }

            if ($entries === []) {
                throw new RuntimeException(__('schedule-import-reconciliation.validation.time_required'));
            }

            $normalized = collect($entries)->map(fn (array $entry): array => $this->normalizeTimeEntry($locked, $section, $entry))->values();
            $keys = $normalized->map(fn (array $entry): string => implode('|', [$entry['weekday'], $entry['start_time'], $entry['end_time']]));

            if ($keys->unique()->count() !== $keys->count()) {
                throw new RuntimeException(__('schedule-import-reconciliation.validation.duplicate_time'));
            }

            foreach ($normalized as $entry) {
                if (ScheduleImportRowTimeOverride::query()->where([
                    'schedule_import_row_id' => $locked->id,
                    'weekday' => $entry['weekday'],
                    'start_time' => $entry['start_time'],
                    'end_time' => $entry['end_time'],
                ])->exists()) {
                    throw new RuntimeException(__('schedule-import-reconciliation.validation.duplicate_time'));
                }

                $exact = $this->conflictDetector->exactSlot($locked, $entry, lock: true);
                $conflicts = $this->conflictDetector->conflicts($locked, $entry, $exact?->id, lock: true);

                if ($conflicts !== []) {
                    throw new RuntimeException($this->conflictDetector->message($conflicts));
                }

                if ($exact && $this->metadataConflicts($exact, $entry) !== []) {
                    throw new RuntimeException(__('schedule-import-reconciliation.validation.exact_slot_metadata_conflict'));
                }
            }

            $before = $this->rowSnapshot($locked, $actor);
            $created = [];
            $existing = [];

            foreach ($normalized as $entry) {
                ScheduleImportRowTimeOverride::query()->create([
                    'schedule_import_row_id' => $locked->id,
                    'weekday' => $entry['weekday'],
                    'start_time' => $entry['start_time'],
                    'end_time' => $entry['end_time'],
                    'lecturer_id' => $entry['lecturer_id'],
                    'hall_id' => $entry['hall_id'],
                    'section_capacity' => $entry['section_capacity'],
                    'expected_student_count' => $entry['expected_student_count'],
                    'created_by' => $actor->id,
                ]);

                $slot = $this->conflictDetector->exactSlot($locked, $entry, lock: true);

                if ($slot) {
                    $this->fillSlotMetadata($slot, $entry);
                    $existing[] = $slot->id;
                } else {
                    $slot = SubjectSectionScheduleSlot::query()->create([
                        'import_batch_id' => $locked->import_batch_id,
                        'academic_term_id' => $locked->academic_term_id,
                        'subject_id' => $subject->id,
                        'subject_section_id' => $section->id,
                        'lecturer_id' => $entry['lecturer_id'],
                        'hall_id' => $entry['hall_id'],
                        'weekday' => $entry['weekday'],
                        'start_time' => $entry['start_time'],
                        'end_time' => $entry['end_time'],
                        'section_capacity' => $entry['section_capacity'],
                        'expected_student_count' => $entry['expected_student_count'],
                    ]);
                    $created[] = $slot->id;
                }
            }

            $this->appendReconciliationSlotIds($locked, [...$created, ...$existing]);
            $locked->update($this->resolutionUpdate($actor, [
                'resolved_section_capacity' => $normalized->first()['section_capacity'],
                'resolved_expected_student_count' => $normalized->first()['expected_student_count'],
            ]));
            $result = [
                'status' => $created === [] ? 'already_exists' : 'completed',
                'created_slot_ids' => $created,
                'already_existing_slot_ids' => $existing,
            ];
            $issues = $this->setIssueStatuses($locked, ScheduleImportIssueWorkflow::TIME_ISSUES, ScheduleImportIssue::STATUS_RESOLVED, ScheduleImportIssueAction::ACTION_ASSIGN_WEEKLY_TIME, $actor, $note, $result, includeResolved: true);
            $this->finishAction($locked, $issues, ScheduleImportIssueAction::ACTION_ASSIGN_WEEKLY_TIME, $actor, $before, $result, $note);

            return $result;
        });
    }

    public function ignore(ScheduleImportIssue $issue, User $actor, ?string $note = null): ScheduleImportIssue
    {
        Gate::forUser($actor)->authorize('ignore', $issue);

        return $this->changeIssueStatus($issue, $actor, ScheduleImportIssue::STATUS_IGNORED, ScheduleImportIssueAction::ACTION_IGNORE, $note);
    }

    public function acknowledge(ScheduleImportIssue $issue, User $actor, ?string $note = null): ScheduleImportIssue
    {
        Gate::forUser($actor)->authorize('resolve', $issue);

        if ($issue->severity !== ScheduleImportIssue::SEVERITY_WARNING) {
            throw new RuntimeException(__('schedule-import-reconciliation.validation.warning_only'));
        }

        return $this->changeIssueStatus($issue, $actor, ScheduleImportIssue::STATUS_RESOLVED, ScheduleImportIssueAction::ACTION_ACKNOWLEDGE, $note);
    }

    public function intentionallyUnscheduled(ScheduleImportIssue $issue, User $actor, ?string $note = null): ScheduleImportIssue
    {
        Gate::forUser($actor)->authorize('assignWeeklyTime', $issue);

        if ($issue->issue_type !== ScheduleImportIssue::TYPE_NO_WEEKLY_TIME) {
            throw new RuntimeException(__('schedule-import-reconciliation.validation.no_time_only'));
        }

        return $this->changeIssueStatus($issue, $actor, ScheduleImportIssue::STATUS_INTENTIONALLY_UNSCHEDULED, ScheduleImportIssueAction::ACTION_INTENTIONALLY_UNSCHEDULE, $note);
    }

    public function resolveConflict(ScheduleImportRow $row, string $decision, User $actor, ?string $note = null): array
    {
        $issue = $this->firstIssue($row, ScheduleImportIssueWorkflow::CONFLICT_ISSUES);
        Gate::forUser($actor)->authorize('resolveConflict', $issue);

        if (! in_array($decision, ['approve', 'ignore', 'keep'], true)) {
            throw new RuntimeException(__('schedule-import-reconciliation.validation.invalid_conflict_decision'));
        }

        return DB::transaction(function () use ($row, $decision, $actor, $note): array {
            $locked = $this->lockRow($row);
            $before = $this->rowSnapshot($locked, $actor);
            $status = match ($decision) {
                'approve' => ScheduleImportIssue::STATUS_RESOLVED,
                'ignore' => ScheduleImportIssue::STATUS_IGNORED,
                default => ScheduleImportIssue::STATUS_UNRESOLVED,
            };
            $action = $decision === 'keep' ? ScheduleImportIssueAction::ACTION_KEEP_UNRESOLVED : ScheduleImportIssueAction::ACTION_RESOLVE_CONFLICT;
            $payload = [...($locked->resolution_payload ?? []), 'duplicate_conflict_decision' => $decision];
            $locked->update($this->resolutionUpdate($actor, ['resolution_payload' => $payload]));
            $result = ['decision' => $decision, 'slot_changes' => []];
            $issues = $this->setIssueStatuses($locked, ScheduleImportIssueWorkflow::CONFLICT_ISSUES, $status, $action, $actor, $note, $result, includeResolved: true);
            $this->finishAction($locked, $issues, $action, $actor, $before, $result, $note);

            return $result;
        });
    }

    public function retryRow(ScheduleImportRow $row, User $actor, ?string $note = null): array
    {
        Gate::forUser($actor)->authorize('retry', $row);

        return DB::transaction(function () use ($row, $actor, $note): array {
            $locked = $this->lockRow($row);
            $this->ensureOptionalIdentityIssues($locked);
            [$subject, $section] = $this->canonicalCatalog($locked);

            if (! $subject || ! $section) {
                throw new RuntimeException(__('schedule-import-reconciliation.validation.subject_section_required'));
            }

            $before = $this->rowSnapshot($locked, $actor);
            $result = $this->retryService->retryRow($locked);
            $status = ($result['conflicts'] ?? []) === [] ? ScheduleImportIssue::STATUS_RESOLVED : ScheduleImportIssue::STATUS_RETRY_FAILED;
            $issues = $this->setIssueStatuses(
                $locked,
                ScheduleImportIssue::issueTypes(),
                $status,
                ScheduleImportIssueAction::ACTION_RETRY,
                $actor,
                $note,
                $result,
                includeResolved: true,
                onlySatisfied: true,
            );
            $this->finishAction($locked, $issues, ScheduleImportIssueAction::ACTION_RETRY, $actor, $before, $result, $note);

            return $result;
        });
    }

    /** Legacy API retained for existing callers. */
    public function link(ScheduleImportIssue $issue, int $subjectId, int $sectionId, User $actor, ?string $note = null): ScheduleImportIssue
    {
        $issue->loadMissing('importRow');

        if (in_array($issue->issue_type, ScheduleImportIssueWorkflow::SECTION_ISSUES, true)) {
            $row = DB::transaction(function () use ($issue, $subjectId, $sectionId, $actor, $note): ScheduleImportRow {
                Gate::forUser($actor)->authorize('resolveSectionMapping', $issue);
                $locked = $this->lockRow($issue->importRow);

                return $this->mapCatalogLocked($locked, $subjectId, $sectionId, $actor, ScheduleImportIssueWorkflow::SECTION_ISSUES, ScheduleImportIssueAction::ACTION_LINK_SECTION, $note);
            });
        } else {
            $row = $this->mapSubject($issue->importRow, $subjectId, $sectionId, $actor, $note);
        }

        return $row->issues()->findOrFail($issue->id);
    }

    /** Legacy API retained for existing callers. */
    public function retry(ScheduleImportIssue $issue, User $actor, ?string $note = null): ScheduleImportIssue
    {
        $issue->loadMissing('importRow');
        $this->retryRow($issue->importRow, $actor, $note);

        return $issue->fresh();
    }

    private function mapCatalog(ScheduleImportRow $row, int $subjectId, int $sectionId, User $actor, array $types, string $action, ?string $note): ScheduleImportRow
    {
        return DB::transaction(function () use ($row, $subjectId, $sectionId, $actor, $types, $action, $note): ScheduleImportRow {
            return $this->mapCatalogLocked($this->lockRow($row), $subjectId, $sectionId, $actor, $types, $action, $note);
        });
    }

    private function mapCatalogLocked(ScheduleImportRow $row, int $subjectId, int $sectionId, User $actor, array $types, string $action, ?string $note): ScheduleImportRow
    {
        $subject = Subject::query()->withoutTrashed()->findOrFail($subjectId);
        $section = SubjectSection::query()->findOrFail($sectionId);
        $this->assertSectionMatches($row, $subject, $section);
        $this->assertCatalogDoesNotConflictWithSlots($row, $subject, $section);
        $before = $this->rowSnapshot($row, $actor);
        $row->update($this->resolutionUpdate($actor, [
            'resolved_subject_id' => $subject->id,
            'resolved_subject_section_id' => $section->id,
            'resolved_section_capacity' => $row->resolved_section_capacity ?? ($row->normalized_payload['section_capacity'] ?? null),
            'resolved_expected_student_count' => $row->resolved_expected_student_count ?? ($row->normalized_payload['expected_student_count'] ?? null),
        ]));
        $issues = $this->setIssueStatuses($row, $types, ScheduleImportIssue::STATUS_RESOLVED, $action, $actor, $note);
        $this->finishAction($row, $issues, $action, $actor, $before, null, $note);

        return $row->fresh();
    }

    private function changeIssueStatus(ScheduleImportIssue $issue, User $actor, string $status, string $action, ?string $note): ScheduleImportIssue
    {
        return DB::transaction(function () use ($issue, $actor, $status, $action, $note): ScheduleImportIssue {
            $lockedRow = $this->lockRow($issue->importRow()->firstOrFail());
            $lockedIssue = $lockedRow->issues->firstWhere('id', $issue->id);

            if (! $lockedIssue) {
                throw new RuntimeException(__('schedule-import-reconciliation.validation.issue_not_found'));
            }

            $before = $this->rowSnapshot($lockedRow, $actor);
            $lockedIssue->update($this->issueUpdate($status, $action, $actor, $note));
            $this->finishAction($lockedRow, collect([$lockedIssue]), $action, $actor, $before, null, $note);

            return $lockedIssue->fresh();
        });
    }

    private function lockRow(ScheduleImportRow $row): ScheduleImportRow
    {
        $locked = ScheduleImportRow::query()->lockForUpdate()->findOrFail($row->id);
        $locked->setRelation('issues', $locked->issues()->lockForUpdate()->orderBy('id')->get());
        $locked->load(['resolvedSubject', 'resolvedSubjectSection', 'resolvedLecturer', 'resolvedHall', 'timeOverrides']);

        return $locked;
    }

    private function firstIssue(ScheduleImportRow $row, array $types): ScheduleImportIssue
    {
        $issue = $this->workflow->unresolvedIssues($row, $types)->first();

        if (! $issue) {
            throw new RuntimeException(__('schedule-import-reconciliation.validation.issue_not_found'));
        }

        return $issue;
    }

    private function firstAnyIssue(ScheduleImportRow $row, array $types): ScheduleImportIssue
    {
        $row->loadMissing('issues');
        $issue = $row->issues->whereIn('issue_type', $types)->first();

        if (! $issue) {
            throw new RuntimeException(__('schedule-import-reconciliation.validation.issue_not_found'));
        }

        return $issue;
    }

    private function ensureCanonicalSubject(ScheduleImportRow $row): ?Subject
    {
        $subject = $this->resolutionContext->effectiveSubject($row);

        if ($subject && ! $row->resolved_subject_id) {
            $row->update(['resolved_subject_id' => $subject->id]);
        }

        if ($subject) {
            $row->setRelation('resolvedSubject', $subject);
        }

        return $subject;
    }

    /** @return array{0: ?Subject, 1: ?SubjectSection} */
    private function canonicalCatalog(ScheduleImportRow $row): array
    {
        $subject = $this->ensureCanonicalSubject($row);
        $section = $this->resolutionContext->effectiveSubjectSection($row);

        if ($section) {
            if (! $row->resolved_subject_section_id) {
                $row->update(['resolved_subject_section_id' => $section->id]);
            }

            $row->setRelation('resolvedSubjectSection', $section);
        }

        return [$subject, $section];
    }

    private function assertSectionMatches(ScheduleImportRow $row, Subject $subject, SubjectSection $section): void
    {
        if ((int) $section->subject_id !== (int) $subject->id || (int) $section->academic_term_id !== (int) $row->academic_term_id) {
            throw new RuntimeException(__('schedule-import-reconciliation.validation.section_scope'));
        }

        $sourceType = $row->normalized_payload['section_type'] ?? null;
        $expected = match ($sourceType) {
            'T' => Subject::TYPE_THEORETICAL,
            'P' => Subject::TYPE_PRACTICAL,
            default => null,
        };

        if ($expected && $section->section_type !== $expected) {
            throw new RuntimeException(__('schedule-import-reconciliation.validation.section_type'));
        }
    }

    private function assertCatalogDoesNotConflictWithSlots(ScheduleImportRow $row, Subject $subject, SubjectSection $section): void
    {
        $conflict = $this->relatedSlots($row, lock: true)->first(fn (SubjectSectionScheduleSlot $slot): bool => (int) $slot->subject_id !== (int) $subject->id
            || (int) $slot->subject_section_id !== (int) $section->id);

        if ($conflict) {
            throw new RuntimeException(__('schedule-import-reconciliation.validation.catalog_slot_conflict'));
        }
    }

    /** @return array{updated_slot_ids: array<int, int>, already_applied_slot_ids: array<int, int>, conflicts: array<int, array<string, mixed>>, slot_changes: array<int, array<string, mixed>>} */
    private function fillRelatedSlotIdentity(ScheduleImportRow $row, string $column, int $selectedId): array
    {
        $updated = [];
        $already = [];
        $conflicts = [];
        $changes = [];

        foreach ($this->relatedSlots($row, lock: true) as $slot) {
            $current = $slot->{$column};

            if ($current === null) {
                $candidate = [
                    'subject_section_id' => $slot->subject_section_id,
                    'weekday' => $slot->weekday,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'lecturer_id' => $column === 'lecturer_id' ? $selectedId : $slot->lecturer_id,
                    'hall_id' => $column === 'hall_id' ? $selectedId : $slot->hall_id,
                ];
                $overlaps = $this->conflictDetector->conflicts($row, $candidate, $slot->id, lock: true);

                if ($overlaps !== []) {
                    $conflicts[] = ['slot_id' => $slot->id, 'field' => $column, 'selected_id' => $selectedId, 'overlaps' => $overlaps];

                    continue;
                }

                $before = $this->slotSnapshot($slot);
                $slot->update([$column => $selectedId]);
                $updated[] = $slot->id;
                $changes[] = ['before' => $before, 'after' => $this->slotSnapshot($slot->fresh())];
            } elseif ((int) $current === $selectedId) {
                $already[] = $slot->id;
            } else {
                $conflicts[] = [
                    'slot_id' => $slot->id,
                    'field' => $column,
                    'current_id' => (int) $current,
                    'selected_id' => $selectedId,
                ];
            }
        }

        return [
            'status' => $conflicts !== [] ? 'conflict' : ($updated === [] ? 'already_applied' : 'completed'),
            'updated_slot_ids' => $updated,
            'already_applied_slot_ids' => $already,
            'conflicts' => $conflicts,
            'slot_changes' => $changes,
        ];
    }

    private function assertNoDifferentStoredIdentity(ScheduleImportRow $row, string $column): void
    {
        if ($this->relatedSlots($row, lock: true)->contains(fn (SubjectSectionScheduleSlot $slot): bool => $slot->{$column} !== null)) {
            throw new RuntimeException(__('schedule-import-reconciliation.validation.existing_identity_must_be_selected'));
        }
    }

    private function normalizeTimeEntry(ScheduleImportRow $row, SubjectSection $section, array $entry): array
    {
        $weekday = (int) ($entry['weekday'] ?? 0);
        $start = substr((string) ($entry['start_time'] ?? ''), 0, 5);
        $end = substr((string) ($entry['end_time'] ?? ''), 0, 5);

        if ($weekday < 1 || $weekday > 7 || preg_match('/^\d{2}:\d{2}$/', $start) !== 1 || preg_match('/^\d{2}:\d{2}$/', $end) !== 1) {
            throw new RuntimeException(__('schedule-import-reconciliation.validation.invalid_time'));
        }

        if ($end <= $start) {
            throw new RuntimeException(__('schedule-import-reconciliation.validation.end_after_start'));
        }

        $lecturerId = filled($entry['lecturer_id'] ?? null)
            ? (int) $entry['lecturer_id']
            : $this->resolutionContext->effectiveLecturerId($row);
        $hallId = filled($entry['hall_id'] ?? null)
            ? (int) $entry['hall_id']
            : $this->resolutionContext->effectiveHallId($row);

        if ($lecturerId && ! Lecturer::query()->whereKey($lecturerId)->exists()) {
            throw new RuntimeException(__('schedule-import-reconciliation.validation.invalid_lecturer'));
        }

        if ($hallId && ! Hall::query()->withoutTrashed()->whereKey($hallId)->exists()) {
            throw new RuntimeException(__('schedule-import-reconciliation.validation.invalid_hall'));
        }

        return [
            'subject_section_id' => $section->id,
            'weekday' => $weekday,
            'start_time' => $start.':00',
            'end_time' => $end.':00',
            'lecturer_id' => $lecturerId,
            'hall_id' => $hallId,
            'section_capacity' => $this->nullableNonNegativeInteger($entry['section_capacity'] ?? $row->resolved_section_capacity),
            'expected_student_count' => $this->nullableNonNegativeInteger($entry['expected_student_count'] ?? $row->resolved_expected_student_count ?? ($row->normalized_payload['expected_student_count'] ?? null)),
        ];
    }

    private function nullableNonNegativeInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || (int) $value < 0 || (float) $value !== (float) (int) $value) {
            throw new RuntimeException(__('schedule-import-reconciliation.validation.non_negative_integer'));
        }

        return (int) $value;
    }

    /** @return array<int, string> */
    private function metadataConflicts(SubjectSectionScheduleSlot $slot, array $entry): array
    {
        return collect(['lecturer_id', 'hall_id', 'section_capacity', 'expected_student_count'])
            ->filter(fn (string $field): bool => $slot->{$field} !== null && $entry[$field] !== null && (int) $slot->{$field} !== (int) $entry[$field])
            ->values()
            ->all();
    }

    private function fillSlotMetadata(SubjectSectionScheduleSlot $slot, array $entry): void
    {
        $updates = [];

        foreach (['lecturer_id', 'hall_id', 'section_capacity', 'expected_student_count'] as $field) {
            if ($slot->{$field} === null && $entry[$field] !== null) {
                $updates[$field] = $entry[$field];
            }
        }

        if ($updates !== []) {
            $slot->update($updates);
        }
    }

    private function appendReconciliationSlotIds(ScheduleImportRow $row, array $slotIds): void
    {
        $result = $row->import_result ?? [];
        $result['reconciliation_slot_ids'] = collect([
            ...($result['reconciliation_slot_ids'] ?? []),
            ...$slotIds,
        ])->map(fn (mixed $id): int => (int) $id)->unique()->values()->all();
        $row->update(['import_result' => $result]);
    }

    /** @return EloquentCollection<int, SubjectSectionScheduleSlot> */
    private function relatedSlots(ScheduleImportRow $row, bool $lock = false): EloquentCollection
    {
        $ids = $row->relatedScheduleSlotIds();

        if ($ids === []) {
            return new EloquentCollection;
        }

        $query = SubjectSectionScheduleSlot::query()->whereIn('id', $ids)->where('academic_term_id', $row->academic_term_id);

        if ($lock) {
            $query->lockForUpdate();
        }

        $slots = $query->orderBy('id')->get();

        if ($slots->count() !== count($ids)) {
            throw new RuntimeException(__('schedule-import-reconciliation.validation.slot_relation_invalid'));
        }

        return $slots;
    }

    private function setIssueStatuses(
        ScheduleImportRow $row,
        array $types,
        string $status,
        string $action,
        User $actor,
        ?string $note,
        ?array $result = null,
        bool $includeResolved = false,
        bool $onlySatisfied = false,
    ): EloquentCollection {
        $issues = $row->issues
            ->whereIn('issue_type', $types)
            ->filter(fn (ScheduleImportIssue $issue): bool => $includeResolved || in_array($issue->resolution_status, [ScheduleImportIssue::STATUS_UNRESOLVED, ScheduleImportIssue::STATUS_RETRY_FAILED], true))
            ->filter(fn (ScheduleImportIssue $issue): bool => ! $onlySatisfied || $this->issueSatisfied($row, $issue, $result ?? []));

        foreach ($issues as $issue) {
            $issue->update([
                ...$this->issueUpdate($status, $action, $actor, $note),
                'retry_result' => $action === ScheduleImportIssueAction::ACTION_RETRY ? $result : $issue->retry_result,
            ]);
        }

        return new EloquentCollection($issues->all());
    }

    private function issueSatisfied(ScheduleImportRow $row, ScheduleImportIssue $issue, array $result): bool
    {
        return match (true) {
            in_array($issue->issue_type, ScheduleImportIssueWorkflow::SUBJECT_ISSUES, true) => $row->resolved_subject_id !== null && $row->resolved_subject_section_id !== null,
            in_array($issue->issue_type, ScheduleImportIssueWorkflow::SECTION_ISSUES, true) => $row->resolved_subject_section_id !== null,
            in_array($issue->issue_type, ScheduleImportIssueWorkflow::LECTURER_ISSUES, true) => $row->resolved_lecturer_id !== null && ($result['lecturer_conflicts'] ?? []) === [],
            in_array($issue->issue_type, ScheduleImportIssueWorkflow::HALL_ISSUES, true) => $row->resolved_hall_id !== null && ($result['hall_conflicts'] ?? []) === [],
            in_array($issue->issue_type, ScheduleImportIssueWorkflow::TIME_ISSUES, true) => $row->timeOverrides()->exists() || ($result['created_slot_ids'] ?? []) !== [] || ($result['already_existing_slot_ids'] ?? []) !== [],
            in_array($issue->issue_type, ScheduleImportIssueWorkflow::CONFLICT_ISSUES, true) => ($row->resolution_payload['duplicate_conflict_decision'] ?? null) === 'approve',
            default => false,
        };
    }

    private function finishAction(ScheduleImportRow $row, EloquentCollection|\Illuminate\Support\Collection $issues, string $action, User $actor, array $before, ?array $result, ?string $note): void
    {
        $this->refreshRowAndSummary($row);
        $after = $this->rowSnapshot($row->fresh(), $actor);

        foreach ($issues as $issue) {
            $previousIssue = collect($before['issues'])->firstWhere('id', $issue->id);
            ScheduleImportIssueAction::query()->create([
                'schedule_import_issue_id' => $issue->id,
                'actor_user_id' => $actor->id,
                'action' => $action,
                'previous_status' => $previousIssue['status'] ?? $issue->resolution_status,
                'new_status' => $issue->fresh()->resolution_status,
                'previous_subject_id' => $before['resolution']['subject']['id'] ?? null,
                'previous_subject_section_id' => $before['resolution']['section']['id'] ?? null,
                'selected_subject_id' => $after['resolution']['subject']['id'] ?? null,
                'selected_subject_section_id' => $after['resolution']['section']['id'] ?? null,
                'previous_state' => $before,
                'new_state' => $after,
                'result' => $result,
                'note' => $note,
                'performed_at' => now(),
            ]);
        }
    }

    private function rowSnapshot(ScheduleImportRow $row, User $actor): array
    {
        $row->load(['issues', 'resolvedSubject', 'resolvedSubjectSection', 'resolvedLecturer', 'resolvedHall', 'timeOverrides', 'academicTerm']);
        $lecturerResolution = $this->resolutionContext->effectiveLecturerResolution($row);
        $hallResolution = $this->resolutionContext->effectiveHallResolution($row);
        $lecturer = $this->resolutionContext->effectiveLecturer($row);
        $hall = $this->resolutionContext->effectiveHall($row);

        return [
            'row_id' => $row->id,
            'actor' => ['id' => $actor->id, 'name' => $actor->name],
            'academic_term' => ['id' => $row->academic_term_id, 'name' => $row->academicTerm?->display_name],
            'resolution' => [
                'subject' => $row->resolvedSubject ? ['id' => $row->resolvedSubject->id, 'code' => $row->resolvedSubject->code, 'name' => $row->resolvedSubject->name] : null,
                'section' => $row->resolvedSubjectSection ? ['id' => $row->resolvedSubjectSection->id, 'code' => $row->resolvedSubjectSection->code] : null,
                'lecturer' => $lecturer ? ['id' => $lecturer->id, 'name' => $lecturer->name, 'source' => $lecturerResolution['source']] : null,
                'hall' => $hall ? ['id' => $hall->id, 'code' => $hall->code, 'name' => $hall->name, 'source' => $hallResolution['source']] : null,
                'lecturer_context' => $lecturerResolution,
                'hall_context' => $hallResolution,
                'section_capacity' => $row->resolved_section_capacity,
                'expected_student_count' => $row->resolved_expected_student_count,
                'payload' => $row->resolution_payload,
            ],
            'time_overrides' => $row->timeOverrides->map(fn (ScheduleImportRowTimeOverride $override): array => [
                'id' => $override->id,
                'weekday' => $override->weekday,
                'start_time' => $override->start_time,
                'end_time' => $override->end_time,
                'lecturer_id' => $override->lecturer_id,
                'hall_id' => $override->hall_id,
                'section_capacity' => $override->section_capacity,
                'expected_student_count' => $override->expected_student_count,
            ])->all(),
            'related_slots' => $this->relatedSlots($row)->map(fn (SubjectSectionScheduleSlot $slot): array => $this->slotSnapshot($slot))->all(),
            'issues' => $row->issues->map(fn (ScheduleImportIssue $issue): array => [
                'id' => $issue->id,
                'type' => $issue->issue_type,
                'severity' => $issue->severity,
                'status' => $issue->resolution_status,
            ])->all(),
        ];
    }

    private function slotSnapshot(SubjectSectionScheduleSlot $slot): array
    {
        return [
            'id' => $slot->id,
            'subject_id' => $slot->subject_id,
            'subject_section_id' => $slot->subject_section_id,
            'weekday' => $slot->weekday,
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'lecturer_id' => $slot->lecturer_id,
            'hall_id' => $slot->hall_id,
            'section_capacity' => $slot->section_capacity,
            'expected_student_count' => $slot->expected_student_count,
        ];
    }

    private function refreshRowAndSummary(ScheduleImportRow $row): void
    {
        $row->load('issues');
        $issues = $row->issues;
        $hasOpenError = $issues->contains(fn (ScheduleImportIssue $issue): bool => $issue->severity === ScheduleImportIssue::SEVERITY_ERROR
            && in_array($issue->resolution_status, [ScheduleImportIssue::STATUS_UNRESOLVED, ScheduleImportIssue::STATUS_RETRY_FAILED], true));
        $hasOpenWarning = $issues->contains(fn (ScheduleImportIssue $issue): bool => $issue->severity === ScheduleImportIssue::SEVERITY_WARNING
            && in_array($issue->resolution_status, [ScheduleImportIssue::STATUS_UNRESOLVED, ScheduleImportIssue::STATUS_RETRY_FAILED], true));
        $hasSlotEvidence = $row->relatedScheduleSlotIds() !== [];
        $status = match (true) {
            $hasOpenError || $hasOpenWarning => ScheduleImportRow::STATUS_UNRESOLVED,
            $issues->contains('resolution_status', ScheduleImportIssue::STATUS_INTENTIONALLY_UNSCHEDULED) => ScheduleImportRow::STATUS_INTENTIONALLY_UNSCHEDULED,
            $issues->isNotEmpty() && $issues->every(fn (ScheduleImportIssue $issue): bool => $issue->resolution_status === ScheduleImportIssue::STATUS_IGNORED) => ScheduleImportRow::STATUS_IGNORED,
            $hasSlotEvidence => ScheduleImportRow::STATUS_RESOLVED,
            default => ScheduleImportRow::STATUS_UNRESOLVED,
        };
        $row->update(['current_reconciliation_status' => $status]);

        $batch = ImportBatch::query()->findOrFail($row->import_batch_id);
        $summary = $batch->summary ?? [];
        $summary['reconciliation'] = [
            ...($summary['reconciliation'] ?? []),
            ...$this->summaryService->forBatch($batch->id),
            'last_action_at' => now()->toISOString(),
        ];
        $batch->update(['summary' => $summary]);
    }

    private function ensureOptionalIdentityIssues(ScheduleImportRow $row): void
    {
        $resolutions = [
            'lecturer' => $this->resolutionContext->effectiveLecturerResolution($row),
            'hall' => $this->resolutionContext->effectiveHallResolution($row),
        ];
        $specifications = [
            'lecturer' => [
                ScheduleImportRowResolutionContext::STATUS_MISSING => [ScheduleImportIssue::TYPE_LECTURER_MISSING, 'تعذر مطابقة المدرس المصدر مع هوية مدرس موجودة.'],
                ScheduleImportRowResolutionContext::STATUS_AMBIGUOUS => [ScheduleImportIssue::TYPE_LECTURER_AMBIGUOUS, 'اسم المدرس يطابق أكثر من هوية.'],
            ],
            'hall' => [
                ScheduleImportRowResolutionContext::STATUS_MISSING => [ScheduleImportIssue::TYPE_HALL_MISSING, 'تعذر مطابقة القاعة المصدر مع قاعة موجودة.'],
                ScheduleImportRowResolutionContext::STATUS_AMBIGUOUS => [ScheduleImportIssue::TYPE_HALL_AMBIGUOUS, 'اسم القاعة يطابق أكثر من قاعة.'],
            ],
        ];
        $created = false;

        foreach ($resolutions as $identity => $resolution) {
            $specification = $specifications[$identity][$resolution['status']] ?? null;

            if (! $specification || $row->issues->contains('issue_type', $specification[0])) {
                continue;
            }

            ScheduleImportIssue::query()->firstOrCreate(
                ['deduplication_key' => hash('sha256', implode('|', ['effective-identity', $row->id, $specification[0]]))],
                [
                    'schedule_import_row_id' => $row->id,
                    'issue_type' => $specification[0],
                    'severity' => ScheduleImportIssue::SEVERITY_WARNING,
                    'reason_ar' => $specification[1],
                    'resolution_status' => ScheduleImportIssue::STATUS_UNRESOLVED,
                ],
            );
            $created = true;
        }

        if ($created) {
            $row->setRelation('issues', $row->issues()->orderBy('id')->get());
        }
    }

    private function resolutionUpdate(User $actor, array $values): array
    {
        return [
            ...$values,
            'resolution_updated_by' => $actor->id,
            'resolution_updated_at' => now(),
        ];
    }

    private function issueUpdate(string $status, string $action, User $actor, ?string $note): array
    {
        return [
            'resolution_status' => $status,
            'resolution_action' => $action,
            'resolution_note' => $note,
            'resolved_by' => $actor->id,
            'resolved_at' => now(),
        ];
    }

    private function assertValidIdentity(string $value): void
    {
        if ($this->normalizer->isMissingValue($value)) {
            throw new RuntimeException(__('schedule-import-reconciliation.validation.invalid_identity'));
        }
    }
}
