<?php

namespace App\Imports;

use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\LectureSession;
use App\Models\Subject;
use App\Models\Hall;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LectureSessionsImport implements ToModel, WithHeadingRow, WithValidation
{
    private $subjects;
    private $halls;
    private $lecturers;

    public function __construct()
    {

        $this->subjects = Subject::pluck('id', 'name')->toArray();
        $this->halls = Hall::pluck('id', 'name')->toArray();

        $this->lecturers = User::whereHas('roles', fn($q) => $q->where('name', 'course_lecturer'))
            ->pluck('id', 'name')
            ->toArray();
    }

    private function convertExcelTime($excelTime)
    {
        if (!is_numeric($excelTime)) {
            return $excelTime;
        }


        $totalSeconds = intval(round($excelTime * 86400));
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;
        return sprintf('%02d:%02d', $hours, $minutes);
        // return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    public function prepareForValidation($data, $index)
    {
        $rowNumber = $index + 2;

        if (isset($data['start_time'])) {
            $data['start_time'] = $this->convertExcelTime($data['start_time']);
        }
        if (isset($data['end_time'])) {
            $data['end_time'] = $this->convertExcelTime($data['end_time']);
        }

        if (!empty($data['subject_name'])) {

            if (!isset($this->subjects[$data['subject_name']])) {
                throw ValidationException::withMessages([
                    "subject_name" => __('subjects.subject') . " '{$data['subject_name']}' "
                        . __('subjects.not_found_in_row', ['row' => $rowNumber])
                ]);
            }

            $data['subject_id'] = $this->subjects[$data['subject_name']];
        }


        if (!empty($data['hall_name'])) {

            if (!isset($this->halls[$data['hall_name']])) {
                throw ValidationException::withMessages([
                    "hall_name" => __('hall.hall') . "'{$data['hall_name']}'  " . __('subjects.not_found_in_row', ['row' => $rowNumber])
                ]);
            }

            $data['hall_id'] = $this->halls[$data['hall_name']];
        }


        unset(
            $data['subject_name'],
            $data['hall_name'],

        );

        return $data;
    }

    public function model(array $row)
    {
        $subject = Subject::find($row['subject_id']);
        $lecturerId = $subject ? $subject->lecturer_id : null;


        $session = LectureSession::firstOrCreate(
            [
                'subject_id' => $row['subject_id'],
                'hall_id' => $row['hall_id'],
                'session_date' => Carbon::createFromFormat('d-m-Y', $row['session_date'])->format('Y-m-d')
            ],
            [
                'lecturer_id' => $lecturerId,
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'status' => $row['status'] ?? 'scheduled',
                'attendance_mode' => $row['attendance_mode'] ?? 'qr_otp',
                'qr_refresh_rate' => $row['qr_refresh_rate'] ?? 120,
                'notes' => $row['notes'] ?? null,
            ]
        );


        $enrollments = Enrollment::where('subject_id', $row['subject_id'])->get();

        foreach ($enrollments as $enrollment) {
            Attendance::firstOrCreate( // updateOrCreate
                [
                    'student_id' => $enrollment->student_id,
                    'lecture_session_id' => $session->id,
                ],
                [
                    'attendance_status' => 'pending'
                ]
            );
        }
        return $session;
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'exists:subjects,id'],
            'hall_id' => ['required', 'exists:halls,id'],
            'session_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'status' => ['nullable', Rule::in(['scheduled', 'active', 'completed', 'cancelled'])],
            'attendance_mode' => ['nullable', Rule::in(['qr_only', 'qr_otp', 'manual'])],
            'qr_refresh_rate' => ['nullable', 'integer', 'min:10'],
            'notes' => ['nullable', 'string'],
        ];
    }


    public function customValidationMessages(): array
    {
        return [
            'subject_id.required' => __('validation.subject_required'),
            'subject_id.exists' => __('validation.subject_not_found'),
            'hall_id.required' => __('validation.hall_required'),
            'hall_id.exists' => __('validation.hall_not_found'),
            'session_date.required' => __('validation.session_date_required'),
            'session_date.date' => __('validation.session_date_invalid'),
            'start_time.required' => __('validation.start_time_required'),
            'start_time.date_format' => __('validation.start_time_format'),
            'end_time.required' => __('validation.end_time_required'),
            'end_time.date_format' => __('validation.end_time_format'),
            'end_time.after' => __('validation.end_time_after_start'),
            'status.in' => __('validation.status_invalid'),
            'attendance_mode.in' => __('validation.attendance_mode_invalid'),
            'qr_refresh_rate.integer' => __('validation.qr_refresh_rate_integer'),
            'qr_refresh_rate.min' => __('validation.qr_refresh_rate_min'),
            'notes.string' => __('validation.notes_string'),
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'subject_id' => __('validation.subject'),
            'hall_id' => __('validation.hall'),
            'session_date' => __('validation.session_date'),
            'start_time' => __('validation.start_time'),
            'end_time' => __('validation.end_time'),
            'status' => __('validation.status'),
            'attendance_mode' => __('validation.attendance_mode'),
            'qr_refresh_rate' => __('validation.qr_refresh_rate'),
            'notes' => __('validation.notes'),
        ];
    }
}
