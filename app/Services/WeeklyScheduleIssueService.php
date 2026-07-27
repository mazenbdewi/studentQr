<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Hall;
use App\Models\Lecturer;
use App\Models\ScheduleImportRow;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use Illuminate\Support\Collection;

class WeeklyScheduleIssueService
{
    public function __construct(private readonly LectureSessionGenerationService $generation) {}

    public function result(AcademicTerm $term, ?int $importBatchId = null): WeeklyScheduleIssueResult
    {
        $preview = $this->generation->preview($term);
        $reasonsBySlot = [];

        foreach ($preview['blocked_slots'] as $blocked) {
            $slotId = (int) ($blocked['source_slot_id'] ?? 0);
            if ($slotId > 0) {
                $reasonsBySlot[$slotId] = array_values(array_unique([
                    ...($reasonsBySlot[$slotId] ?? []),
                    ...($blocked['reasons'] ?? []),
                ]));
            }
        }

        foreach ($preview['conflicts'] as $conflict) {
            foreach (['source_slot_id', 'conflicting_source_slot_id'] as $key) {
                $slotId = (int) ($conflict[$key] ?? 0);
                if ($slotId > 0) {
                    $reasonsBySlot[$slotId] = array_values(array_unique([
                        ...($reasonsBySlot[$slotId] ?? []),
                        (string) ($conflict['reason'] ?? 'scheduling_conflict'),
                    ]));
                }
            }
        }

        /** @var Collection<int, SubjectSectionScheduleSlot> $slots */
        $slots = SubjectSectionScheduleSlot::query()
            ->with(['subject.department.faculty', 'subjectSection', 'lecturer', 'hall', 'importBatch'])
            ->where('academic_term_id', $term->id)
            ->when($importBatchId, fn ($query) => $query->where('import_batch_id', $importBatchId))
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get();

        /** @var array<int, ScheduleImportRow> $sourceRows */
        $sourceRows = [];
        foreach (ScheduleImportRow::query()
            ->with(['resolutionUpdater', 'excludedFromWeeklyScheduleBy'])
            ->where('academic_term_id', $term->id)
            ->when($importBatchId, fn ($query) => $query->where('import_batch_id', $importBatchId))
            ->get() as $sourceRow) {
            foreach ($sourceRow->relatedScheduleSlotIds() as $slotId) {
                $sourceRows[$slotId] = $sourceRow;
            }
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = [];
        foreach ($slots as $slot) {
            $reasons = $reasonsBySlot[$slot->id] ?? [];
            $sourceRow = $sourceRows[$slot->id] ?? null;
            $excluded = $sourceRow instanceof ScheduleImportRow && $sourceRow->isExcludedFromWeeklySchedule();
            $subject = $slot->getRelation('subject');
            $section = $slot->getRelation('subjectSection');
            $lecturer = $slot->getRelation('lecturer');
            $hall = $slot->getRelation('hall');
            $department = $subject instanceof Subject ? $subject->getRelation('department') : null;
            $faculty = $department instanceof Department ? $department->getRelation('faculty') : null;
            $updater = $sourceRow instanceof ScheduleImportRow ? $sourceRow->getRelation('resolutionUpdater') : null;
            $exclusionUpdater = $sourceRow instanceof ScheduleImportRow ? $sourceRow->getRelation('excludedFromWeeklyScheduleBy') : null;

            $rows[] = [
                'slot_id' => $slot->id,
                'import_batch_id' => $slot->import_batch_id,
                'academic_term_id' => $slot->academic_term_id,
                'faculty' => $faculty instanceof Faculty ? $faculty->name : '—',
                'department' => $department instanceof Department ? $department->name : '—',
                'subject_code' => $subject instanceof Subject ? $subject->code : '—',
                'subject' => $subject instanceof Subject ? $subject->name : '—',
                'section' => $section instanceof SubjectSection ? $section->code : '—',
                'section_type' => $section instanceof SubjectSection ? $section->section_type : '—',
                'weekday' => __('weekly-schedule.weekdays')[$slot->weekday] ?? (string) $slot->weekday,
                'weekday_value' => $slot->weekday,
                'start_time' => substr((string) $slot->start_time, 0, 5),
                'end_time' => substr((string) $slot->end_time, 0, 5),
                'time' => substr((string) $slot->start_time, 0, 5).' - '.substr((string) $slot->end_time, 0, 5),
                'lecturer_id' => $slot->lecturer_id,
                'lecturer' => $lecturer instanceof Lecturer ? $lecturer->name : '—',
                'hall_id' => $slot->hall_id,
                'hall' => $hall instanceof Hall ? $hall->name : '—',
                'reasons' => $reasons,
                'status' => $excluded ? 'excluded' : ($reasons === [] ? 'resolved' : 'needs_attention'),
                'exclusion_note' => $sourceRow?->exclusion_note,
                'resolved_at' => $sourceRow?->resolution_updated_at?->toDateTimeString(),
                'updated_by' => $updater instanceof User ? $updater->name : ($exclusionUpdater instanceof User ? $exclusionUpdater->name : '—'),
            ];
        }

        $reasonCounts = collect($rows)
            ->reject(fn (array $row): bool => $row['status'] === 'excluded')
            ->flatMap(fn (array $row): array => $row['reasons'])
            ->countBy()
            ->map(fn (int $count): int => $count)
            ->all();

        $affected = collect($rows)->filter(fn (array $row): bool => $row['status'] === 'needs_attention');

        return new WeeklyScheduleIssueResult(
            academicTermId: $term->id,
            importBatchId: $importBatchId,
            preview: $preview,
            issues: $rows,
            uniqueAffectedSlots: $affected->count(),
            issueCountsByKey: $reasonCounts,
            readySlots: collect($rows)->where('status', 'resolved')->count(),
            excludedSlots: collect($rows)->where('status', 'excluded')->count(),
        );
    }

    /** @return array<string, mixed> */
    public function forTerm(AcademicTerm $term, ?int $importBatchId = null): array
    {
        return $this->result($term, $importBatchId)->toArray();
    }
}
