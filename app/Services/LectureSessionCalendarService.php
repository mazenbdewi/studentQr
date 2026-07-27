<?php

namespace App\Services;

use App\Filament\Resources\LectureSessions\LectureSessionResource;
use App\Models\AcademicTerm;
use App\Models\AppSetting;
use App\Models\Hall;
use App\Models\LectureSession;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LectureSessionCalendarService
{
    private const MAX_SESSIONS_PER_BATCH = 500;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly LectureSessionLecturerResolver $lecturerResolver,
    ) {}

    /**
     * @return array{created: int, skipped: int, total: int, created_ids: array<int>}
     */
    public function createRecurring(array $data): array
    {
        $preview = $this->previewRecurring($data);
        $context = $preview['context'];
        $readyRows = collect($preview['rows'])
            ->filter(fn (array $row): bool => $row['result'] === 'ready')
            ->values();

        $createdIds = [];

        DB::transaction(function () use ($data, $context, $readyRows, &$createdIds): void {
            foreach ($readyRows as $row) {
                $session = LectureSession::create([
                    'academic_term_id' => $context['term']->id,
                    'subject_id' => $context['subject']->id,
                    'subject_section_id' => $context['section']?->id,
                    'lecturer_id' => $context['lecturer']->id,
                    'hall_id' => $context['hall']->id,
                    'subject_section_schedule_slot_id' => null,
                    'lecture_session_generation_run_id' => null,
                    'generated_from_weekly_schedule_at' => null,
                    'session_date' => $row['date'],
                    'start_time' => $context['start_time'],
                    'end_time' => $context['end_time'],
                    'status' => $data['status'] ?? 'scheduled',
                    'attendance_mode' => 'qr_otp',
                    'qr_refresh_rate' => (int) ($data['qr_refresh_rate'] ?? AppSetting::defaultQrRefreshRate()),
                    'notes' => $data['notes'] ?? null,
                ]);

                $createdIds[] = $session->id;
            }
        });

        $skipped = $preview['skipped_count'];

        $this->activityLogger->log([
            'category' => 'lecture_sessions',
            'action' => 'create_recurring',
            'description' => 'lecture_sessions_recurring_created',
            'new_values' => [
                'created_count' => count($createdIds),
                'skipped_count' => $skipped,
                'date_from' => $context['from']->toDateString(),
                'date_to' => $context['to']->toDateString(),
                'weekdays' => $context['weekdays'],
                'start_time' => $context['start_time'],
                'end_time' => $context['end_time'],
            ],
            'context' => [
                'academic_term_id' => $context['term']->id,
                'subject_id' => $context['subject']->id,
                'subject_section_id' => $context['section']?->id,
                'lecturer_id' => $context['lecturer']->id,
                'hall_id' => $context['hall']->id,
                'created_ids' => $createdIds,
            ],
        ], heavy: true);

        return [
            'created' => count($createdIds),
            'skipped' => $skipped,
            'total' => $preview['total_count'],
            'created_ids' => $createdIds,
        ];
    }

    /**
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     total_count: int,
     *     existing_count: int,
     *     conflict_count: int,
     *     ready_count: int,
     *     skipped_count: int,
     *     context: array<string, mixed>
     * }
     */
    public function previewRecurring(array $data): array
    {
        $context = $this->resolveRecurringContext($data);
        $dates = $this->matchingDates($context['from'], $context['to'], $context['weekdays']);

        if (count($dates) > self::MAX_SESSIONS_PER_BATCH) {
            throw ValidationException::withMessages([
                'date_to' => __('lecture-session.recurring_too_many_sessions', [
                    'max' => self::MAX_SESSIONS_PER_BATCH,
                ]),
            ]);
        }

        $rows = collect($dates)
            ->map(fn (CarbonImmutable $date): array => $this->candidateRow($date, $context))
            ->values()
            ->all();

        $existingCount = collect($rows)->where('result', 'existing')->count();
        $readyCount = collect($rows)->where('result', 'ready')->count();
        $conflictCount = collect($rows)
            ->whereIn('result', ['lecturer_conflict', 'hall_conflict', 'section_conflict', 'outside_teaching_period'])
            ->count();

        return [
            'rows' => $rows,
            'total_count' => count($rows),
            'existing_count' => $existingCount,
            'conflict_count' => $conflictCount,
            'ready_count' => $readyCount,
            'skipped_count' => count($rows) - $readyCount,
            'context' => $context,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRecurringContext(array $data): array
    {
        $term = AcademicTerm::query()->find($data['academic_term_id'] ?? null);

        if (! $term instanceof AcademicTerm) {
            throw ValidationException::withMessages([
                'academic_term_id' => __('validation.required', ['attribute' => __('lecture-session.academic_term')]),
            ]);
        }

        $subject = Subject::query()
            ->withoutTrashed()
            ->findOrFail($data['subject_id']);

        $section = $this->resolveSubjectSection($subject, $data['subject_section_id'] ?? null, $term);
        $lecturerOptions = $this->lecturerResolver->options($term->id, $subject->id, $section?->id);

        if ($lecturerOptions === []) {
            throw ValidationException::withMessages([
                $section ? 'subject_section_id' : 'subject_id' => LectureSessionResource::manualLecturerWarning($term->id, $subject->id, $section?->id),
            ]);
        }

        $selectedLecturerId = (int) ($data['lecturer_id'] ?? 0);

        if ($selectedLecturerId === 0) {
            $selectedLecturerId = count($lecturerOptions) === 1 ? (int) array_key_first($lecturerOptions) : 0;
        }

        if ($selectedLecturerId === 0 || ! array_key_exists($selectedLecturerId, $lecturerOptions)) {
            throw ValidationException::withMessages([
                'lecturer_id' => __('lecture-session.lecturer_must_match_selected_section_schedule'),
            ]);
        }

        $lecturer = User::query()
            ->withoutTrashed()
            ->whereKey($selectedLecturerId)
            ->firstOrFail();

        $hall = Hall::query()
            ->withoutTrashed()
            ->whereKey($data['hall_id'] ?? null)
            ->where('is_active', true)
            ->first();

        if (! $hall instanceof Hall) {
            throw ValidationException::withMessages([
                'hall_id' => __('lecture-session.hall_must_be_active'),
            ]);
        }

        $from = CarbonImmutable::parse($data['date_from'])->startOfDay();
        $to = CarbonImmutable::parse($data['date_to'])->startOfDay();

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'date_to' => __('lecture-session.recurring_date_range_invalid'),
            ]);
        }

        $startTime = $this->normalizeTime($data['start_time']);
        $endTime = $this->normalizeTime($data['end_time']);

        if ($endTime <= $startTime) {
            throw ValidationException::withMessages([
                'end_time' => __('lecture-session.recurring_time_range_invalid'),
            ]);
        }

        $weekdays = collect($data['weekdays'] ?? [])
            ->map(fn (mixed $weekday): int => (int) $weekday)
            ->filter(fn (int $weekday): bool => $weekday >= 0 && $weekday <= 6)
            ->unique()
            ->values()
            ->all();

        if ($weekdays === []) {
            throw ValidationException::withMessages([
                'weekdays' => __('lecture-session.recurring_weekdays_required'),
            ]);
        }

        return [
            'term' => $term,
            'subject' => $subject,
            'section' => $section,
            'lecturer' => $lecturer,
            'hall' => $hall,
            'from' => $from,
            'to' => $to,
            'weekdays' => $weekdays,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function candidateRow(CarbonImmutable $date, array $context): array
    {
        $base = [
            'date' => $date->toDateString(),
            'weekday' => $date->dayOfWeek,
            'start_time' => $context['start_time'],
            'end_time' => $context['end_time'],
            'subject' => $context['subject']->name,
            'section' => $context['section']?->code,
            'lecturer' => $context['lecturer']->name,
            'hall' => $context['hall']->name,
        ];

        if (! $this->isInsideTeachingPeriod($date, $context['term'])) {
            return [...$base, 'result' => 'outside_teaching_period'];
        }

        if ($this->matchingSessionExists($date, $context)) {
            return [...$base, 'result' => 'existing'];
        }

        if ($this->overlappingSessionExists($date, $context, 'lecturer_id', $context['lecturer']->id)) {
            return [...$base, 'result' => 'lecturer_conflict'];
        }

        if ($this->overlappingSessionExists($date, $context, 'hall_id', $context['hall']->id)) {
            return [...$base, 'result' => 'hall_conflict'];
        }

        if ($this->sectionConflictExists($date, $context)) {
            return [...$base, 'result' => 'section_conflict'];
        }

        return [...$base, 'result' => 'ready'];
    }

    private function isInsideTeachingPeriod(CarbonImmutable $date, AcademicTerm $term): bool
    {
        $start = $term->teaching_start_date?->toDateString();
        $end = $term->teaching_end_date?->toDateString();

        return $start !== null
            && $end !== null
            && $date->toDateString() >= $start
            && $date->toDateString() <= $end;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function matchingSessionExists(CarbonImmutable $date, array $context): bool
    {
        return LectureSession::query()
            ->where('academic_term_id', $context['term']->id)
            ->where('subject_id', $context['subject']->id)
            ->when(
                $context['section'],
                fn ($query) => $query->where('subject_section_id', $context['section']->id),
                fn ($query) => $query->whereNull('subject_section_id'),
            )
            ->where('hall_id', $context['hall']->id)
            ->whereDate('session_date', $date->toDateString())
            ->whereTime('start_time', $context['start_time'])
            ->whereTime('end_time', $context['end_time'])
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function overlappingSessionExists(CarbonImmutable $date, array $context, string $column, int $id): bool
    {
        return LectureSession::query()
            ->whereDate('session_date', $date->toDateString())
            ->where($column, $id)
            ->whereNotIn('status', ['cancelled'])
            ->whereTime('start_time', '<', $context['end_time'])
            ->whereTime('end_time', '>', $context['start_time'])
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function sectionConflictExists(CarbonImmutable $date, array $context): bool
    {
        $query = LectureSession::query()
            ->whereDate('session_date', $date->toDateString())
            ->whereNotIn('status', ['cancelled'])
            ->whereTime('start_time', '<', $context['end_time'])
            ->whereTime('end_time', '>', $context['start_time']);

        if ($context['section']) {
            $query->where('subject_section_id', $context['section']->id);
        } else {
            $query->where('subject_id', $context['subject']->id)
                ->whereNull('subject_section_id');
        }

        return $query->exists();
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function matchingDates(CarbonImmutable $from, CarbonImmutable $to, array $weekdays): array
    {
        $dates = [];

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            if (in_array($date->dayOfWeek, $weekdays, true)) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    private function normalizeTime(mixed $time): string
    {
        return CarbonImmutable::parse((string) $time)->format('H:i:s');
    }

    private function resolveSubjectSection(Subject $subject, mixed $sectionId, AcademicTerm $term): ?SubjectSection
    {
        if (blank($sectionId)) {
            return null;
        }

        $section = $subject->sections()
            ->whereKey($sectionId)
            ->where('academic_term_id', $term->id)
            ->first();

        if (! $section instanceof SubjectSection) {
            throw ValidationException::withMessages([
                'subject_section_id' => __('lecture-session.section_must_belong_to_subject_and_term'),
            ]);
        }

        return $section;
    }
}
