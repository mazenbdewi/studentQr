<?php

namespace App\Services;

use App\Models\LectureSessionGenerationRun;
use App\Models\ScheduleImportRow;
use App\Models\SubjectSectionScheduleSlot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BlockedWeeklySlotReportService
{
    /** @return array{run: ?LectureSessionGenerationRun, rows: array<int, array<string, mixed>>, conflicts: array<int, array<string, mixed>>, summary: array<string, int>} */
    public function latestReport(): array
    {
        return $this->reportForRun(
            LectureSessionGenerationRun::query()
                ->latest('id')
                ->first(),
        );
    }

    /** @return array{run: ?LectureSessionGenerationRun, rows: array<int, array<string, mixed>>, conflicts: array<int, array<string, mixed>>, summary: array<string, int>} */
    public function reportForRun(?LectureSessionGenerationRun $run): array
    {
        if (! $run instanceof LectureSessionGenerationRun) {
            return [
                'run' => null,
                'rows' => [],
                'conflicts' => [],
                'summary' => $this->emptySummary(),
            ];
        }

        $summary = $run->summary ?? [];
        $errorRows = is_array($summary['error_report'] ?? null) ? $summary['error_report'] : [];
        $blockedSlots = is_array($summary['blocked_slots'] ?? null) ? $summary['blocked_slots'] : [];
        $conflicts = is_array($summary['conflicts'] ?? null) ? $summary['conflicts'] : [];
        $slotIds = [];

        foreach ($errorRows as $errorRow) {
            if (! is_array($errorRow) || blank($errorRow['الموعد الأسبوعي المصدر'] ?? null)) {
                continue;
            }

            $slotIds[] = (int) $errorRow['الموعد الأسبوعي المصدر'];
        }

        $slotIds = array_values(array_unique($slotIds));

        /** @var Collection<int, SubjectSectionScheduleSlot> $slots */
        $slots = SubjectSectionScheduleSlot::query()
            ->with(['subject', 'subjectSection', 'lecturer', 'hall'])
            ->whereIn('id', $slotIds)
            ->get()
            ->keyBy('id');

        $blockedBySlot = [];
        foreach ($blockedSlots as $blockedSlot) {
            if (is_array($blockedSlot)) {
                $blockedBySlot[(int) ($blockedSlot['source_slot_id'] ?? 0)] = $blockedSlot;
            }
        }

        $conflictRows = $this->conflictRows($conflicts, $slots);
        $conflictSlotIds = [];
        foreach ($conflictRows as $conflictRow) {
            $conflictSlotIds[] = (int) $conflictRow['source_slot_id'];
            $conflictSlotIds[] = (int) $conflictRow['conflicting_source_slot_id'];
        }
        $conflictSlotIds = array_values(array_unique(array_filter($conflictSlotIds)));

        $rows = [];
        $importRowsBySlot = $this->importRowsBySlot($slotIds);

        foreach ($slotIds as $slotId) {
            $slot = $slots->get($slotId);
            $importRow = $importRowsBySlot[$slotId] ?? null;
            $slotErrorRows = $this->errorRowsForSlot($errorRows, $slotId);
            $codes = $this->codesForSlot($slotErrorRows);
            $actions = $this->actionsForSlot($slotErrorRows);
            $blockedSlot = $blockedBySlot[$slotId] ?? [];
            $conflictDates = $this->conflictDateCountForSlot($conflicts, $slotId);
            $firstErrorRow = $slotErrorRows[0] ?? [];
            $excelRow = $importRow instanceof ScheduleImportRow ? $importRow->source_row_number : '';
            $importRowId = $importRow instanceof ScheduleImportRow ? $importRow->id : null;
            $importRowTermId = $importRow instanceof ScheduleImportRow ? $importRow->academic_term_id : null;
            $importRowBatchId = $importRow instanceof ScheduleImportRow ? $importRow->import_batch_id : null;
            $resolvedLecturer = $importRow instanceof ScheduleImportRow && $importRow->resolvedLecturer
                ? $importRow->resolvedLecturer->name
                : $this->relatedAttribute($slot, 'lecturer', 'name');
            $resolvedHall = $importRow instanceof ScheduleImportRow && $importRow->resolvedHall
                ? $importRow->resolvedHall->name
                : $this->relatedAttribute($slot, 'hall', 'name');

            $rows[] = [
                'رقم الموعد الأسبوعي' => $slotId,
                'رقم صف Excel' => $excelRow,
                'معرف صف الاستيراد' => $importRowId,
                'معرف الفصل الدراسي' => $slot instanceof SubjectSectionScheduleSlot ? $slot->academic_term_id : $importRowTermId,
                'معرف دفعة الاستيراد' => $slot instanceof SubjectSectionScheduleSlot ? $slot->import_batch_id : $importRowBatchId,
                'المادة' => $this->relatedAttribute($slot, 'subject', 'name') ?: (string) ($firstErrorRow['المادة'] ?? ''),
                'الشعبة' => $this->relatedAttribute($slot, 'subjectSection', 'code') ?: (string) ($firstErrorRow['الشعبة'] ?? ''),
                'المدرس' => $this->relatedAttribute($slot, 'lecturer', 'name') ?: 'غير محدد',
                'القيمة الأصلية من الملف - المدرس' => $this->sourceValue($importRow, ['lecturer', 'lecturer_name', 'teacher_name', 'teacher_name_source', 'اسم المدرس']),
                'قيمة المدرس الأصلية من الملف' => $this->sourceValue($importRow, ['lecturer', 'lecturer_name', 'teacher_name', 'teacher_name_source', 'اسم المدرس']),
                'القيمة المعتمدة بعد المعالجة - المدرس' => $resolvedLecturer,
                'القاعة' => $this->relatedAttribute($slot, 'hall', 'name') ?: 'غير محددة',
                'القيمة الأصلية من الملف - القاعة' => $this->sourceValue($importRow, ['hall', 'hall_name', 'اسم القاعة']),
                'قيمة القاعة الأصلية من الملف' => $this->sourceValue($importRow, ['hall', 'hall_name', 'اسم القاعة']),
                'القيمة المعتمدة بعد المعالجة - القاعة' => $resolvedHall,
                'اليوم' => $this->weekdayLabel($slot instanceof SubjectSectionScheduleSlot ? (int) $slot->weekday : 0),
                'وقت البداية' => $this->time($slot instanceof SubjectSectionScheduleSlot ? $slot->start_time : null),
                'وقت النهاية' => $this->time($slot instanceof SubjectSectionScheduleSlot ? $slot->end_time : null),
                'المشكلات' => implode('، ', array_map(fn (string $code): string => $this->problemLabel($code), $codes)),
                'رموز المشكلات' => $codes,
                'عدد الجلسات المتأثرة' => max(
                    (int) ($blockedSlot['occurrence_count'] ?? 0),
                    $conflictDates,
                    $this->occurrenceCount($slot, $run),
                ),
                'الإجراء المقترح' => $actions !== []
                    ? implode(' / ', $actions)
                    : 'راجع بيانات الموعد الأسبوعي قبل إعادة التوليد.',
            ];
        }

        usort($rows, fn (array $first, array $second): int => (int) $first['رقم الموعد الأسبوعي'] <=> (int) $second['رقم الموعد الأسبوعي']);

        $withoutLecturer = 0;
        $withoutHall = 0;
        $multiIssue = 0;

        foreach ($rows as $row) {
            $codes = $row['رموز المشكلات'];
            $withoutLecturer += in_array('missing_lecturer_identity', $codes, true) ? 1 : 0;
            $withoutHall += in_array('missing_hall', $codes, true) ? 1 : 0;
            $multiIssue += count($codes) > 1 ? 1 : 0;
        }

        return [
            'run' => $run,
            'rows' => $rows,
            'conflicts' => $conflictRows,
            'summary' => [
                'without_lecturer' => $withoutLecturer,
                'without_hall' => $withoutHall,
                'multi_issue' => $multiIssue,
                'conflict_participants' => count($conflictSlotIds),
                'unique_affected_slots' => count($rows),
                'error_report_rows' => count($errorRows),
                'generation_blocked_slots' => (int) $run->blocked_slot_count,
            ],
        ];
    }

    /**
     * @param  array<int, int>  $slotIds
     * @return array<int, ScheduleImportRow>
     */
    private function importRowsBySlot(array $slotIds): array
    {
        if ($slotIds === []) {
            return [];
        }

        $rowsBySlot = [];
        ScheduleImportRow::query()
            ->with(['resolvedLecturer', 'resolvedHall'])
            ->whereNotNull('import_result')
            ->get()
            ->each(function (ScheduleImportRow $row) use (&$rowsBySlot, $slotIds): void {
                foreach ($row->relatedScheduleSlotIds() as $slotId) {
                    if (in_array($slotId, $slotIds, true)) {
                        $rowsBySlot[$slotId] = $row;
                    }
                }
            });

        return $rowsBySlot;
    }

    /**
     * @param  array<int, mixed>  $conflicts
     * @param  Collection<int, SubjectSectionScheduleSlot>  $knownSlots
     * @return array<int, array<string, mixed>>
     */
    private function conflictRows(array $conflicts, Collection $knownSlots): array
    {
        $ids = [];

        foreach ($conflicts as $conflict) {
            if (! is_array($conflict)) {
                continue;
            }

            $ids[] = (int) ($conflict['source_slot_id'] ?? 0);
            $ids[] = (int) ($conflict['conflicting_source_slot_id'] ?? 0);
        }

        $ids = array_values(array_unique(array_filter($ids)));

        $slots = SubjectSectionScheduleSlot::query()
            ->with(['subject', 'subjectSection', 'lecturer', 'hall'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id')
            ->union($knownSlots);

        $rows = [];

        foreach ($conflicts as $conflict) {
            if (! is_array($conflict)) {
                continue;
            }

            $source = $slots->get((int) ($conflict['source_slot_id'] ?? 0));
            $other = $slots->get((int) ($conflict['conflicting_source_slot_id'] ?? 0));
            $dimensions = $this->conflictDimensions($source, $other);
            $sourceStart = $this->time($source instanceof SubjectSectionScheduleSlot ? $source->start_time : null);
            $sourceEnd = $this->time($source instanceof SubjectSectionScheduleSlot ? $source->end_time : null);
            $otherStart = $this->time($other instanceof SubjectSectionScheduleSlot ? $other->start_time : null);
            $otherEnd = $this->time($other instanceof SubjectSectionScheduleSlot ? $other->end_time : null);

            $rows[] = [
                'source_slot_id' => (int) ($conflict['source_slot_id'] ?? 0),
                'conflicting_source_slot_id' => (int) ($conflict['conflicting_source_slot_id'] ?? 0),
                'source_subject_section' => $this->subjectSectionLabel($source),
                'conflicting_subject_section' => $this->subjectSectionLabel($other),
                'source_lecturer' => $this->relatedAttribute($source, 'lecturer', 'name') ?: 'غير محدد',
                'conflicting_lecturer' => $this->relatedAttribute($other, 'lecturer', 'name') ?: 'غير محدد',
                'source_hall' => $this->relatedAttribute($source, 'hall', 'name') ?: 'غير محددة',
                'conflicting_hall' => $this->relatedAttribute($other, 'hall', 'name') ?: 'غير محددة',
                'weekday' => $this->weekdayLabel($source instanceof SubjectSectionScheduleSlot ? (int) $source->weekday : (int) ($conflict['weekday'] ?? 0)),
                'source_start_time' => $sourceStart,
                'source_end_time' => $sourceEnd,
                'conflicting_start_time' => $otherStart,
                'conflicting_end_time' => $otherEnd,
                'actual_overlap_interval' => max($sourceStart, $otherStart).' - '.min($sourceEnd, $otherEnd),
                'session_date' => $conflict['session_date'] ?? '',
                'conflict_dimension' => $dimensions,
            ];
        }

        return $rows;
    }

    private function conflictDimensions(?SubjectSectionScheduleSlot $source, ?SubjectSectionScheduleSlot $other): string
    {
        $dimensions = [];

        if ($source instanceof SubjectSectionScheduleSlot && $other instanceof SubjectSectionScheduleSlot) {
            if ((int) $source->lecturer_id === (int) $other->lecturer_id) {
                $dimensions[] = 'same lecturer';
            }

            if ((int) $source->hall_id === (int) $other->hall_id) {
                $dimensions[] = 'same hall';
            }

            if ((int) $source->subject_section_id === (int) $other->subject_section_id) {
                $dimensions[] = 'same section';
            }
        }

        return count($dimensions) > 1 ? 'multiple dimensions: '.implode(', ', $dimensions) : ($dimensions[0] ?? 'another generated candidate');
    }

    private function occurrenceCount(?SubjectSectionScheduleSlot $slot, LectureSessionGenerationRun $run): int
    {
        if (! $slot instanceof SubjectSectionScheduleSlot) {
            return 0;
        }

        if ($slot->weekday < 1 || $slot->weekday > 7) {
            return 0;
        }

        $start = CarbonImmutable::parse($run->teaching_start_date);
        $end = CarbonImmutable::parse($run->teaching_end_date);
        $offset = ((int) $slot->weekday - $start->isoWeekday() + 7) % 7;
        $count = 0;

        for ($date = $start->addDays($offset); $date->lte($end); $date = $date->addWeek()) {
            $count++;
        }

        return $count;
    }

    private function problemLabel(string $code): string
    {
        return match ($code) {
            'missing_lecturer_identity', 'missing_active_lecturer_login', 'missing_course_lecturer_role' => 'لم يتم تحديد المدرس',
            'missing_hall' => 'لم يتم تحديد القاعة',
            'weekly_schedule_overlap' => 'تعارض في البرنامج الأسبوعي',
            'scheduling_conflict' => 'تعارض أثناء توليد الجلسات',
            default => $code,
        };
    }

    private function problemSort(string $code): int
    {
        return match ($code) {
            'missing_lecturer_identity', 'missing_active_lecturer_login', 'missing_course_lecturer_role' => 10,
            'missing_hall' => 20,
            'weekly_schedule_overlap' => 30,
            'scheduling_conflict' => 40,
            default => 99,
        };
    }

    private function subjectSectionLabel(?SubjectSectionScheduleSlot $slot): string
    {
        if (! $slot instanceof SubjectSectionScheduleSlot) {
            return '';
        }

        return trim($this->relatedAttribute($slot, 'subject', 'name').' / '.$this->relatedAttribute($slot, 'subjectSection', 'code'));
    }

    private function relatedAttribute(?SubjectSectionScheduleSlot $slot, string $relation, string $attribute): string
    {
        if (! $slot instanceof SubjectSectionScheduleSlot) {
            return '';
        }

        $related = $slot->getRelationValue($relation);

        return $related instanceof Model ? (string) ($related->getAttribute($attribute) ?? '') : '';
    }

    /** @param  array<int, string>  $keys */
    private function sourceValue(?ScheduleImportRow $row, array $keys): string
    {
        if (! $row instanceof ScheduleImportRow) {
            return '';
        }

        foreach ($keys as $key) {
            $value = $row->source_payload[$key] ?? $row->normalized_payload[$key] ?? null;

            if (filled($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }

    /**
     * @param  array<int, mixed>  $errorRows
     * @return array<int, array<string, mixed>>
     */
    private function errorRowsForSlot(array $errorRows, int $slotId): array
    {
        $rows = [];

        foreach ($errorRows as $errorRow) {
            if (is_array($errorRow) && (int) ($errorRow['الموعد الأسبوعي المصدر'] ?? 0) === $slotId) {
                $rows[] = $errorRow;
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $errorRows
     * @return array<int, string>
     */
    private function codesForSlot(array $errorRows): array
    {
        $codes = [];

        foreach ($errorRows as $errorRow) {
            if (filled($errorRow['رمز الخطأ'] ?? null)) {
                $codes[] = (string) $errorRow['رمز الخطأ'];
            }
        }

        $codes = array_values(array_unique($codes));
        usort($codes, fn (string $first, string $second): int => $this->problemSort($first) <=> $this->problemSort($second));

        return $codes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $errorRows
     * @return array<int, string>
     */
    private function actionsForSlot(array $errorRows): array
    {
        $actions = [];

        foreach ($errorRows as $errorRow) {
            if (filled($errorRow['الإجراء المقترح'] ?? null)) {
                $actions[] = (string) $errorRow['الإجراء المقترح'];
            }
        }

        return array_values(array_unique($actions));
    }

    /** @param  array<int, mixed>  $conflicts */
    private function conflictDateCountForSlot(array $conflicts, int $slotId): int
    {
        $dates = [];

        foreach ($conflicts as $conflict) {
            if (! is_array($conflict)) {
                continue;
            }

            if ((int) ($conflict['source_slot_id'] ?? 0) === $slotId || (int) ($conflict['conflicting_source_slot_id'] ?? 0) === $slotId) {
                $dates[] = (string) ($conflict['session_date'] ?? '');
            }
        }

        return count(array_unique(array_filter($dates)));
    }

    private function weekdayLabel(int $weekday): string
    {
        return __('weekly-schedule.weekdays')[$weekday] ?? (string) $weekday;
    }

    private function time(mixed $time): string
    {
        return substr((string) $time, 0, 5);
    }

    /** @return array<string, int> */
    private function emptySummary(): array
    {
        return [
            'without_lecturer' => 0,
            'without_hall' => 0,
            'multi_issue' => 0,
            'conflict_participants' => 0,
            'unique_affected_slots' => 0,
            'error_report_rows' => 0,
            'generation_blocked_slots' => 0,
        ];
    }
}
