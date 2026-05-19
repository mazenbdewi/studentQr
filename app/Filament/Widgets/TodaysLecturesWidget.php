<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\LectureSessions\LectureSessionResource;
use App\Models\LectureSession;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TodaysLecturesWidget extends Widget
{
    protected string $view = 'filament.widgets.todays-lectures-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected function getViewData(): array
    {
        $lectures = $this->getLectureCards();

        return [
            'lectures' => $lectures,
            'summary' => $this->getSummary($lectures),
            'todayLabel' => Carbon::today()
                ->locale(app()->getLocale())
                ->translatedFormat('l، j-n-Y'),
        ];
    }

    public static function getTodaysLecturesQueryForUser(?int $userId): Builder
    {
        $query = LectureSession::query()
            ->with(['hall', 'lecturer', 'subject', 'subjectSection'])
            ->whereDate('session_date', today());

        if (! $userId) {
            return $query
                ->whereRaw('1 = 0')
                ->orderBy('start_time');
        }

        return $query
            ->where('lecturer_id', $userId)
            ->orderBy('start_time');
    }

    private function getLectureCards(): Collection
    {
        return static::getTodaysLecturesQueryForUser(auth()->id())
            ->get()
            ->map(fn (LectureSession $lecture): array => $this->mapLectureCard($lecture));
    }

    private function mapLectureCard(LectureSession $lecture): array
    {
        $startAt = $this->sessionDateTime($lecture, 'start_time');
        $endAt = $this->sessionDateTime($lecture, 'end_time');
        $attendanceCount = $lecture->attendances()
            ->select('student_id')
            ->distinct()
            ->count('student_id');
        $expectedStudents = (int) ($lecture->expected_students ?? 0);
        $attendancePercent = $expectedStudents > 0
            ? min(100, (int) round(($attendanceCount / $expectedStudents) * 100))
            : null;

        return [
            'record' => $lecture,
            'subject' => $lecture->subject?->name ?? __('lecture-session.not_available'),
            'section' => $lecture->subjectSection?->code,
            'type' => $lecture->subjectSection?->section_type_label
                ?? $lecture->subject?->subject_type_label
                ?? __('subjects.not_available'),
            'hall' => $lecture->hall?->name ?? __('lecture-session.no_hall'),
            'timeRange' => $this->formatTime($startAt) . ' - ' . $this->formatTime($endAt),
            'startsIn' => $this->relativeStartLabel($lecture, $startAt, $endAt),
            'statusLabel' => $this->statusLabel($lecture, $startAt, $endAt),
            'statusTone' => $this->statusTone($lecture, $startAt, $endAt),
            'attendanceCount' => $attendanceCount,
            'expectedStudents' => $expectedStudents,
            'attendancePercent' => $attendancePercent,
            'detailsUrl' => LectureSessionResource::getUrl('view', ['record' => $lecture]),
            'qrUrl' => $lecture->shouldShowQrAction(auth()->user())
                ? route('teacher.lecture-session.qr', $lecture)
                : null,
        ];
    }

    private function getSummary(Collection $lectures): array
    {
        $now = now();
        $active = $lectures->filter(fn (array $lecture): bool => $lecture['statusTone'] === 'active')->count();
        $completed = $lectures->filter(fn (array $lecture): bool => $lecture['statusTone'] === 'completed')->count();
        $next = $lectures->first(function (array $lecture) use ($now): bool {
            /** @var LectureSession $record */
            $record = $lecture['record'];

            return $record->status === 'scheduled'
                && $this->sessionDateTime($record, 'start_time')->greaterThanOrEqualTo($now);
        });

        return [
            'total' => $lectures->count(),
            'active' => $active,
            'completed' => $completed,
            'next' => $next,
        ];
    }

    private function statusLabel(LectureSession $lecture, Carbon $startAt, Carbon $endAt): string
    {
        if ($lecture->status === 'active' && now()->between($startAt, $endAt)) {
            return __('lecture-session.active_now');
        }

        if ($lecture->status === 'scheduled' && now()->lt($startAt)) {
            return __('lecture-session.upcoming_today');
        }

        if ($lecture->status === 'completed' || $lecture->hasReachedScheduledEnd()) {
            return __('lecture-session.finished_today');
        }

        return __("lecture-session.status_{$lecture->status}");
    }

    private function statusTone(LectureSession $lecture, Carbon $startAt, Carbon $endAt): string
    {
        if ($lecture->status === 'active' && now()->between($startAt, $endAt)) {
            return 'active';
        }

        if ($lecture->status === 'scheduled' && now()->lt($startAt)) {
            return 'upcoming';
        }

        if ($lecture->status === 'completed' || $lecture->hasReachedScheduledEnd()) {
            return 'completed';
        }

        if ($lecture->status === 'cancelled') {
            return 'cancelled';
        }

        return 'neutral';
    }

    private function relativeStartLabel(LectureSession $lecture, Carbon $startAt, Carbon $endAt): string
    {
        $now = now();

        if ($lecture->status === 'active' && $now->between($startAt, $endAt)) {
            return __('lecture-session.ends_at', ['time' => $this->formatTime($endAt)]);
        }

        if ($now->lt($startAt)) {
            return __('lecture-session.starts_at', ['time' => $this->formatTime($startAt)]);
        }

        return __('lecture-session.ended_at', ['time' => $this->formatTime($endAt)]);
    }

    private function sessionDateTime(LectureSession $lecture, string $timeAttribute): Carbon
    {
        return Carbon::parse($lecture->session_date->toDateString() . ' ' . $lecture->{$timeAttribute});
    }

    private function formatTime(Carbon $time): string
    {
        return $time->format('H:i');
    }
}
