<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Hall;
use App\Models\Lecturer;
use App\Models\ScheduleImportRow;
use App\Models\SubjectSectionScheduleSlot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WeeklyScheduleSlotConflictDetector
{
    /**
     * Finds slots participating in real weekly conflicts without comparing the
     * full timetable pair-by-pair. Slots are grouped by weekday and resource,
     * then scanned in start-time order. Placeholder resources are omitted.
     *
     * @param  array<int, int>  $excludedSlotIds
     * @return array<int, int>
     */
    public function affectedSlotIds(AcademicTerm $term, ?int $importBatchId = null, array $excludedSlotIds = []): array
    {
        $slots = DB::table('subject_section_schedule_slots as slots')
            ->leftJoin('lecturers', 'lecturers.id', '=', 'slots.lecturer_id')
            ->leftJoin('halls', 'halls.id', '=', 'slots.hall_id')
            ->where('slots.academic_term_id', $term->id)
            ->when($importBatchId, fn ($query, int $batchId) => $query->where('slots.import_batch_id', $batchId))
            ->when($excludedSlotIds !== [], fn ($query) => $query->whereNotIn('slots.id', $excludedSlotIds))
            ->whereBetween('slots.weekday', [1, 7])
            ->whereNotNull('slots.start_time')
            ->whereNotNull('slots.end_time')
            ->whereColumn('slots.start_time', '<', 'slots.end_time')
            ->get([
                'slots.id',
                'slots.weekday',
                'slots.start_time',
                'slots.end_time',
                'slots.subject_section_id',
                'slots.lecturer_id',
                'slots.hall_id',
                'lecturers.placeholder_type as lecturer_placeholder_type',
                'halls.placeholder_type as hall_placeholder_type',
            ]);
        $groups = [];

        foreach ($slots as $slot) {
            $keys = ["{$slot->weekday}|section|{$slot->subject_section_id}"];

            if ($slot->lecturer_id && $slot->lecturer_placeholder_type === null) {
                $keys[] = "{$slot->weekday}|lecturer|{$slot->lecturer_id}";
            }

            if ($slot->hall_id && $slot->hall_placeholder_type === null) {
                $keys[] = "{$slot->weekday}|hall|{$slot->hall_id}";
            }

            foreach ($keys as $key) {
                $groups[$key][] = $slot;
            }
        }

        $affected = [];

        foreach ($groups as $group) {
            usort($group, fn (object $left, object $right): int => [(string) $left->start_time, (int) $left->id] <=> [(string) $right->start_time, (int) $right->id]);
            $active = [];

            foreach ($group as $slot) {
                $active = array_values(array_filter($active, fn (object $candidate): bool => (string) $candidate->end_time > (string) $slot->start_time));

                foreach ($active as $candidate) {
                    $affected[(int) $candidate->id] = true;
                    $affected[(int) $slot->id] = true;
                }

                $active[] = $slot;
            }
        }

        $ids = array_keys($affected);
        sort($ids);

        return $ids;
    }

    public function exactSlot(ScheduleImportRow $row, array $candidate, bool $lock = false): ?SubjectSectionScheduleSlot
    {
        return SubjectSectionScheduleSlot::query()
            ->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())
            ->where('academic_term_id', $row->academic_term_id)
            ->where('subject_section_id', $candidate['subject_section_id'])
            ->where('weekday', $candidate['weekday'])
            ->where('start_time', $candidate['start_time'])
            ->where('end_time', $candidate['end_time'])
            ->first();
    }

    /** @return array<int, array<string, mixed>> */
    public function conflicts(ScheduleImportRow $row, array $candidate, ?int $excludeSlotId = null, bool $lock = false): array
    {
        $base = SubjectSectionScheduleSlot::query()
            ->with(['subject', 'subjectSection', 'lecturer', 'hall'])
            ->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())
            ->where('academic_term_id', $row->academic_term_id)
            ->where('weekday', $candidate['weekday'])
            ->where('start_time', '<', $candidate['end_time'])
            ->where('end_time', '>', $candidate['start_time'])
            ->when($excludeSlotId, fn (Builder $query, int $id): Builder => $query->where('id', '!=', $id));

        $conflicts = collect();
        $this->append($conflicts, (clone $base)->where('subject_section_id', $candidate['subject_section_id'])->get(), 'section');

        if (($candidate['lecturer_id'] ?? null) && ! $this->lecturerIsPlaceholder((int) $candidate['lecturer_id'])) {
            $this->append($conflicts, (clone $base)->where('lecturer_id', $candidate['lecturer_id'])->get(), 'lecturer');
        }

        if (($candidate['hall_id'] ?? null) && ! $this->hallIsPlaceholder((int) $candidate['hall_id'])) {
            $this->append($conflicts, (clone $base)->where('hall_id', $candidate['hall_id'])->get(), 'hall');
        }

        return $conflicts->unique(fn (array $item): string => $item['type'].'|'.$item['slot_id'])->values()->all();
    }

    private function lecturerIsPlaceholder(int $lecturerId): bool
    {
        return Lecturer::query()->find($lecturerId)?->isPlaceholder() ?? false;
    }

    private function hallIsPlaceholder(int $hallId): bool
    {
        return Hall::withTrashed()->find($hallId)?->isPlaceholder() ?? false;
    }

    public function message(array $conflicts): string
    {
        return collect($conflicts)->map(function (array $conflict): string {
            return __('schedule-import-reconciliation.validation.slot_conflict', [
                'type' => __('schedule-import-reconciliation.conflict_types.'.$conflict['type']),
                'subject' => $conflict['subject'],
                'section' => $conflict['section'],
                'weekday' => $conflict['weekday_label'],
                'start' => substr((string) $conflict['start_time'], 0, 5),
                'end' => substr((string) $conflict['end_time'], 0, 5),
                'lecturer' => $conflict['lecturer'],
                'hall' => $conflict['hall'],
            ]);
        })->implode("\n");
    }

    /** @param Collection<int, array<string, mixed>> $target */
    private function append(Collection $target, Collection $slots, string $type): void
    {
        foreach ($slots as $slot) {
            $target->push([
                'type' => $type,
                'slot_id' => $slot->id,
                'subject' => $slot->subject->name ?? __('weekly-schedule.not_specified'),
                'section' => $slot->subjectSection->code ?? __('weekly-schedule.not_specified'),
                'weekday' => $slot->weekday,
                'weekday_label' => __('weekly-schedule.weekdays')[$slot->weekday] ?? (string) $slot->weekday,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'lecturer' => optional($slot->lecturer)->name ?? __('weekly-schedule.not_specified'),
                'hall' => optional($slot->hall)->name ?? __('weekly-schedule.not_specified'),
            ]);
        }
    }
}
