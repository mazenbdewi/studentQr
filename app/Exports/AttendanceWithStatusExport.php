<?php

namespace App\Exports;

use App\Models\Attendance;
use App\Models\LectureSession;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class AttendanceWithStatusExport implements FromView
{
    protected $session;

    public function __construct(LectureSession $session)
    {
        $this->session = $session;
    }

    public function view(): View
    {
        $this->session->loadMissing(['subject', 'subjectSection', 'hall']);

        // Get all enrolled students for this session's subject
        $enrolledStudents = $this->session->subject->students()
            ->orderBy('name')
            ->get();

        // Get all attendance records for this session
        $attendanceRecords = Attendance::where('lecture_session_id', $this->session->id)
            ->pluck('student_id')
            ->toArray();

        // Mark each student as present or absent
        $studentsWithStatus = $enrolledStudents->map(function ($student) use ($attendanceRecords) {
            return [
                'subject_code' => $this->session->subject?->code,
                'subject_name' => $this->session->subject?->name,
                'subject_type' => $this->session->subjectSection?->section_type_label
                    ?? $this->session->subject?->subject_type_label,
                'section_code' => $this->session->subjectSection?->code,
                'hall_name' => $this->session->hall?->name,
                'name' => $student->name,
                'student_number' => $student->student_number,
                'status' => in_array($student->id, $attendanceRecords) ? 'present' : 'absent',
            ];
        });

        return view('exports.attendance_with_status', [
            'students' => $studentsWithStatus,
            'session' => $this->session,
        ]);
    }
}
