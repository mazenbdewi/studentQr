<?php

namespace App\Support;

use App\Models\LectureSession;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StudentAttendanceReport
{
    public function query(Student $student, ?int $subjectId = null): Builder
    {
        return LectureSession::query()
            ->withTrashed()
            ->with(['subject', 'lecturer', 'hall'])
            ->select('lecture_sessions.*')
            ->selectRaw(
                "student_attendances.id as attendance_record_id,
                student_attendances.attendance_status as attendance_status_raw,
                student_attendances.attendance_time as attendance_recorded_time,
                student_attendances.created_at as attendance_recorded_at,
                case
                    when student_attendances.id is null
                        or student_attendances.attendance_status in ('pending', 'absent')
                    then 'absent'
                    else 'present'
                end as report_status"
            )
            ->join('enrollments', function ($join) use ($student): void {
                $join->on('enrollments.subject_id', '=', 'lecture_sessions.subject_id')
                    ->where('enrollments.student_id', '=', $student->id);
            })
            ->leftJoin('attendances as student_attendances', function ($join) use ($student): void {
                $join->on('student_attendances.lecture_session_id', '=', 'lecture_sessions.id')
                    ->where('student_attendances.student_id', '=', $student->id);
            })
            ->when(
                $subjectId,
                fn (Builder $query): Builder => $query->where('lecture_sessions.subject_id', $subjectId),
            )
            ->orderByDesc('lecture_sessions.session_date')
            ->orderByDesc('lecture_sessions.start_time');
    }

    public function rows(Student $student, ?int $subjectId = null): Collection
    {
        return $this->query($student, $subjectId)->get();
    }

    public function summary(Student $student, ?int $subjectId = null): array
    {
        return $this->summaryFromRows($this->rows($student, $subjectId));
    }

    public function subjectOptions(Student $student): array
    {
        return $student->subjects()
            ->select('subjects.id', 'subjects.name')
            ->orderBy('subjects.name')
            ->pluck('subjects.name', 'subjects.id')
            ->all();
    }

    public function resolveSubject(Student $student, ?int $subjectId): ?Subject
    {
        if (! $subjectId) {
            return null;
        }

        return $student->subjects()
            ->select('subjects.*')
            ->whereKey($subjectId)
            ->first();
    }

    public function summaryFromRows(Collection $rows): array
    {
        $totalLectures = $rows->count();
        $totalPresent = $rows->where('report_status', 'present')->count();
        $totalAbsent = $rows->where('report_status', 'absent')->count();

        return [
            'total_lectures' => $totalLectures,
            'total_present' => $totalPresent,
            'total_absent' => $totalAbsent,
            'attendance_percentage' => $totalLectures > 0
                ? round(($totalPresent / $totalLectures) * 100, 1)
                : 0.0,
        ];
    }
}
