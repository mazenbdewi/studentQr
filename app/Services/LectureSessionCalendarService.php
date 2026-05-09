<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\LectureSession;
use App\Models\Subject;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LectureSessionCalendarService
{
    private const MAX_SESSIONS_PER_BATCH = 500;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    /**
     * @return array{created: int, skipped: int, total: int, created_ids: array<int>}
     */
    public function createRecurring(array $data): array
    {
        $subject = Subject::query()
            ->withoutTrashed()
            ->findOrFail($data['subject_id']);

        if (blank($subject->lecturer_id)) {
            throw ValidationException::withMessages([
                'subject_id' => __('lecture-session.subject_has_no_lecturer'),
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

        $dates = $this->matchingDates($from, $to, $weekdays);

        if (count($dates) > self::MAX_SESSIONS_PER_BATCH) {
            throw ValidationException::withMessages([
                'date_to' => __('lecture-session.recurring_too_many_sessions', [
                    'max' => self::MAX_SESSIONS_PER_BATCH,
                ]),
            ]);
        }

        $createdIds = [];
        $skipped = 0;

        DB::transaction(function () use ($data, $dates, $subject, $startTime, $endTime, &$createdIds, &$skipped): void {
            foreach ($dates as $date) {
                $exists = LectureSession::query()
                    ->where('subject_id', $subject->id)
                    ->where('hall_id', $data['hall_id'])
                    ->whereDate('session_date', $date->toDateString())
                    ->whereTime('start_time', $startTime)
                    ->whereTime('end_time', $endTime)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                $session = LectureSession::create([
                    'subject_id' => $subject->id,
                    'lecturer_id' => $subject->lecturer_id,
                    'hall_id' => $data['hall_id'],
                    'session_date' => $date->toDateString(),
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'status' => $data['status'] ?? 'scheduled',
                    'attendance_mode' => 'qr_otp',
                    'qr_refresh_rate' => (int) ($data['qr_refresh_rate'] ?? AppSetting::defaultQrRefreshRate()),
                    'notes' => $data['notes'] ?? null,
                ]);

                $createdIds[] = $session->id;
            }
        });

        $this->activityLogger->log([
            'category' => 'lecture_sessions',
            'action' => 'create_recurring',
            'description' => 'lecture_sessions_recurring_created',
            'new_values' => [
                'created_count' => count($createdIds),
                'skipped_count' => $skipped,
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'weekdays' => $weekdays,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ],
            'context' => [
                'subject_id' => $subject->id,
                'hall_id' => $data['hall_id'],
                'created_ids' => $createdIds,
            ],
        ], heavy: true);

        return [
            'created' => count($createdIds),
            'skipped' => $skipped,
            'total' => count($dates),
            'created_ids' => $createdIds,
        ];
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
}
