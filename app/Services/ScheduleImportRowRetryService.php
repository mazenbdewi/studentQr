<?php

namespace App\Services;

use App\Models\ScheduleImportRow;
use App\Models\ScheduleImportRowTimeOverride;
use App\Models\SubjectSectionScheduleSlot;
use App\Support\WeeklyScheduleRowNormalizer;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use RuntimeException;

class ScheduleImportRowRetryService
{
    public function __construct(
        private readonly WeeklyScheduleRowNormalizer $normalizer,
        private readonly WeeklyScheduleSlotConflictDetector $conflictDetector,
    ) {}

    public function retryRow(ScheduleImportRow $row): array
    {
        $row->loadMissing(['resolvedSubject', 'resolvedSubjectSection', 'timeOverrides']);
        $subject = $row->resolvedSubject;
        $section = $row->resolvedSubjectSection;

        if (! $subject || ! $section) {
            throw new RuntimeException(__('schedule-import-reconciliation.validation.subject_section_required'));
        }

        if ((int) $section->subject_id !== (int) $subject->id || (int) $section->academic_term_id !== (int) $row->academic_term_id) {
            throw new RuntimeException(__('schedule-import-reconciliation.validation.section_scope'));
        }

        $lecturerResult = $this->fillIdentity($row, 'lecturer_id', $row->resolved_lecturer_id);
        $hallResult = $this->fillIdentity($row, 'hall_id', $row->resolved_hall_id);
        $candidates = $this->candidates($row);
        $created = [];
        $existing = [];
        $conflicts = [...$lecturerResult['conflicts'], ...$hallResult['conflicts']];

        foreach ($candidates as $candidate) {
            $exact = $this->conflictDetector->exactSlot($row, $candidate, lock: true);

            if ($exact) {
                $metadataConflicts = $this->metadataConflicts($exact, $candidate);

                if ($metadataConflicts !== []) {
                    $conflicts[] = [
                        'type' => 'metadata',
                        'slot_id' => $exact->id,
                        'fields' => $metadataConflicts,
                    ];

                    continue;
                }

                $this->fillSlotMetadata($exact, $candidate);
                $existing[] = $exact->id;

                continue;
            }

            $overlaps = $this->conflictDetector->conflicts($row, $candidate, lock: true);

            if ($overlaps !== []) {
                $conflicts = [...$conflicts, ...$overlaps];

                continue;
            }

            $slot = SubjectSectionScheduleSlot::query()->create([
                'import_batch_id' => $row->import_batch_id,
                'academic_term_id' => $row->academic_term_id,
                'subject_id' => $subject->id,
                'subject_section_id' => $section->id,
                'lecturer_id' => $candidate['lecturer_id'],
                'hall_id' => $candidate['hall_id'],
                'weekday' => $candidate['weekday'],
                'start_time' => $candidate['start_time'],
                'end_time' => $candidate['end_time'],
                'section_capacity' => $candidate['section_capacity'],
                'expected_student_count' => $candidate['expected_student_count'],
                'raw_teacher_name' => $row->normalized_payload['teacher_name_source'] ?? null,
                'raw_hall_name' => $row->normalized_payload['hall_name_source'] ?? null,
            ]);
            $created[] = $slot->id;
        }

        $importResult = $row->import_result ?? [];
        $importResult['reconciliation_slot_ids'] = collect([
            ...($importResult['reconciliation_slot_ids'] ?? []),
            ...$created,
            ...$existing,
        ])->map(fn (mixed $id): int => (int) $id)->unique()->values()->all();
        $row->update(['import_result' => $importResult]);

        return [
            'status' => $conflicts === [] ? ($created === [] ? 'already_applied' : 'completed') : 'conflict',
            'created_slot_ids' => $created,
            'already_existing_slot_ids' => $existing,
            'updated_lecturer_slot_ids' => $lecturerResult['updated_slot_ids'],
            'updated_hall_slot_ids' => $hallResult['updated_slot_ids'],
            'lecturer_conflicts' => $lecturerResult['conflicts'],
            'hall_conflicts' => $hallResult['conflicts'],
            'conflicts' => $conflicts,
            'slot_changes' => [...$lecturerResult['slot_changes'], ...$hallResult['slot_changes']],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function candidates(ScheduleImportRow $row): array
    {
        if ($row->timeOverrides->isNotEmpty()) {
            return $row->timeOverrides->map(fn (ScheduleImportRowTimeOverride $override): array => [
                'subject_section_id' => $row->resolved_subject_section_id,
                'weekday' => $override->weekday,
                'start_time' => $override->start_time,
                'end_time' => $override->end_time,
                'lecturer_id' => $override->lecturer_id ?? $row->resolved_lecturer_id,
                'hall_id' => $override->hall_id ?? $row->resolved_hall_id,
                'section_capacity' => $override->section_capacity ?? $row->resolved_section_capacity,
                'expected_student_count' => $override->expected_student_count ?? $row->resolved_expected_student_count,
            ])->all();
        }

        $candidates = [];

        foreach (($row->normalized_payload['weekday_values'] ?? []) as $weekday => $sourceTime) {
            if ($this->normalizer->isMissingValue($sourceTime)) {
                continue;
            }

            try {
                $time = $this->normalizer->parseTimeRange($sourceTime);
            } catch (\InvalidArgumentException $exception) {
                throw new RuntimeException($exception->getMessage(), previous: $exception);
            }

            if ($time) {
                $candidates[] = [
                    'subject_section_id' => $row->resolved_subject_section_id,
                    'weekday' => (int) $weekday,
                    ...$time,
                    'lecturer_id' => $row->resolved_lecturer_id,
                    'hall_id' => $row->resolved_hall_id,
                    'section_capacity' => $row->resolved_section_capacity ?? ($row->normalized_payload['section_capacity'] ?? null),
                    'expected_student_count' => $row->resolved_expected_student_count ?? ($row->normalized_payload['expected_student_count'] ?? null),
                ];
            }
        }

        if ($candidates === [] && ! $row->issues()->where('resolution_status', 'intentionally_unscheduled')->exists()) {
            throw new RuntimeException(__('schedule-import-reconciliation.validation.time_required'));
        }

        return $candidates;
    }

    private function fillIdentity(ScheduleImportRow $row, string $column, ?int $selectedId): array
    {
        if (! $selectedId) {
            return ['updated_slot_ids' => [], 'conflicts' => [], 'slot_changes' => []];
        }

        $updated = [];
        $conflicts = [];
        $changes = [];

        foreach ($this->relatedSlots($row) as $slot) {
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
                    $conflicts[] = ['slot_id' => $slot->id, 'field' => $column, 'overlaps' => $overlaps];

                    continue;
                }

                $before = $this->slotSnapshot($slot);
                $slot->update([$column => $selectedId]);
                $updated[] = $slot->id;
                $changes[] = ['before' => $before, 'after' => $this->slotSnapshot($slot->fresh())];
            } elseif ((int) $current !== $selectedId) {
                $conflicts[] = ['slot_id' => $slot->id, 'field' => $column, 'current_id' => (int) $current, 'selected_id' => $selectedId];
            }
        }

        return ['updated_slot_ids' => $updated, 'conflicts' => $conflicts, 'slot_changes' => $changes];
    }

    /** @return EloquentCollection<int, SubjectSectionScheduleSlot> */
    private function relatedSlots(ScheduleImportRow $row): EloquentCollection
    {
        $ids = $row->relatedScheduleSlotIds();

        if ($ids === []) {
            return new EloquentCollection;
        }

        $slots = SubjectSectionScheduleSlot::query()->whereIn('id', $ids)->where('academic_term_id', $row->academic_term_id)->lockForUpdate()->orderBy('id')->get();

        if ($slots->count() !== count($ids)) {
            throw new RuntimeException(__('schedule-import-reconciliation.validation.slot_relation_invalid'));
        }

        return $slots;
    }

    private function metadataConflicts(SubjectSectionScheduleSlot $slot, array $candidate): array
    {
        return collect(['lecturer_id', 'hall_id', 'section_capacity', 'expected_student_count'])
            ->filter(fn (string $field): bool => $slot->{$field} !== null && $candidate[$field] !== null && (int) $slot->{$field} !== (int) $candidate[$field])
            ->values()
            ->all();
    }

    private function fillSlotMetadata(SubjectSectionScheduleSlot $slot, array $candidate): void
    {
        $updates = [];

        foreach (['lecturer_id', 'hall_id', 'section_capacity', 'expected_student_count'] as $field) {
            if ($slot->{$field} === null && $candidate[$field] !== null) {
                $updates[$field] = $candidate[$field];
            }
        }

        if ($updates !== []) {
            $slot->update($updates);
        }
    }

    private function slotSnapshot(SubjectSectionScheduleSlot $slot): array
    {
        return [
            'id' => $slot->id,
            'lecturer_id' => $slot->lecturer_id,
            'hall_id' => $slot->hall_id,
            'weekday' => $slot->weekday,
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
        ];
    }
}
