<?php

namespace App\Services;

use App\Models\Hall;
use App\Models\Lecturer;
use App\Models\LectureSession;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FutureLectureSessionAssignmentService
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /** @return array<string, mixed> */
    public function preview(SubjectSectionScheduleSlot $slot, ?CarbonInterface $now = null): array
    {
        $slot->loadMissing(['lecturer.user.roles', 'hall']);

        return $this->buildPreview($slot, $this->sessionsForSlot($slot), $now ?? now(config('app.timezone')));
    }

    /** @return array<string, mixed> */
    public function replacePlaceholders(SubjectSectionScheduleSlot $slot, ?int $lecturerId, ?int $hallId, User $actor): array
    {
        return DB::transaction(function () use ($slot, $lecturerId, $hallId, $actor): array {
            $locked = SubjectSectionScheduleSlot::query()
                ->with(['lecturer.user.roles', 'hall'])
                ->lockForUpdate()
                ->findOrFail($slot->id);
            $oldLecturerId = $locked->lecturer_id;
            $oldHallId = $locked->hall_id;
            $changes = [];

            if ($lecturerId !== null && $lecturerId !== (int) $oldLecturerId) {
                $targetLecturer = Lecturer::query()->with('user.roles')->findOrFail($lecturerId);

                if (! $locked->lecturer?->isPlaceholder() || $targetLecturer->isPlaceholder() || ! $targetLecturer->is_active) {
                    throw ValidationException::withMessages(['lecturer_id' => __('weekly-schedule.future_assignment.validation.placeholder_to_real_only')]);
                }

                $changes['lecturer_id'] = $targetLecturer->id;
            }

            if ($hallId !== null && $hallId !== (int) $oldHallId) {
                $targetHall = Hall::query()->withoutTrashed()->findOrFail($hallId);

                if (! $locked->hall?->isPlaceholder() || $targetHall->isPlaceholder() || ! $targetHall->is_active) {
                    throw ValidationException::withMessages(['hall_id' => __('weekly-schedule.future_assignment.validation.placeholder_to_real_only')]);
                }

                $changes['hall_id'] = $targetHall->id;
            }

            if ($changes === []) {
                throw ValidationException::withMessages(['assignments' => __('weekly-schedule.future_assignment.validation.select_replacement')]);
            }

            $weeklyConflicts = $this->weeklySlotConflicts($locked, $changes);
            if ($weeklyConflicts !== []) {
                throw ValidationException::withMessages([
                    'assignments' => __('weekly-schedule.future_assignment.validation.weekly_conflicts', ['count' => count($weeklyConflicts)]),
                ]);
            }

            $locked->update($changes);

            $this->activityLogger->log([
                'category' => 'lecture_sessions',
                'action' => 'replace_schedule_placeholders',
                'model_type' => SubjectSectionScheduleSlot::class,
                'model_id' => $locked->id,
                'description' => 'Replaced weekly schedule placeholder assignments',
                'user_id' => $actor->id,
                'old_values' => ['lecturer_id' => $oldLecturerId, 'hall_id' => $oldHallId],
                'new_values' => ['lecturer_id' => $locked->lecturer_id, 'hall_id' => $locked->hall_id],
                'metadata' => [
                    'academic_term_id' => $locked->academic_term_id,
                    'batch_id' => $locked->import_batch_id,
                ],
            ]);

            return [
                'slot_id' => $locked->id,
                'old_lecturer_id' => $oldLecturerId,
                'new_lecturer_id' => $locked->lecturer_id,
                'old_hall_id' => $oldHallId,
                'new_hall_id' => $locked->hall_id,
            ];
        });
    }

    /** @return array<string, mixed> */
    public function apply(SubjectSectionScheduleSlot $slot, User $actor, ?CarbonInterface $now = null): array
    {
        return DB::transaction(function () use ($slot, $actor, $now): array {
            $lockedSlot = SubjectSectionScheduleSlot::query()
                ->with(['lecturer.user.roles', 'hall'])
                ->lockForUpdate()
                ->findOrFail($slot->id);
            $sessions = $this->sessionsForSlot($lockedSlot, lock: true);
            $preview = $this->buildPreview($lockedSlot, $sessions, $now ?? now(config('app.timezone')), lockConflicts: true);

            if ($preview['conflict_count'] > 0) {
                throw ValidationException::withMessages([
                    'sessions' => __('weekly-schedule.future_assignment.validation.session_conflicts', ['count' => $preview['conflict_count']]),
                ]);
            }

            /** @var Collection<int, array<string, mixed>> $eligible */
            $eligible = collect($preview['eligible_sessions']);
            if ($eligible->isEmpty()) {
                return [...$preview, 'updated_sessions_count' => 0];
            }

            $sessionIds = $eligible->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            $targetLecturer = $lockedSlot->lecturer;
            $targetLecturerUserId = $this->validLecturerUserId($targetLecturer);

            foreach ($sessions->whereIn('id', $sessionIds) as $session) {
                $changes = [];

                if (($eligible->firstWhere('id', $session->id)['update_lecturer'] ?? false) === true) {
                    $changes['lecturer_identity_id'] = $targetLecturer?->id;
                    $changes['lecturer_id'] = $targetLecturerUserId;
                }

                if (($eligible->firstWhere('id', $session->id)['update_hall'] ?? false) === true) {
                    $changes['hall_id'] = $lockedSlot->hall_id;
                }

                $session->update($changes);
            }

            $firstEligible = $eligible->first();
            $metadata = [
                'academic_term_id' => $lockedSlot->academic_term_id,
                'batch_id' => $lockedSlot->import_batch_id,
                'schedule_slot_id' => $lockedSlot->id,
                'old_lecturer_identity_id' => $firstEligible['old_lecturer_identity_id'] ?? null,
                'new_lecturer_identity_id' => $lockedSlot->lecturer_id,
                'old_hall_id' => $firstEligible['old_hall_id'] ?? null,
                'new_hall_id' => $lockedSlot->hall_id,
                'updated_sessions_count' => count($sessionIds),
                'skipped_past_count' => $preview['skipped_past_count'],
                'skipped_completed_count' => $preview['skipped_completed_count'],
                'skipped_started_count' => $preview['skipped_started_count'],
                'skipped_attendance_count' => $preview['skipped_attendance_count'],
                'skipped_activity_count' => $preview['skipped_activity_count'],
                'user_id' => $actor->id,
            ];

            $this->activityLogger->log([
                'category' => 'lecture_sessions',
                'action' => 'apply_schedule_assignment_changes_to_future_sessions',
                'model_type' => SubjectSectionScheduleSlot::class,
                'model_id' => $lockedSlot->id,
                'description' => 'Applied schedule assignment changes to future sessions',
                'user_id' => $actor->id,
                'metadata' => $metadata,
            ]);

            return [...$preview, 'updated_sessions_count' => count($sessionIds)];
        });
    }

    /** @return EloquentCollection<int, LectureSession> */
    private function sessionsForSlot(SubjectSectionScheduleSlot $slot, bool $lock = false): EloquentCollection
    {
        return LectureSession::query()
            ->where('subject_section_schedule_slot_id', $slot->id)
            ->with(['lecturerIdentity', 'hall'])
            ->withCount(['attendances', 'tokens'])
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();
    }

    /** @param EloquentCollection<int, LectureSession> $sessions @return array<string, mixed> */
    private function buildPreview(
        SubjectSectionScheduleSlot $slot,
        EloquentCollection $sessions,
        CarbonInterface $now,
        bool $lockConflicts = false,
    ): array {
        $targetLecturer = $slot->lecturer;
        $targetHall = $slot->hall;
        $targetLecturerIsReal = $targetLecturer instanceof Lecturer && ! $targetLecturer->isPlaceholder();
        $targetHallIsReal = $targetHall instanceof Hall && ! $targetHall->isPlaceholder();
        $eligible = collect();
        $counts = [
            'skipped_past_count' => 0,
            'skipped_completed_count' => 0,
            'skipped_started_count' => 0,
            'skipped_attendance_count' => 0,
            'skipped_activity_count' => 0,
            'skipped_no_change_count' => 0,
        ];

        foreach ($sessions as $session) {
            $updateLecturer = $targetLecturerIsReal && $session->usesPlaceholderLecturer();
            $updateHall = $targetHallIsReal && $session->usesPlaceholderHall();

            if (! $updateLecturer && ! $updateHall) {
                $counts['skipped_no_change_count']++;

                continue;
            }

            if ((int) $session->attendances_count > 0) {
                $counts['skipped_attendance_count']++;

                continue;
            }

            if ((int) $session->tokens_count > 0) {
                $counts['skipped_activity_count']++;

                continue;
            }

            if (in_array($session->status, ['completed', 'cancelled'], true) || $session->actual_end !== null) {
                $counts['skipped_completed_count']++;

                continue;
            }

            if ($session->status === 'active' || $session->actual_start !== null || $session->qr_started_at !== null) {
                $counts['skipped_started_count']++;

                continue;
            }

            if (! $session->scheduledStartAt()?->greaterThan($now)) {
                $counts['skipped_past_count']++;

                continue;
            }

            $oldLecturer = $session->lecturerIdentity;
            $oldHall = $session->hall;

            $eligible->push([
                'id' => $session->id,
                'session_date' => $session->session_date->toDateString(),
                'start_time' => (string) $session->start_time,
                'end_time' => (string) $session->end_time,
                'update_lecturer' => $updateLecturer,
                'update_hall' => $updateHall,
                'old_lecturer_identity_id' => $session->lecturer_identity_id,
                'old_lecturer_name' => $oldLecturer instanceof Lecturer ? $oldLecturer->name : null,
                'old_hall_id' => $session->hall_id,
                'old_hall_name' => $oldHall instanceof Hall ? $oldHall->name : null,
            ]);
        }

        $conflicts = $this->sessionConflicts($sessions, $eligible, $targetLecturer, $targetHall, $lockConflicts);
        $lecturerChanges = $eligible->where('update_lecturer', true);
        $hallChanges = $eligible->where('update_hall', true);

        return [
            'schedule_slot_id' => $slot->id,
            'academic_term_id' => $slot->academic_term_id,
            'batch_id' => $slot->import_batch_id,
            'source_session_count' => $sessions->count(),
            'eligible_future_count' => $eligible->count(),
            ...$counts,
            'conflict_count' => count($conflicts),
            'conflicts' => $conflicts,
            'eligible_sessions' => $eligible->all(),
            'lecturer_from' => $eligible->first()['old_lecturer_identity_id'] ?? null,
            'lecturer_to' => $targetLecturerIsReal ? $targetLecturer->id : null,
            'hall_from' => $eligible->first()['old_hall_id'] ?? null,
            'hall_to' => $targetHallIsReal ? $targetHall->id : null,
            'lecturer_from_names' => $lecturerChanges->pluck('old_lecturer_name')->filter()->unique()->values()->all(),
            'hall_from_names' => $hallChanges->pluck('old_hall_name')->filter()->unique()->values()->all(),
            'target_lecturer_name' => $targetLecturer?->name,
            'target_hall_name' => $targetHall?->name,
            'lecturer_account_missing' => $targetLecturerIsReal && $this->validLecturerUserId($targetLecturer) === null,
        ];
    }

    /** @param EloquentCollection<int, LectureSession> $sessions @param Collection<int, array<string, mixed>> $eligible @return array<int, array<string, mixed>> */
    private function sessionConflicts(
        EloquentCollection $sessions,
        Collection $eligible,
        ?Lecturer $targetLecturer,
        ?Hall $targetHall,
        bool $lock = false,
    ): array {
        if ($eligible->isEmpty()) {
            return [];
        }

        $dates = $eligible->pluck('session_date')->unique()->sort()->values();
        $targetUserId = $this->validLecturerUserId($targetLecturer);
        $otherSessions = LectureSession::query()
            ->whereBetween('session_date', [$dates->first(), $dates->last()])
            ->whereNotIn('id', $sessions->pluck('id')->all())
            ->where('status', '!=', 'cancelled')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get(['id', 'lecturer_id', 'lecturer_identity_id', 'hall_id', 'session_date', 'start_time', 'end_time']);
        $conflicts = [];

        foreach ($eligible as $candidate) {
            foreach ($otherSessions as $other) {
                if ($other->session_date->toDateString() !== $candidate['session_date']
                    || ! $this->timesOverlap((string) $candidate['start_time'], (string) $candidate['end_time'], (string) $other->start_time, (string) $other->end_time)) {
                    continue;
                }

                if (($candidate['update_lecturer'] ?? false) === true
                    && $targetLecturer instanceof Lecturer
                    && ((int) $other->lecturer_identity_id === $targetLecturer->id
                        || ($targetUserId !== null && (int) $other->lecturer_id === $targetUserId))) {
                    $conflicts[] = ['session_id' => $candidate['id'], 'conflicting_session_id' => $other->id, 'type' => 'lecturer'];
                }

                if (($candidate['update_hall'] ?? false) === true
                    && $targetHall instanceof Hall
                    && (int) $other->hall_id === $targetHall->id) {
                    $conflicts[] = ['session_id' => $candidate['id'], 'conflicting_session_id' => $other->id, 'type' => 'hall'];
                }
            }
        }

        return collect($conflicts)->unique(fn (array $conflict): string => implode('|', $conflict))->values()->all();
    }

    /** @return array<int, array{type: string, slot_id: int}> */
    private function weeklySlotConflicts(SubjectSectionScheduleSlot $slot, array $changes): array
    {
        $query = SubjectSectionScheduleSlot::query()
            ->where('academic_term_id', $slot->academic_term_id)
            ->where('weekday', $slot->weekday)
            ->where('start_time', '<', $slot->end_time)
            ->where('end_time', '>', $slot->start_time)
            ->whereKeyNot($slot->id)
            ->lockForUpdate();
        $conflicts = collect();

        if (isset($changes['lecturer_id'])) {
            $conflicts->push(...(clone $query)->where('lecturer_id', $changes['lecturer_id'])->pluck('id')->map(
                fn (int $id): array => ['type' => 'lecturer', 'slot_id' => $id],
            ));
        }

        if (isset($changes['hall_id'])) {
            $conflicts->push(...(clone $query)->where('hall_id', $changes['hall_id'])->pluck('id')->map(
                fn (int $id): array => ['type' => 'hall', 'slot_id' => $id],
            ));
        }

        return $conflicts->unique(fn (array $conflict): string => $conflict['type'].'|'.$conflict['slot_id'])->values()->all();
    }

    private function validLecturerUserId(?Lecturer $lecturer): ?int
    {
        $user = $lecturer?->user;

        return $user instanceof User
            && ! $user->trashed()
            && ($user->is_active ?? true)
            && $user->status === 'active'
            && $user->hasRole('course_lecturer')
                ? $user->id
                : null;
    }

    private function timesOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        return $startA < $endB && $endA > $startB;
    }
}
