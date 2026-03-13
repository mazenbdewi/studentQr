<?php

namespace App\Imports;

use App\Models\LectureSession;
use App\Models\Subject;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class LectureSessionsImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {

        if (empty($row['lecturer_id']) && !empty($row['subject_id'])) {
            $subject = Subject::find($row['subject_id']);
            $row['lecturer_id'] = $subject?->lecturer_id;
        }


        $row['status'] = $row['status'] ?? 'scheduled';
        $row['attendance_mode'] = $row['attendance_mode'] ?? 'qr_otp';
        $row['qr_refresh_rate'] = $row['qr_refresh_rate'] ?? 120;

        return new LectureSession([
            'subject_id'       => $row['subject_id'],
            'hall_id'          => $row['hall_id'],
            'session_date'     => $row['session_date'],
            'start_time'       => $row['start_time'],
            'end_time'         => $row['end_time'],
            'status'           => $row['status'],
            'attendance_mode'  => $row['attendance_mode'],
            'qr_refresh_rate'  => $row['qr_refresh_rate'],
            'notes'            => $row['notes'] ?? null,
            'lecturer_id'      => $row['lecturer_id'],
        ]);
    }

    public function rules(): array
    {
        return [
            'subject_id'   => ['required', 'integer', 'exists:subjects,id'],
            'hall_id'      => ['required', 'integer', 'exists:halls,id'],
            'session_date' => ['required', 'date'],
            // 'start_time'   => ['required', 'date_format:H:i:s'],
            // 'end_time'     => ['required', 'date_format:H:i:s', 'after:start_time'],
            'status'       => ['sometimes', Rule::in(['scheduled', 'active', 'completed', 'cancelled'])],
            'attendance_mode' => ['sometimes', Rule::in(['qr_only', 'qr_otp', 'manual'])],
            'qr_refresh_rate' => ['sometimes', 'integer', 'min:10'],
            'notes'        => ['nullable', 'string'],
            'lecturer_id'  => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }
}
