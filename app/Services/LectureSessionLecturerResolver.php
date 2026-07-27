<?php

namespace App\Services;

use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use Illuminate\Support\Collection;

/** Resolves manual-session lecturers from the selected term and section only. */
class LectureSessionLecturerResolver
{
    /**
     * @return array{
     *     status: 'single'|'multiple'|'none'|'account_problem',
     *     users: Collection<int, User>,
     *     source_slot_ids: array<int, int>,
     *     problems: array<int, array{code: string, source_slot_ids: array<int, int>}>
     * }
     */
    public function resolve(
        int|string|null $academicTermId,
        int|string|null $subjectId,
        int|string|null $subjectSectionId,
    ): array {
        if (blank($academicTermId) || blank($subjectId) || blank($subjectSectionId)) {
            return $this->result('none');
        }

        $section = SubjectSection::query()
            ->whereKey($subjectSectionId)
            ->where('subject_id', $subjectId)
            ->where('academic_term_id', $academicTermId)
            ->with('lecturer.roles')
            ->first();

        if (! $section instanceof SubjectSection) {
            return $this->result('none');
        }

        $slots = SubjectSectionScheduleSlot::query()
            ->where('academic_term_id', $academicTermId)
            ->where('subject_id', $subjectId)
            ->where('subject_section_id', $subjectSectionId)
            ->with('lecturer.user.roles')
            ->orderBy('id')
            ->get();
        $sourceSlotIds = $slots->pluck('id')->map(fn (int $id): int => $id)->all();

        if ($slots->whereNotNull('lecturer_id')->isEmpty()) {
            /** @var User|null $sectionLecturer */
            $sectionLecturer = $section->lecturer;

            if ($sectionLecturer instanceof User) {
                return $this->isActiveCourseLecturer($sectionLecturer)
                    ? $this->result('single', collect([$sectionLecturer]), $sourceSlotIds)
                    : $this->result('account_problem', collect(), $sourceSlotIds, [[
                        'code' => 'inactive_account',
                        'source_slot_ids' => [],
                    ]]);
            }

            return $this->result('none', sourceSlotIds: $sourceSlotIds);
        }

        /** @var Collection<int, User> $resolvedUsers */
        $resolvedUsers = $slots->map(function (SubjectSectionScheduleSlot $slot): ?User {
            /** @var User|null $user */
            $user = $slot->lecturer?->user;

            return $user;
        })
            ->filter(fn (?User $user): bool => $user instanceof User)
            ->unique('id')
            ->values();
        $problems = $this->accountProblems($slots);
        $readyUsers = $resolvedUsers
            ->filter(fn (User $user): bool => $this->isActiveCourseLecturer($user))
            ->values();

        if ($resolvedUsers->isEmpty()) {
            return $this->result('account_problem', collect(), $sourceSlotIds, $problems);
        }

        if ($resolvedUsers->count() === 1) {
            return $readyUsers->isNotEmpty()
                ? $this->result('single', $readyUsers, $sourceSlotIds, $problems)
                : $this->result('account_problem', collect(), $sourceSlotIds, $problems);
        }

        return $readyUsers->isNotEmpty()
            ? $this->result('multiple', $readyUsers, $sourceSlotIds, $problems)
            : $this->result('account_problem', collect(), $sourceSlotIds, $problems);
    }

    /** @return array<int, string> */
    public function options(int|string|null $academicTermId, int|string|null $subjectId, int|string|null $subjectSectionId): array
    {
        return $this->resolve($academicTermId, $subjectId, $subjectSectionId)['users']
            ->sortBy('name')
            ->mapWithKeys(fn (User $user): array => [(int) $user->id => (string) $user->name])
            ->all();
    }

    public function defaultUserId(int|string|null $academicTermId, int|string|null $subjectId, int|string|null $subjectSectionId): ?int
    {
        $resolution = $this->resolve($academicTermId, $subjectId, $subjectSectionId);

        return $resolution['status'] === 'single' ? $resolution['users']->first()?->id : null;
    }

    public function userCanUseSubject(int $userId, int|string|null $subjectId, int|string|null $academicTermId = null, int|string|null $subjectSectionId = null): bool
    {
        return $this->resolve($academicTermId, $subjectId, $subjectSectionId)['users']
            ->contains(fn (User $user): bool => (int) $user->id === $userId);
    }

    public function assignmentStatus(int|string|null $academicTermId, int|string|null $subjectId, int|string|null $subjectSectionId): string
    {
        return $this->resolve($academicTermId, $subjectId, $subjectSectionId)['status'];
    }

    /** @return array<int, array{code: string, source_slot_ids: array<int, int>}> */
    private function accountProblems(Collection $slots): array
    {
        $problems = [];

        foreach ($slots->whereNotNull('lecturer_id')->groupBy('lecturer_id') as $lecturerSlots) {
            /** @var SubjectSectionScheduleSlot $slot */
            $slot = $lecturerSlots->first();
            $user = $slot->lecturer?->user;
            $code = ! $user instanceof User
                ? 'missing_linked_user'
                : (! $user->is_active || $user->status !== 'active' || $user->trashed()
                    ? 'inactive_account'
                    : (! $user->hasRole('course_lecturer') ? 'missing_course_lecturer_role' : null));

            if ($code !== null) {
                $problems[] = [
                    'code' => $code,
                    'source_slot_ids' => $lecturerSlots->pluck('id')->map(fn (int $id): int => $id)->all(),
                ];
            }
        }

        return $problems;
    }

    /** @param Collection<int, User> $users
     * @param  array<int, int>  $sourceSlotIds
     * @param  array<int, array{code: string, source_slot_ids: array<int, int>}>  $problems
     * @return array{status: 'single'|'multiple'|'none'|'account_problem', users: Collection<int, User>, source_slot_ids: array<int, int>, problems: array<int, array{code: string, source_slot_ids: array<int, int>}>}
     */
    private function result(string $status, ?Collection $users = null, array $sourceSlotIds = [], array $problems = []): array
    {
        return [
            'status' => $status,
            'users' => $users ?? collect(),
            'source_slot_ids' => $sourceSlotIds,
            'problems' => $problems,
        ];
    }

    private function isActiveCourseLecturer(User $user): bool
    {
        return ! $user->trashed()
            && $user->status === 'active'
            && $user->is_active
            && $user->hasRole('course_lecturer');
    }
}
