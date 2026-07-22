<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\AppSetting;
use App\Models\ImportBatch;
use App\Models\LectureSession;
use App\Models\LectureSessionGenerationRun;
use App\Models\ScheduleImportRow;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LectureSessionGenerationService
{
    private const STATUS_TO_CREATE = 'to_create';

    private const STATUS_ALREADY_EXISTS = 'already_exists';

    private const STATUS_MANUAL_EXISTS = 'manual_exists';

    private const STATUS_CONFLICT = 'conflict';

    public function preview(AcademicTerm $term): array
    {
        $term->refresh();

        $dateRange = $this->dateRange($term);
        $prerequisiteErrors = $this->prerequisiteErrors($term, $dateRange);
        $excludedSlotIds = $this->excludedScheduleSlotIds($term);
        $structuralReadiness = $this->structuralReadiness($term);
        $plannedCandidates = [];

        $preview = [
            'ready' => false,
            'academic_term_id' => $term->id,
            'teaching_start_date' => $dateRange['start']?->toDateString(),
            'teaching_end_date' => $dateRange['end']?->toDateString(),
            'prerequisite_errors' => $prerequisiteErrors,
            'source_slot_count' => 0,
            'excluded_slot_count' => 0,
            'candidate_session_count' => 0,
            'to_create_count' => 0,
            'already_existing_count' => 0,
            'manual_existing_count' => 0,
            'blocked_slot_count' => 0,
            'conflict_count' => 0,
            'structural_readiness' => $structuralReadiness,
            'blocked_slots' => [],
            'conflicts' => [],
            'candidates' => [],
        ];

        if ($dateRange['start'] === null || $dateRange['end'] === null) {
            $preview['source_slot_count'] = $structuralReadiness['total_weekly_slots'];
            $preview['blocked_slot_count'] = $structuralReadiness['blocked_slots'];

            return $preview;
        }

        /** @var Collection<int, SubjectSectionScheduleSlot> $slots */
        $slots = $this->sourceSlotQuery($term)
            ->with(['subject', 'subjectSection', 'lecturer.user', 'hall'])
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        $preview['source_slot_count'] = $slots->count();

        foreach ($slots as $slot) {
            if ($excludedSlotIds->contains($slot->id)) {
                $preview['excluded_slot_count']++;
                $preview['blocked_slots'][] = $this->blockedSlot($slot, ['excluded_from_weekly_schedule']);

                continue;
            }

            $slotErrors = $this->slotErrors($slot);
            $sessionDates = $this->sessionDatesForSlot($slot, $dateRange['start'], $dateRange['end']);

            if ($slotErrors !== []) {
                $preview['blocked_slots'][] = $this->blockedSlot($slot, $slotErrors, count($sessionDates));

                continue;
            }

            foreach ($sessionDates as $sessionDate) {
                $candidate = $this->candidateForSlot($slot, $sessionDate);
                $evaluation = $this->evaluateCandidate($candidate, $plannedCandidates);
                $candidate = [
                    ...$candidate,
                    ...$evaluation,
                ];

                $preview['candidate_session_count']++;
                $preview['candidates'][] = $candidate;

                match ($candidate['status']) {
                    self::STATUS_TO_CREATE => $preview['to_create_count']++,
                    self::STATUS_ALREADY_EXISTS => $preview['already_existing_count']++,
                    self::STATUS_MANUAL_EXISTS => $preview['manual_existing_count']++,
                    self::STATUS_CONFLICT => $preview['conflict_count']++,
                    default => null,
                };

                if ($candidate['status'] === self::STATUS_TO_CREATE) {
                    $plannedCandidates[] = $candidate;
                }

                if ($candidate['status'] === self::STATUS_CONFLICT) {
                    $preview['conflicts'][] = $candidate;
                }
            }
        }

        $preview['blocked_slot_count'] = count($preview['blocked_slots']);
        $preview['ready'] = $prerequisiteErrors === []
            && $preview['blocked_slot_count'] === 0
            && $preview['conflict_count'] === 0;

        return $preview;
    }

    public function generate(AcademicTerm $term, ?User $user = null): array
    {
        $preview = $this->preview($term);

        if (! $preview['ready']) {
            throw ValidationException::withMessages([
                'academic_term_id' => __('lecture-session.weekly_generation_not_ready'),
            ]);
        }

        return DB::transaction(function () use ($term, $user, $preview): array {
            $now = now(config('app.timezone', 'Asia/Damascus'));
            $run = LectureSessionGenerationRun::query()->create([
                'academic_term_id' => $term->id,
                'schedule_import_batch_id' => $this->latestWeeklyScheduleBatch($term)?->id,
                'started_by' => $user?->id,
                'teaching_start_date' => $preview['teaching_start_date'],
                'teaching_end_date' => $preview['teaching_end_date'],
                'status' => 'running',
                'source_slot_count' => $preview['source_slot_count'],
                'candidate_session_count' => $preview['candidate_session_count'],
                'blocked_slot_count' => $preview['blocked_slot_count'],
                'conflict_count' => $preview['conflict_count'],
                'summary' => $preview,
                'started_at' => $now,
            ]);

            $created = 0;
            $skipped = 0;

            foreach ($preview['candidates'] as $candidate) {
                if ($candidate['status'] !== self::STATUS_TO_CREATE) {
                    $skipped++;

                    continue;
                }

                $freshEvaluation = $this->evaluateCandidate($candidate, []);

                if ($freshEvaluation['status'] === self::STATUS_ALREADY_EXISTS || $freshEvaluation['status'] === self::STATUS_MANUAL_EXISTS) {
                    $skipped++;

                    continue;
                }

                if ($freshEvaluation['status'] !== self::STATUS_TO_CREATE) {
                    throw ValidationException::withMessages([
                        'academic_term_id' => __('lecture-session.weekly_generation_conflict_detected'),
                    ]);
                }

                LectureSession::query()->create([
                    'academic_term_id' => $term->id,
                    'subject_id' => $candidate['subject_id'],
                    'subject_section_id' => $candidate['subject_section_id'],
                    'subject_section_schedule_slot_id' => $candidate['source_slot_id'],
                    'lecture_session_generation_run_id' => $run->id,
                    'generated_from_weekly_schedule_at' => $now,
                    'lecturer_id' => $candidate['lecturer_user_id'],
                    'hall_id' => $candidate['hall_id'],
                    'session_date' => $candidate['session_date'],
                    'start_time' => $candidate['start_time'],
                    'end_time' => $candidate['end_time'],
                    'status' => 'scheduled',
                    'attendance_mode' => 'qr_otp',
                    'qr_refresh_rate' => AppSetting::defaultQrRefreshRate(),
                    'expected_students' => $candidate['expected_student_count'] ?? 0,
                    'notes' => __('lecture-session.generated_from_weekly_schedule_note'),
                ]);

                $created++;
            }

            $run->update([
                'status' => 'completed',
                'created_session_count' => $created,
                'skipped_session_count' => $skipped,
                'summary' => [
                    ...$preview,
                    'created_session_count' => $created,
                    'skipped_session_count' => $skipped,
                ],
                'completed_at' => now(config('app.timezone', 'Asia/Damascus')),
            ]);

            return [
                ...$preview,
                'generation_run_id' => $run->id,
                'created_session_count' => $created,
                'skipped_session_count' => $skipped,
            ];
        });
    }

    public function generateReadySessions(AcademicTerm $term, ?User $user = null): array
    {
        $preview = $this->preview($term);
        $fatalPrerequisites = $preview['prerequisite_errors'];

        if ($fatalPrerequisites !== []) {
            throw ValidationException::withMessages([
                'academic_term_id' => __('lecture-session.weekly_generation_not_ready'),
            ]);
        }

        $now = now(config('app.timezone', 'Asia/Damascus'));
        $unsafeSourceSlotIds = $this->unsafeConflictSourceSlotIds($preview['conflicts']);
        $safeCandidates = collect($preview['candidates'])
            ->filter(fn (array $candidate): bool => $candidate['status'] === self::STATUS_TO_CREATE)
            ->reject(fn (array $candidate): bool => in_array((int) $candidate['source_slot_id'], $unsafeSourceSlotIds, true))
            ->values();

        $run = LectureSessionGenerationRun::query()->create([
            'academic_term_id' => $term->id,
            'schedule_import_batch_id' => $this->latestWeeklyScheduleBatch($term)?->id,
            'started_by' => $user?->id,
            'teaching_start_date' => $preview['teaching_start_date'],
            'teaching_end_date' => $preview['teaching_end_date'],
            'status' => 'running',
            'source_slot_count' => $preview['source_slot_count'],
            'candidate_session_count' => $preview['candidate_session_count'],
            'blocked_slot_count' => $preview['blocked_slot_count'],
            'conflict_count' => $preview['conflict_count'],
            'summary' => $preview,
            'started_at' => $now,
        ]);

        $successReport = collect($preview['candidates'])
            ->filter(fn (array $candidate): bool => in_array($candidate['status'], [self::STATUS_ALREADY_EXISTS, self::STATUS_MANUAL_EXISTS], true))
            ->map(fn (array $candidate): array => $this->sessionSuccessReportRow($candidate, $candidate['status'] === self::STATUS_ALREADY_EXISTS ? 'already_exists' : 'manual_exists'))
            ->values()
            ->all();
        $errorReport = $this->sessionErrorReportRows($preview['blocked_slots'], $preview['conflicts'], $unsafeSourceSlotIds);
        $createdCount = 0;
        $skipped = $preview['already_existing_count'] + $preview['manual_existing_count'];

        foreach ($safeCandidates as $candidate) {
            try {
                $freshEvaluation = $this->evaluateCandidate($candidate, []);

                if ($freshEvaluation['status'] === self::STATUS_ALREADY_EXISTS || $freshEvaluation['status'] === self::STATUS_MANUAL_EXISTS) {
                    $skipped++;
                    $successReport[] = $this->sessionSuccessReportRow($candidate, $freshEvaluation['status'] === self::STATUS_ALREADY_EXISTS ? 'already_exists' : 'manual_exists');

                    continue;
                }

                if ($freshEvaluation['status'] !== self::STATUS_TO_CREATE) {
                    $skipped++;
                    $errorReport[] = $this->sessionErrorReportRow($candidate, 'scheduling_conflict', __('lecture-session.report_reasons.scheduling_conflict'));

                    continue;
                }

                $session = DB::transaction(fn (): LectureSession => LectureSession::query()->create([
                    'academic_term_id' => $term->id,
                    'subject_id' => $candidate['subject_id'],
                    'subject_section_id' => $candidate['subject_section_id'],
                    'subject_section_schedule_slot_id' => $candidate['source_slot_id'],
                    'lecture_session_generation_run_id' => $run->id,
                    'generated_from_weekly_schedule_at' => $now,
                    'lecturer_id' => $candidate['lecturer_user_id'],
                    'hall_id' => $candidate['hall_id'],
                    'session_date' => $candidate['session_date'],
                    'start_time' => $candidate['start_time'],
                    'end_time' => $candidate['end_time'],
                    'status' => 'scheduled',
                    'attendance_mode' => 'qr_otp',
                    'qr_refresh_rate' => AppSetting::defaultQrRefreshRate(),
                    'expected_students' => $candidate['expected_student_count'] ?? 0,
                    'notes' => __('lecture-session.generated_from_weekly_schedule_note'),
                ]));

                $createdCount++;
                $successReport[] = [
                    ...$this->sessionSuccessReportRow($candidate, 'created'),
                    'lecture_session_id' => (int) $session->id,
                ];
            } catch (\Throwable) {
                $skipped++;
                $errorReport[] = $this->sessionErrorReportRow($candidate, 'unexpected_item_failure', __('lecture-session.report_reasons.unexpected_item_failure'));
            }
        }

        $run->update([
            'status' => $errorReport !== [] ? 'completed_with_errors' : 'completed',
            'created_session_count' => $createdCount,
            'skipped_session_count' => $skipped,
            'summary' => [
                ...$preview,
                'created_session_count' => $createdCount,
                'skipped_session_count' => $skipped,
                'success_report' => $successReport,
                'error_report' => $errorReport,
            ],
            'completed_at' => now(config('app.timezone', 'Asia/Damascus')),
        ]);

        return [
            ...$preview,
            'ready_for_partial_generation' => $preview['prerequisite_errors'] === []
                && $safeCandidates->isNotEmpty(),
            'generation_run_id' => $run->id,
            'created_session_count' => $createdCount,
            'skipped_session_count' => $skipped,
            'success_report' => $successReport,
            'error_report' => $errorReport,
        ];
    }

    /** @return array{start: ?CarbonImmutable, end: ?CarbonImmutable} */
    private function dateRange(AcademicTerm $term): array
    {
        if (blank($term->teaching_start_date) || blank($term->teaching_end_date)) {
            return ['start' => null, 'end' => null];
        }

        try {
            $timezone = config('app.timezone', 'Asia/Damascus');

            return [
                'start' => CarbonImmutable::parse($term->teaching_start_date, $timezone)->startOfDay(),
                'end' => CarbonImmutable::parse($term->teaching_end_date, $timezone)->startOfDay(),
            ];
        } catch (\Throwable) {
            return ['start' => null, 'end' => null];
        }
    }

    /** @param array{start: ?CarbonImmutable, end: ?CarbonImmutable} $dateRange */
    private function prerequisiteErrors(AcademicTerm $term, array $dateRange): array
    {
        $errors = [];

        if ($dateRange['start'] === null || $dateRange['end'] === null) {
            $errors[] = 'missing_teaching_dates';
        } elseif ($dateRange['end']->lt($dateRange['start'])) {
            $errors[] = 'invalid_teaching_date_range';
        }

        if (! $this->hasCompletedEnrollmentBatch($term)) {
            $errors[] = 'missing_completed_enrollment_batch';
        }

        if (! $this->hasCompletedWeeklyScheduleBatch($term)) {
            $errors[] = 'missing_completed_weekly_schedule_batch';
        }

        if (! $this->sourceSlotQuery($term)->exists()) {
            $errors[] = 'missing_weekly_schedule_slots';
        }

        return $errors;
    }

    /** @return Builder<SubjectSectionScheduleSlot> */
    private function sourceSlotQuery(AcademicTerm $term): Builder
    {
        return SubjectSectionScheduleSlot::query()
            ->where('academic_term_id', $term->id);
    }

    private function hasCompletedEnrollmentBatch(AcademicTerm $term): bool
    {
        return ImportBatch::query()
            ->eligibleEnrollmentSource()
            ->whereHas('academicTerms', fn (Builder $query): Builder => $query->whereKey($term->id))
            ->exists();
    }

    private function hasCompletedWeeklyScheduleBatch(AcademicTerm $term): bool
    {
        return ImportBatch::query()
            ->where('import_type', ImportBatch::TYPE_WEEKLY_SCHEDULE)
            ->where('status', ImportBatch::STATUS_COMPLETED)
            ->whereHas('academicTerms', fn (Builder $query): Builder => $query->whereKey($term->id))
            ->whereHas('scheduleSlots', fn (Builder $query): Builder => $query->where('academic_term_id', $term->id))
            ->exists();
    }

    private function latestWeeklyScheduleBatch(AcademicTerm $term): ?ImportBatch
    {
        return ImportBatch::query()
            ->where('import_type', ImportBatch::TYPE_WEEKLY_SCHEDULE)
            ->where('status', ImportBatch::STATUS_COMPLETED)
            ->whereHas('academicTerms', fn (Builder $query): Builder => $query->whereKey($term->id))
            ->whereHas('scheduleSlots', fn (Builder $query): Builder => $query->where('academic_term_id', $term->id))
            ->latest('completed_at')
            ->latest('id')
            ->first();
    }

    private function excludedScheduleSlotIds(AcademicTerm $term): Collection
    {
        return ScheduleImportRow::query()
            ->where('academic_term_id', $term->id)
            ->where(function (Builder $query): void {
                $query
                    ->whereNotNull('excluded_from_weekly_schedule_at')
                    ->orWhere('current_reconciliation_status', ScheduleImportRow::STATUS_EXCLUDED_FROM_BATCH_SCHEDULE);
            })
            ->get()
            ->flatMap(fn (ScheduleImportRow $row): array => $row->relatedScheduleSlotIds())
            ->unique()
            ->values();
    }

    public function structuralReadiness(AcademicTerm $term): array
    {
        /** @var Collection<int, SubjectSectionScheduleSlot> $slots */
        $slots = $this->sourceSlotQuery($term)
            ->with(['subject', 'subjectSection', 'lecturer.user.roles', 'hall'])
            ->get();

        $validSubjectAndSection = $slots->filter(fn (SubjectSectionScheduleSlot $slot): bool => $this->hasValidSubjectSection($slot));
        $withLecturerIdentity = $slots->filter(fn (SubjectSectionScheduleSlot $slot): bool => filled($slot->lecturer_id) && filled($slot->lecturer));
        $withValidLinkedLecturerAccountAndRole = $slots->filter(function (SubjectSectionScheduleSlot $slot): bool {
            $user = $slot->lecturer?->user;

            return $user instanceof User
                && ! $user->trashed()
                && ($user->is_active ?? true)
                && $user->status === 'active'
                && $user->hasRole('course_lecturer');
        });
        $withHalls = $slots->filter(fn (SubjectSectionScheduleSlot $slot): bool => filled($slot->hall_id) && filled($slot->hall));
        $otherwiseInvalid = $slots->reject(fn (SubjectSectionScheduleSlot $slot): bool => $this->hasValidSubjectSection($slot)
            && $slot->weekday >= 1
            && $slot->weekday <= 7
            && filled($slot->start_time)
            && filled($slot->end_time)
            && strcmp((string) $slot->start_time, (string) $slot->end_time) < 0);
        $ready = $slots->filter(fn (SubjectSectionScheduleSlot $slot): bool => $this->slotErrors($slot) === []);

        return [
            'total_weekly_slots' => $slots->count(),
            'valid_subject_and_section' => $validSubjectAndSection->count(),
            'slots_with_lecturer_identity' => $withLecturerIdentity->count(),
            'slots_without_lecturer_identity' => $slots->count() - $withLecturerIdentity->count(),
            'slots_with_valid_linked_lecturer_account_and_role' => $withValidLinkedLecturerAccountAndRole->count(),
            'slots_with_halls' => $withHalls->count(),
            'slots_without_halls' => $slots->count() - $withHalls->count(),
            'slots_otherwise_invalid' => $otherwiseInvalid->count(),
            'ready_slots' => $ready->count(),
            'blocked_slots' => $slots->count() - $ready->count(),
        ];
    }

    private function slotErrors(SubjectSectionScheduleSlot $slot): array
    {
        $errors = [];

        if ($slot->weekday < 1 || $slot->weekday > 7) {
            $errors[] = 'invalid_weekday';
        }

        if (blank($slot->start_time) || blank($slot->end_time) || strcmp((string) $slot->start_time, (string) $slot->end_time) >= 0) {
            $errors[] = 'invalid_time_range';
        }

        if (! $this->hasValidSubjectSection($slot)) {
            $errors[] = 'invalid_subject_section';
        }

        if (! $slot->lecturer) {
            $errors[] = 'missing_lecturer_identity';
        } else {
            $lecturerLogin = $slot->lecturer->user;

            if (! $slot->lecturer->user_id || ! $lecturerLogin instanceof User || $lecturerLogin->trashed() || ! ($lecturerLogin->is_active ?? true)) {
                $errors[] = 'missing_active_lecturer_login';
            } elseif (! $lecturerLogin->hasRole('course_lecturer')) {
                $errors[] = 'missing_course_lecturer_role';
            }
        }

        if (! $slot->hall_id || ! $slot->hall) {
            $errors[] = 'missing_hall';
        }

        return $errors;
    }

    private function hasValidSubjectSection(SubjectSectionScheduleSlot $slot): bool
    {
        $section = $slot->subjectSection;

        return $section instanceof SubjectSection && (int) $section->subject_id === (int) $slot->subject_id;
    }

    private function sessionDatesForSlot(SubjectSectionScheduleSlot $slot, CarbonImmutable $start, CarbonImmutable $end): array
    {
        if ($slot->weekday < 1 || $slot->weekday > 7) {
            return [];
        }

        $offset = ((int) $slot->weekday - $start->isoWeekday() + 7) % 7;
        $dates = [];

        for ($date = $start->addDays($offset); $date->lte($end); $date = $date->addWeek()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    private function candidateForSlot(SubjectSectionScheduleSlot $slot, string $sessionDate): array
    {
        return [
            'source_slot_id' => $slot->id,
            'academic_term_id' => $slot->academic_term_id,
            'subject_id' => $slot->subject_id,
            'subject_section_id' => $slot->subject_section_id,
            'lecturer_identity_id' => $slot->lecturer_id,
            'lecturer_user_id' => $slot->lecturer->user_id,
            'hall_id' => $slot->hall_id,
            'session_date' => $sessionDate,
            'weekday' => $slot->weekday,
            'start_time' => (string) $slot->start_time,
            'end_time' => (string) $slot->end_time,
            'expected_student_count' => $slot->expected_student_count,
        ];
    }

    private function evaluateCandidate(array $candidate, array $plannedCandidates): array
    {
        $sourceExists = LectureSession::query()
            ->where('subject_section_schedule_slot_id', $candidate['source_slot_id'])
            ->whereDate('session_date', $candidate['session_date'])
            ->exists();

        if ($sourceExists) {
            return ['status' => self::STATUS_ALREADY_EXISTS, 'reason' => 'source_date_already_generated'];
        }

        $manualExists = LectureSession::query()
            ->whereNull('subject_section_schedule_slot_id')
            ->where('subject_id', $candidate['subject_id'])
            ->where('subject_section_id', $candidate['subject_section_id'])
            ->where('lecturer_id', $candidate['lecturer_user_id'])
            ->where('hall_id', $candidate['hall_id'])
            ->whereDate('session_date', $candidate['session_date'])
            ->whereTime('start_time', $candidate['start_time'])
            ->whereTime('end_time', $candidate['end_time'])
            ->exists();

        if ($manualExists) {
            return ['status' => self::STATUS_MANUAL_EXISTS, 'reason' => 'matching_manual_session_exists'];
        }

        $persistedConflict = $this->persistedConflict($candidate);

        if ($persistedConflict !== null) {
            return [
                'status' => self::STATUS_CONFLICT,
                'reason' => 'persisted_session_overlap',
                'conflicting_session_id' => $persistedConflict->id,
            ];
        }

        $plannedConflict = $this->plannedConflict($candidate, $plannedCandidates);

        if ($plannedConflict !== null) {
            return [
                'status' => self::STATUS_CONFLICT,
                'reason' => 'weekly_schedule_overlap',
                'conflicting_source_slot_id' => $plannedConflict['source_slot_id'],
            ];
        }

        return ['status' => self::STATUS_TO_CREATE, 'reason' => null];
    }

    private function persistedConflict(array $candidate): ?LectureSession
    {
        return LectureSession::query()
            ->whereDate('session_date', $candidate['session_date'])
            ->whereTime('start_time', '<', $candidate['end_time'])
            ->whereTime('end_time', '>', $candidate['start_time'])
            ->where(function (Builder $query) use ($candidate): void {
                $query
                    ->where('subject_section_id', $candidate['subject_section_id'])
                    ->orWhere('lecturer_id', $candidate['lecturer_user_id'])
                    ->orWhere('hall_id', $candidate['hall_id']);
            })
            ->orderBy('start_time')
            ->first();
    }

    private function plannedConflict(array $candidate, array $plannedCandidates): ?array
    {
        foreach ($plannedCandidates as $plannedCandidate) {
            if ($plannedCandidate['session_date'] !== $candidate['session_date']) {
                continue;
            }

            if (! $this->timesOverlap(
                $plannedCandidate['start_time'],
                $plannedCandidate['end_time'],
                $candidate['start_time'],
                $candidate['end_time'],
            )) {
                continue;
            }

            if (
                (int) $plannedCandidate['subject_section_id'] === (int) $candidate['subject_section_id']
                || (int) $plannedCandidate['lecturer_user_id'] === (int) $candidate['lecturer_user_id']
                || (int) $plannedCandidate['hall_id'] === (int) $candidate['hall_id']
            ) {
                return $plannedCandidate;
            }
        }

        return null;
    }

    private function timesOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        return $startA < $endB && $endA > $startB;
    }

    private function sessionSuccessReportRow(array $candidate, string $result): array
    {
        $slot = SubjectSectionScheduleSlot::query()
            ->with(['subject', 'subjectSection', 'lecturer.user', 'hall'])
            ->find($candidate['source_slot_id']);
        $lecturerUser = $slot?->lecturer?->user;

        return [
            'المادة' => $slot?->subject?->getAttribute('name') ?? (string) $candidate['subject_id'],
            'الشعبة' => $slot?->subjectSection?->getAttribute('code') ?? (string) $candidate['subject_section_id'],
            'المدرس' => $slot?->lecturer?->getAttribute('name') ?? (string) ($candidate['lecturer_identity_id'] ?? ''),
            'اسم الدخول' => $lecturerUser instanceof User ? ($lecturerUser->login_username ?? $lecturerUser->email) : null,
            'القاعة' => $slot?->hall?->getAttribute('name') ?? (string) $candidate['hall_id'],
            'التاريخ' => $candidate['session_date'],
            'وقت البداية' => $candidate['start_time'],
            'وقت النهاية' => $candidate['end_time'],
            'النتيجة' => $result,
            'source_slot_id' => (int) $candidate['source_slot_id'],
        ];
    }

    private function sessionErrorReportRows(array $blockedSlots, array $conflicts, array $unsafeSourceSlotIds): array
    {
        return collect($blockedSlots)
            ->flatMap(fn (array $slot): array => collect($slot['reasons'] ?? ['blocked'])
                ->map(fn (string $reason): array => $this->sessionErrorReportRow($slot, $reason, $this->arabicGenerationReason($reason)))
                ->all())
            ->merge(collect($conflicts)->map(fn (array $conflict): array => $this->sessionErrorReportRow(
                $conflict,
                (string) ($conflict['reason'] ?? 'scheduling_conflict'),
                $this->arabicGenerationReason((string) ($conflict['reason'] ?? 'scheduling_conflict')),
            )))
            ->merge(collect($unsafeSourceSlotIds)->map(fn (int $slotId): array => $this->sessionErrorReportRow(
                ['source_slot_id' => $slotId],
                'scheduling_conflict',
                $this->arabicGenerationReason('scheduling_conflict'),
            )))
            ->values()
            ->all();
    }

    private function sessionErrorReportRow(array $source, string $code, string $reason): array
    {
        $slot = SubjectSectionScheduleSlot::query()
            ->with(['subject', 'subjectSection', 'lecturer.user', 'hall'])
            ->find($source['source_slot_id'] ?? null);

        return [
            'الموعد الأسبوعي المصدر' => $source['source_slot_id'] ?? null,
            'المادة' => $slot?->subject?->getAttribute('name'),
            'الشعبة' => $slot?->subjectSection?->getAttribute('code'),
            'المدرس' => $slot?->lecturer?->getAttribute('name'),
            'القاعة' => $slot?->hall?->getAttribute('name'),
            'اليوم' => $slot?->weekday,
            'الوقت' => collect([$slot?->start_time, $slot?->end_time])->filter()->implode(' - '),
            'رمز الخطأ' => $code,
            'السبب بالعربية' => $reason,
            'الإجراء المقترح' => __('lecture-session.report_actions.'.$code) !== 'lecture-session.report_actions.'.$code
                ? __('lecture-session.report_actions.'.$code)
                : __('lecture-session.report_actions.default'),
        ];
    }

    private function arabicGenerationReason(string $reason): string
    {
        $translation = __('lecture-session.report_reasons.'.$reason);

        return $translation === 'lecture-session.report_reasons.'.$reason
            ? $reason
            : $translation;
    }

    private function unsafeConflictSourceSlotIds(array $conflicts): array
    {
        return collect($conflicts)
            ->flatMap(fn (array $conflict): array => array_filter([
                (int) ($conflict['source_slot_id'] ?? 0),
                (int) ($conflict['conflicting_source_slot_id'] ?? 0),
            ]))
            ->unique()
            ->values()
            ->all();
    }

    private function blockedSlot(SubjectSectionScheduleSlot $slot, array $reasons, int $occurrenceCount = 0): array
    {
        return [
            'source_slot_id' => $slot->id,
            'subject_id' => $slot->subject_id,
            'subject_section_id' => $slot->subject_section_id,
            'lecturer_identity_id' => $slot->lecturer_id,
            'hall_id' => $slot->hall_id,
            'weekday' => $slot->weekday,
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'occurrence_count' => $occurrenceCount,
            'reasons' => $reasons,
        ];
    }
}
