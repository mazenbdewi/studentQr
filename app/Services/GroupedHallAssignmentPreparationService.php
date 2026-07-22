<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Hall;
use App\Models\LectureSession;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Policies\ScheduleImportRowPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class GroupedHallAssignmentPreparationService
{
    public const CLASS_SUITABLE = 'مناسب';

    public const CLASS_WARNING = 'مناسب مع تحذير';

    public const CLASS_INSUFFICIENT_CAPACITY = 'غير كافٍ من حيث السعة';

    public const CLASS_WRONG_TYPE = 'نوع القاعة غير مناسب';

    public const CLASS_INACTIVE = 'القاعة غير فعالة';

    public const CLASS_CONFLICT = 'يوجد تعارض';

    public const ADMIN_ACKNOWLEDGEMENT = 'أؤكد أنني تحققت إداريًا من سعة القاعة ونوعها ومناسبتها للمادة.';

    /** @return array<int, array<string, mixed>> */
    public function groups(): array
    {
        $rows = collect(app(BlockedWeeklySlotReportService::class)->latestReport()['rows'])
            ->filter(fn (array $row): bool => $this->isHallOnlyRow($row));

        return $this->groupsFromSlotIds(
            $rows->pluck('رقم الموعد الأسبوعي')->map(fn (mixed $id): int => (int) $id)->all()
        );
    }

    /**
     * @param  array<int, int>  $slotIds
     * @return array<int, array<string, mixed>>
     */
    public function groupsFromSlotIds(array $slotIds): array
    {
        $slots = $this->slots($slotIds);
        /** @var Collection<int, SubjectSectionScheduleSlot> $slotCollection */
        $slotCollection = collect($slots->all());
        $groups = $slotCollection
            ->groupBy(fn (SubjectSectionScheduleSlot $slot): string => $this->groupKey($slot))
            ->map(function (Collection $groupSlots, string $key): array {
                /** @var SubjectSectionScheduleSlot $first */
                $first = $groupSlots->first();

                return [
                    'key' => $key,
                    'label' => $this->groupLabel($key, $first),
                    'subject' => $this->relatedString($first, 'subject', 'name'),
                    'section' => $this->relatedString($first, 'subjectSection', 'code'),
                    'lecturer' => $this->relatedString($first, 'lecturer', 'name'),
                    'slot_ids' => $groupSlots->pluck('id')->sort()->values()->all(),
                    'slot_count' => $groupSlots->count(),
                    'enrolled_count' => (int) $groupSlots
                        ->map(fn (SubjectSectionScheduleSlot $slot): int => (int) ($slot->expected_student_count ?? $slot->section_capacity ?? 0))
                        ->max(),
                    'required_hall_type' => $this->requiresWorkshop($first) ? Hall::TYPE_WORKSHOP : Hall::TYPE_LECTURE_HALL,
                    'required_hall_type_label' => $this->requiresWorkshop($first) ? 'ورشة أو بديل معتمد إدارياً' : 'قاعة نظرية أو بديل مناسب',
                    'days_times' => $groupSlots
                        ->sortBy(fn (SubjectSectionScheduleSlot $slot): string => sprintf('%d-%s', $slot->weekday, $slot->start_time))
                        ->map(fn (SubjectSectionScheduleSlot $slot): string => $this->weekdayLabel($slot).' '.substr((string) $slot->start_time, 0, 5).'–'.substr((string) $slot->end_time, 0, 5))
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy('label')
            ->values()
            ->all();

        return $groups;
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(string $groupKey, int $hallId, ?User $actor = null, bool $acknowledged = false, ?string $adminNote = null): array
    {
        if ($actor) {
            Gate::forUser($actor)->authorize(ScheduleImportRowPolicy::PREVIEW_GROUPED_HALL_ASSIGNMENT);
        }

        $group = collect($this->groups())->firstWhere('key', $groupKey);
        $slotIds = $group ? $group['slot_ids'] : [];
        $slots = $this->slots($slotIds);
        $hallQuery = Hall::query()->withoutTrashed();
        if ($this->facultySupported()) {
            $hallQuery->with('faculty');
        }
        $hall = $hallQuery->find($hallId);
        $hallCapacity = $hall instanceof Hall ? $this->hallAttribute($hall, 'capacity') : null;
        $hallType = $hall instanceof Hall ? $this->hallAttribute($hall, 'hall_type') : null;
        $buildingName = $hall instanceof Hall ? $this->hallAttribute($hall, 'building_name') : null;
        $facultyId = $hall instanceof Hall ? $this->hallAttribute($hall, 'faculty_id') : null;
        $notes = $hall instanceof Hall ? $this->hallAttribute($hall, 'notes') : null;

        $warnings = [];
        $blockingErrors = [];
        $slotRows = [];
        $expectedSessions = $this->expectedSessionCount($slots);
        $internalConflicts = $hall instanceof Hall ? $this->internalSelectedConflicts($slots, $hall) : [];
        $weeklyConflicts = $hall instanceof Hall ? $this->weeklyConflicts($slots, $hall) : [];
        $datedConflicts = $hall instanceof Hall ? $this->datedSessionConflicts($slots, $hall) : [];

        if (! $group) {
            $blockingErrors[] = 'المجموعة المحددة غير موجودة ضمن الخانات المحجوبة الحالية.';
        }

        if (! $hall instanceof Hall) {
            $blockingErrors[] = 'القاعة المقترحة غير موجودة.';
        }

        if ($hall instanceof Hall && ! (bool) $hall->is_active) {
            $blockingErrors[] = 'القاعة المقترحة غير فعالة.';
        }

        if ($hall instanceof Hall && blank($hallCapacity)) {
            $warnings[] = 'سعة القاعة غير مدخلة؛ يلزم تأكيد إداري قبل الحفظ.';
        }

        if ($hall instanceof Hall && blank($hallType)) {
            $warnings[] = 'نوع القاعة غير مدخل؛ يلزم تأكيد إداري قبل الحفظ.';
        }

        if ($hall instanceof Hall && blank($buildingName)) {
            $warnings[] = 'المبنى غير مدخل في بيانات القاعة.';
        }

        if ($hall instanceof Hall && $this->facultySupported() && blank($facultyId)) {
            $warnings[] = 'الكلية غير مدخلة في بيانات القاعة.';
        }

        $maxEnrolled = 0;
        foreach ($slots as $slot) {
            /** @var SubjectSectionScheduleSlot $slot */
            $enrolled = (int) ($slot->expected_student_count ?? $slot->section_capacity ?? 0);
            $maxEnrolled = max($maxEnrolled, $enrolled);
            $remainingSeats = $hall instanceof Hall && $hallCapacity !== null ? ((int) $hallCapacity - $enrolled) : null;
            $slotWarnings = [];
            $slotErrors = [];

            if ($hall instanceof Hall && $hallCapacity !== null && $remainingSeats < 0) {
                $slotErrors[] = 'سعة القاعة أقل من عدد الطلاب المتوقع.';
            }

            if ($hall instanceof Hall && $this->requiresWorkshop($slot) && $hallType !== null && ! in_array($hallType, Hall::workshopCompatibleTypes(), true)) {
                $slotErrors[] = 'هذه المادة تتطلب ورشة أو بديلاً معتمداً إدارياً.';
            }

            if ($hall instanceof Hall && $this->requiresWorkshop($slot) && $hallType === null) {
                $slotWarnings[] = 'نوع القاعة غير معروف لمادة تتطلب ورشة.';
            }

            $slotRows[] = [
                'slot_id' => $slot->id,
                'subject' => $this->relatedString($slot, 'subject', 'name'),
                'section' => $this->relatedString($slot, 'subjectSection', 'code'),
                'lecturer' => $this->relatedString($slot, 'lecturer', 'name'),
                'weekday' => $this->weekdayLabel($slot),
                'start_time' => substr((string) $slot->start_time, 0, 5),
                'end_time' => substr((string) $slot->end_time, 0, 5),
                'enrolled_count' => $enrolled,
                'proposed_hall' => $hall instanceof Hall ? $hall->name : null,
                'proposed_hall_code' => $hall instanceof Hall ? $hall->code : null,
                'hall_capacity' => $hallCapacity,
                'hall_type' => $hallType ? (Hall::hallTypeOptions()[$hallType] ?? $hallType) : null,
                'building_name' => $buildingName,
                'faculty' => $this->facultySupported() && $hall instanceof Hall ? $this->relatedString($hall, 'faculty', 'name') : null,
                'remaining_seats' => $remainingSeats,
                'expected_additional_sessions' => $expectedSessions[$slot->id] ?? 0,
                'warnings' => $slotWarnings,
                'blocking_errors' => $slotErrors,
                'classification' => $this->classification($slotErrors, $slotWarnings, $hall),
            ];
        }

        if ($hall instanceof Hall && $hallCapacity !== null && $hallCapacity < $maxEnrolled) {
            $blockingErrors[] = 'سعة القاعة غير كافية لكل الخانات المختارة.';
        }

        /** @var Collection<int, SubjectSectionScheduleSlot> $slotCollection */
        $slotCollection = collect($slots->all());
        if ($hall instanceof Hall && $slotCollection->contains(fn (SubjectSectionScheduleSlot $slot): bool => $this->requiresWorkshop($slot))
            && $hallType !== null
            && ! in_array($hallType, Hall::workshopCompatibleTypes(), true)) {
            $blockingErrors[] = 'نوع القاعة غير مناسب لمجموعة تدريب الورشة.';
        }

        if ($internalConflicts !== [] || $weeklyConflicts !== [] || $datedConflicts !== []) {
            $blockingErrors[] = 'يوجد تعارض زمني مع القاعة المقترحة.';
        }

        $acknowledgementRequired = $warnings !== [];
        $hasWarningPermission = ! $actor || $actor->can(ScheduleImportRowPolicy::CONFIRM_GROUPED_HALL_ASSIGNMENT_WITH_WARNING);
        $acknowledgementComplete = ! $acknowledgementRequired
            || ($acknowledged && filled($adminNote) && $hasWarningPermission);

        return [
            'group' => $group,
            'proposed_hall' => $hall instanceof Hall ? [
                'id' => $hall->id,
                'code' => $hall->code,
                'name' => $hall->name,
                'capacity' => $hallCapacity,
                'hall_type' => $hallType,
                'hall_type_label' => $hallType ? (Hall::hallTypeOptions()[$hallType] ?? $hallType) : null,
                'building_name' => $buildingName,
                'faculty' => $this->facultySupported() ? $this->relatedString($hall, 'faculty', 'name') : null,
                'is_active' => (bool) $hall->is_active,
                'notes' => $notes,
            ] : null,
            'rows' => $slotRows,
            'expected_additional_sessions' => array_sum($expectedSessions),
            'weekly_conflicts' => $weeklyConflicts,
            'dated_session_conflicts' => $datedConflicts,
            'selected_slot_conflicts' => $internalConflicts,
            'warnings' => array_values(array_unique($warnings)),
            'blocking_errors' => array_values(array_unique($blockingErrors)),
            'acknowledgement_text' => self::ADMIN_ACKNOWLEDGEMENT,
            'acknowledgement_required' => $acknowledgementRequired,
            'admin_note_required' => $acknowledgementRequired,
            'classification' => $this->overallClassification($blockingErrors, $warnings, $hall),
            'confirm_enabled' => $blockingErrors === [] && $acknowledgementComplete,
            'writes_performed' => false,
            'generation_remains_separate_action' => true,
        ];
    }

    /**
     * @param  array<string, int>  $groupHallIds
     * @return array{conflicts: array<int, array<string, mixed>>, confirm_enabled: bool, writes_performed: bool}
     */
    public function previewPlannedAssignments(array $groupHallIds): array
    {
        $groups = collect($this->groups())->keyBy('key');
        $planned = [];
        $conflicts = [];

        foreach ($groupHallIds as $groupKey => $hallId) {
            $group = $groups->get($groupKey);
            $hall = Hall::query()->withoutTrashed()->find($hallId);

            if (! $group || ! $hall instanceof Hall) {
                continue;
            }

            foreach ($this->slots($group['slot_ids']) as $slot) {
                $planned[] = [
                    'group_key' => $groupKey,
                    'group_label' => $group['label'],
                    'hall_id' => $hall->id,
                    'hall' => $hall->name,
                    'slot' => $slot,
                ];
            }
        }

        foreach ($planned as $firstIndex => $first) {
            foreach (array_slice($planned, $firstIndex + 1) as $second) {
                /** @var SubjectSectionScheduleSlot $firstSlot */
                $firstSlot = $first['slot'];
                /** @var SubjectSectionScheduleSlot $secondSlot */
                $secondSlot = $second['slot'];

                if ($first['group_key'] === $second['group_key']
                    || $first['hall_id'] !== $second['hall_id']
                    || $firstSlot->weekday !== $secondSlot->weekday
                    || (string) $firstSlot->start_time >= (string) $secondSlot->end_time
                    || (string) $firstSlot->end_time <= (string) $secondSlot->start_time) {
                    continue;
                }

                $conflicts[] = [
                    'first_group' => $first['group_label'],
                    'second_group' => $second['group_label'],
                    'first_slot_id' => $firstSlot->id,
                    'second_slot_id' => $secondSlot->id,
                    'hall' => $first['hall'],
                    'weekday' => $this->weekdayLabel($firstSlot),
                    'first_time' => substr((string) $firstSlot->start_time, 0, 5).' - '.substr((string) $firstSlot->end_time, 0, 5),
                    'second_time' => substr((string) $secondSlot->start_time, 0, 5).' - '.substr((string) $secondSlot->end_time, 0, 5),
                    'dimension' => 'planned_same_hall_overlap',
                ];
            }
        }

        return [
            'conflicts' => $conflicts,
            'confirm_enabled' => $conflicts === [],
            'writes_performed' => false,
        ];
    }

    /** @param EloquentCollection<int, SubjectSectionScheduleSlot> $slots */
    private function internalSelectedConflicts(EloquentCollection $slots, Hall $hall): array
    {
        $conflicts = [];

        foreach ($slots as $source) {
            foreach ($slots as $candidate) {
                if ($source->id >= $candidate->id || $source->weekday !== $candidate->weekday) {
                    continue;
                }

                if ((string) $source->start_time < (string) $candidate->end_time && (string) $source->end_time > (string) $candidate->start_time) {
                    $conflicts[] = $this->conflictRow($source, $candidate, $hall, 'conflict_between_selected_slots');
                }
            }
        }

        return $conflicts;
    }

    /** @param EloquentCollection<int, SubjectSectionScheduleSlot> $slots */
    private function weeklyConflicts(EloquentCollection $slots, Hall $hall): array
    {
        $conflicts = [];
        $selectedIds = $slots->pluck('id')->all();

        foreach ($slots as $slot) {
            $candidates = SubjectSectionScheduleSlot::query()
                ->with(['subject', 'subjectSection', 'lecturer', 'hall'])
                ->where('academic_term_id', $slot->academic_term_id)
                ->where('weekday', $slot->weekday)
                ->where('start_time', '<', $slot->end_time)
                ->where('end_time', '>', $slot->start_time)
                ->whereNotIn('id', $selectedIds)
                ->where(function ($query) use ($slot, $hall): void {
                    $query->where('hall_id', $hall->id)
                        ->orWhere('subject_section_id', $slot->subject_section_id);

                    if ($slot->lecturer_id) {
                        $query->orWhere('lecturer_id', $slot->lecturer_id);
                    }
                })
                ->get();

            foreach ($candidates as $candidate) {
                $conflicts[] = $this->conflictRow($slot, $candidate, $hall, 'weekly_schedule_overlap');
            }
        }

        return collect($conflicts)->unique(fn (array $row): string => $row['source_slot_id'].'|'.$row['conflicting_slot_id'].'|'.$row['dimension'])->values()->all();
    }

    /** @param EloquentCollection<int, SubjectSectionScheduleSlot> $slots */
    private function datedSessionConflicts(EloquentCollection $slots, Hall $hall): array
    {
        $conflicts = [];

        foreach ($slots as $slot) {
            foreach ($this->teachingDates($slot) as $date) {
                $sessions = LectureSession::query()
                    ->where('academic_term_id', $slot->academic_term_id)
                    ->whereDate('session_date', $date)
                    ->where('start_time', '<', $slot->end_time)
                    ->where('end_time', '>', $slot->start_time)
                    ->where('hall_id', $hall->id)
                    ->get();

                foreach ($sessions as $session) {
                    $conflicts[] = [
                        'source_slot_id' => $slot->id,
                        'conflicting_session_id' => $session->id,
                        'date' => $date,
                        'weekday' => $this->weekdayLabel($slot),
                        'start_time' => substr((string) $slot->start_time, 0, 5),
                        'end_time' => substr((string) $slot->end_time, 0, 5),
                        'hall' => $hall->name,
                        'dimension' => 'dated_session_hall',
                    ];
                }
            }
        }

        return $conflicts;
    }

    private function conflictRow(SubjectSectionScheduleSlot $source, SubjectSectionScheduleSlot $candidate, Hall $hall, string $dimension): array
    {
        return [
            'source_slot_id' => $source->id,
            'conflicting_slot_id' => $candidate->id,
            'source_subject_section' => trim($this->relatedString($source, 'subject', 'name').' '.$this->relatedString($source, 'subjectSection', 'code')),
            'conflicting_subject_section' => trim($this->relatedString($candidate, 'subject', 'name').' '.$this->relatedString($candidate, 'subjectSection', 'code')),
            'source_lecturer' => $this->relatedString($source, 'lecturer', 'name'),
            'conflicting_lecturer' => $this->relatedString($candidate, 'lecturer', 'name'),
            'hall' => $hall->name,
            'weekday' => $this->weekdayLabel($source),
            'source_time' => substr((string) $source->start_time, 0, 5).' - '.substr((string) $source->end_time, 0, 5),
            'conflicting_time' => substr((string) $candidate->start_time, 0, 5).' - '.substr((string) $candidate->end_time, 0, 5),
            'dimension' => $dimension,
        ];
    }

    /** @param EloquentCollection<int, SubjectSectionScheduleSlot> $slots */
    private function expectedSessionCount(EloquentCollection $slots): array
    {
        $counts = [];

        foreach ($slots as $slot) {
            $counts[$slot->id] = count($this->teachingDates($slot));
        }

        return $counts;
    }

    /** @return array<int, string> */
    private function teachingDates(SubjectSectionScheduleSlot $slot): array
    {
        $term = $slot->academicTerm;

        if (! $term instanceof AcademicTerm || blank($term->teaching_start_date) || blank($term->teaching_end_date)) {
            return [];
        }

        $start = CarbonImmutable::parse($term->teaching_start_date);
        $end = CarbonImmutable::parse($term->teaching_end_date);
        $offset = ($slot->weekday - $start->isoWeekday() + 7) % 7;
        $dates = [];

        for ($date = $start->addDays($offset); $date->lte($end); $date = $date->addWeek()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    /** @param array<int, int> $slotIds */
    private function slots(array $slotIds): EloquentCollection
    {
        /** @var EloquentCollection<int, SubjectSectionScheduleSlot> $slots */
        $slots = SubjectSectionScheduleSlot::query()
            ->with(['academicTerm', 'subject', 'subjectSection', 'lecturer', 'hall'])
            ->whereIn('id', collect($slotIds)->filter()->unique()->values()->all())
            ->orderBy('id')
            ->get();

        return $slots;
    }

    private function isHallOnlyRow(array $row): bool
    {
        $codes = collect($row['رموز المشكلات'] ?? []);

        return $codes->contains('missing_hall')
            && ! $codes->contains('missing_lecturer_identity')
            && $codes->intersect(['weekly_schedule_overlap', 'scheduling_conflict'])->isEmpty();
    }

    private function groupKey(SubjectSectionScheduleSlot $slot): string
    {
        $subject = trim($this->relatedString($slot, 'subject', 'name'));
        $section = trim($this->relatedString($slot, 'subjectSection', 'code'));
        $lecturer = trim($this->relatedString($slot, 'lecturer', 'name'));

        return md5($subject.'|'.$section.'|'.$lecturer);
    }

    private function groupLabel(string $key, SubjectSectionScheduleSlot $slot): string
    {
        $subject = $this->relatedString($slot, 'subject', 'name') ?: 'مجموعة غير معروفة';

        $known = match (true) {
            str_contains($subject, 'تدريب في الورشة') => 'Group A — تدريب في الورشة',
            str_contains($subject, 'حوكمة الشركات') => 'Group B — حوكمة الشركات',
            str_contains($subject, 'فيزياء 2') => 'Group C — فيزياء 2',
            str_contains($subject, 'تشريعات التجارة الإلكترونية') => 'Group D — تشريعات التجارة الإلكترونية',
            str_contains($subject, 'ترويج المبيعات') => 'Group E — ترويج المبيعات',
            default => null,
        };

        return $known ?? 'مجموعة — '.$subject.' — '.($this->relatedString($slot, 'subjectSection', 'code') ?: '#'.$key);
    }

    private function requiresWorkshop(SubjectSectionScheduleSlot $slot): bool
    {
        return str_contains($this->relatedString($slot, 'subject', 'name'), 'تدريب في الورشة');
    }

    private function weekdayLabel(SubjectSectionScheduleSlot $slot): string
    {
        return __('weekly-schedule.weekdays')[$slot->weekday] ?? (string) $slot->weekday;
    }

    private function relatedString(Model $model, string $relation, string $attribute): string
    {
        $related = $model->getRelationValue($relation);

        return $related instanceof Model ? (string) ($related->getAttribute($attribute) ?? '') : '';
    }

    private function classification(array $slotErrors, array $slotWarnings, ?Hall $hall): string
    {
        if ($hall instanceof Hall && ! (bool) $hall->is_active) {
            return self::CLASS_INACTIVE;
        }

        if ($slotErrors !== []) {
            return str_contains(implode(' ', $slotErrors), 'سعة') ? self::CLASS_INSUFFICIENT_CAPACITY : self::CLASS_WRONG_TYPE;
        }

        return $slotWarnings === [] ? self::CLASS_SUITABLE : self::CLASS_WARNING;
    }

    private function overallClassification(array $blockingErrors, array $warnings, ?Hall $hall): string
    {
        $text = implode(' ', $blockingErrors);

        return match (true) {
            $hall instanceof Hall && ! (bool) $hall->is_active => self::CLASS_INACTIVE,
            str_contains($text, 'تعارض') => self::CLASS_CONFLICT,
            str_contains($text, 'سعة') => self::CLASS_INSUFFICIENT_CAPACITY,
            str_contains($text, 'نوع') => self::CLASS_WRONG_TYPE,
            $warnings !== [] => self::CLASS_WARNING,
            default => self::CLASS_SUITABLE,
        };
    }

    private function facultySupported(): bool
    {
        return Schema::hasColumn('halls', 'faculty_id');
    }

    private function hallAttribute(Hall $hall, string $attribute): mixed
    {
        if (! Schema::hasColumn('halls', $attribute)) {
            return null;
        }

        return $hall->getAttributes()[$attribute] ?? null;
    }
}
