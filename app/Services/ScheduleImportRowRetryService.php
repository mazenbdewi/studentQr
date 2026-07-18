<?php

namespace App\Services;

use App\Models\Hall;
use App\Models\Lecturer;
use App\Models\ScheduleImportIssue;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Support\WeeklyScheduleRowNormalizer;
use RuntimeException;

class ScheduleImportRowRetryService
{
    public function __construct(private readonly WeeklyScheduleRowNormalizer $normalizer) {}

    public function retry(ScheduleImportIssue $issue): array
    {
        $issue->loadMissing(['importRow.importBatch', 'resolvedSubject', 'resolvedSubjectSection']);
        $row = $issue->importRow;
        $subject = $issue->resolvedSubject;
        $section = $issue->resolvedSubjectSection;

        if (! $subject || ! $section) {
            throw new RuntimeException('يجب اختيار مقرر وشعبة صالحين قبل إعادة المعالجة.');
        }

        if ($section->subject_id !== $subject->id || $section->academic_term_id !== $row->academic_term_id) {
            throw new RuntimeException('الشعبة المختارة لا تنتمي للمقرر والفصل الدراسي المرتبطين.');
        }

        $payload = $row->normalized_payload;
        $candidates = [];

        foreach (($payload['weekday_values'] ?? []) as $weekday => $sourceTime) {
            if ($this->normalizer->isMissingValue($sourceTime)) {
                continue;
            }

            try {
                $time = $this->normalizer->parseTimeRange($sourceTime);
            } catch (\InvalidArgumentException $exception) {
                throw new RuntimeException($exception->getMessage(), previous: $exception);
            }

            if ($time) {
                $candidates[] = ['weekday' => (int) $weekday, ...$time];
            }
        }

        if ($candidates === []) {
            throw new RuntimeException('لا يحتوي السطر على موعد أسبوعي صالح لإعادة المعالجة.');
        }

        $missingCandidates = [];
        $alreadyExists = [];
        $conflicts = [];

        foreach ($candidates as $candidate) {
            $slot = SubjectSectionScheduleSlot::query()->with(['lecturer', 'hall'])->where([
                'academic_term_id' => $row->academic_term_id,
                'subject_section_id' => $section->id,
                'weekday' => $candidate['weekday'],
                'start_time' => $candidate['start_time'],
                'end_time' => $candidate['end_time'],
            ])->first();

            if (! $slot) {
                $missingCandidates[] = $candidate;

                continue;
            }

            if ($this->slotConflicts($slot, $payload)) {
                $conflicts[] = ['slot_id' => $slot->id, ...$candidate];
            } else {
                $alreadyExists[] = $slot->id;
            }
        }

        $created = [];

        if ($missingCandidates !== []) {
            $lecturer = $this->resolveLecturer($payload);
            $hall = $this->resolveHall($payload);

            foreach ($missingCandidates as $candidate) {
                $slot = SubjectSectionScheduleSlot::query()->create([
                    'import_batch_id' => $row->import_batch_id,
                    'academic_term_id' => $row->academic_term_id,
                    'subject_id' => $subject->id,
                    'subject_section_id' => $section->id,
                    'lecturer_id' => $lecturer?->id,
                    'hall_id' => $hall?->id,
                    'weekday' => $candidate['weekday'],
                    'start_time' => $candidate['start_time'],
                    'end_time' => $candidate['end_time'],
                    'section_capacity' => $payload['section_capacity'] ?? null,
                    'expected_student_count' => $payload['expected_student_count'] ?? null,
                    'raw_teacher_name' => $payload['teacher_name_source'] ?? null,
                    'raw_hall_name' => $payload['hall_name_source'] ?? null,
                ]);
                $created[] = $slot->id;
            }
        }

        return [
            'created_slot_ids' => $created,
            'already_existing_slot_ids' => $alreadyExists,
            'conflicts' => $conflicts,
            'status' => $conflicts === [] ? 'completed' : 'completed_with_conflicts',
        ];
    }

    private function slotConflicts(SubjectSectionScheduleSlot $slot, array $payload): bool
    {
        $teacherKey = $this->normalizer->normalizeKey($payload['teacher_name'] ?? null);
        $existingTeacherKey = $this->normalizer->normalizeKey($slot->lecturer?->canonical_name ?: $slot->lecturer?->name);
        $hallKey = $this->normalizer->normalizeKey($payload['hall_name'] ?? null);
        $existingHallKeys = array_filter([
            $this->normalizer->normalizeKey($slot->hall?->name),
            $this->normalizer->normalizeKey($slot->hall?->code),
        ]);

        return ($teacherKey !== '' && $existingTeacherKey !== '' && $teacherKey !== $existingTeacherKey)
            || ($hallKey !== '' && $existingHallKeys !== [] && ! in_array($hallKey, $existingHallKeys, true))
            || (($payload['section_capacity'] ?? null) !== null && $slot->section_capacity !== null && (int) $payload['section_capacity'] !== $slot->section_capacity)
            || (($payload['expected_student_count'] ?? null) !== null && $slot->expected_student_count !== null && (int) $payload['expected_student_count'] !== $slot->expected_student_count);
    }

    private function resolveLecturer(array $payload): ?Lecturer
    {
        $name = $payload['teacher_name'] ?? null;
        $key = $this->normalizer->normalizeKey($name);

        if ($key === '') {
            return null;
        }

        $matches = Lecturer::query()->get()->filter(fn (Lecturer $lecturer): bool => $this->normalizer->normalizeKey($lecturer->canonical_name ?: $lecturer->name) === $key)->values();

        if ($matches->count() > 1) {
            throw new RuntimeException('اسم المدرس يطابق أكثر من هوية؛ لم يتم الاختيار تلقائياً.');
        }

        if ($matches->count() === 1) {
            return $matches->sole();
        }

        $users = User::query()->where(function ($query): void {
            $query->where('role', 'course_lecturer')
                ->orWhereHas('roles', fn ($roles) => $roles->where('name', 'course_lecturer'));
        })->get()->filter(fn (User $user): bool => $this->normalizer->normalizeKey($user->name) === $key)->values();

        if ($users->count() > 1) {
            throw new RuntimeException('اسم المدرس يطابق أكثر من حساب مدرس؛ لم يتم الاختيار تلقائياً.');
        }

        return Lecturer::query()->create([
            'user_id' => $users->first()?->id,
            'name' => $name,
            'canonical_name' => $key,
            'lecturer_id' => null,
            'email' => null,
            'is_active' => true,
        ]);
    }

    private function resolveHall(array $payload): ?Hall
    {
        $name = $payload['hall_name'] ?? null;
        $key = $this->normalizer->normalizeKey($name);

        if ($key === '') {
            return null;
        }

        $matches = Hall::withTrashed()->get()->filter(fn (Hall $hall): bool => in_array($key, [
            $this->normalizer->normalizeKey($hall->name),
            $this->normalizer->normalizeKey($hall->code),
        ], true))->values();

        if ($matches->count() > 1) {
            throw new RuntimeException('اسم القاعة يطابق أكثر من قاعة؛ لم يتم الاختيار تلقائياً.');
        }

        if ($matches->count() === 1) {
            $hall = $matches->sole();
            $hall->restore();

            return $hall;
        }

        return Hall::query()->create([
            'code' => $name,
            'name' => $name,
            'floor' => 0,
            'is_active' => true,
        ]);
    }
}
