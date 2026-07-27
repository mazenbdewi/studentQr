<?php

namespace App\Services;

use App\Models\ScheduleImportRow;
use App\Models\SubjectSectionScheduleSlot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WeeklyScheduleSlotConflictDetector
{
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

        if ($candidate['lecturer_id'] ?? null) {
            $this->append($conflicts, (clone $base)->where('lecturer_id', $candidate['lecturer_id'])->get(), 'lecturer');
        }

        if ($candidate['hall_id'] ?? null) {
            $this->append($conflicts, (clone $base)->where('hall_id', $candidate['hall_id'])->get(), 'hall');
        }

        return $conflicts->unique(fn (array $item): string => $item['type'].'|'.$item['slot_id'])->values()->all();
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
