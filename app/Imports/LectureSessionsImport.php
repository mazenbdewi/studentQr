<?php

namespace App\Imports;

use App\Models\Hall;
use App\Models\LectureSession;
use App\Models\Subject;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class LectureSessionsImport implements ToModel, WithHeadingRow, WithValidation
{
    private array $subjects = [];
    private array $halls = [];

    public function __construct()
    {
        $this->subjects = Subject::query()
            ->get(['id', 'name', 'code', 'lecturer_id'])
            ->flatMap(function ($subject) {
                $map = [];

                if (filled($subject->name)) {
                    $map[trim((string) $subject->name)] = [
                        'id' => $subject->id,
                        'lecturer_id' => $subject->lecturer_id,
                    ];
                }

                if (filled($subject->code)) {
                    $map[trim((string) $subject->code)] = [
                        'id' => $subject->id,
                        'lecturer_id' => $subject->lecturer_id,
                    ];
                }

                return $map;
            })
            ->toArray();

        $this->halls = Hall::query()
            ->get(['id', 'name', 'code'])
            ->flatMap(function ($hall) {
                $map = [];

                if (filled($hall->name)) {
                    $map[trim((string) $hall->name)] = $hall->id;
                }

                if (filled($hall->code)) {
                    $map[trim((string) $hall->code)] = $hall->id;
                }

                return $map;
            })
            ->toArray();
    }

    private function convertExcelTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $totalSeconds = (int) round(((float) $value) * 86400);
            $hours = floor($totalSeconds / 3600) % 24;
            $minutes = floor(($totalSeconds % 3600) / 60);

            return sprintf('%02d:%02d', $hours, $minutes);
        }

        $value = trim((string) $value);

        if (preg_match('/^(?<hour>\d{1,2}):(?<minute>\d{2})$/', $value, $matches)) {
            return sprintf('%02d:%02d', (int) $matches['hour'], (int) $matches['minute']);
        }

        if (preg_match('/^(?<hour>\d{1,2}):(?<minute>\d{2}):\d{2}$/', $value, $matches)) {
            return sprintf('%02d:%02d', (int) $matches['hour'], (int) $matches['minute']);
        }

        return $value;
    }

    private function convertExcelDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $value = trim((string) $value);

        foreach (['Y-m-d'] as $format) {
            try {
                return \Carbon\Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable $e) {
            }
        }

        return $value;
    }

    public function prepareForValidation($data, $index)
    {
        $rowNumber = $index + 2;

        if (isset($data['subject_name']) && $data['subject_name'] !== null) {
            $data['subject_name'] = trim((string) $data['subject_name']);
        }

        if (isset($data['hall_name']) && $data['hall_name'] !== null) {
            $data['hall_name'] = trim((string) $data['hall_name']);
        }

        if (isset($data['status']) && $data['status'] !== null) {
            $data['status'] = trim((string) $data['status']);
        }

        if (isset($data['attendance_mode']) && $data['attendance_mode'] !== null) {
            $data['attendance_mode'] = trim((string) $data['attendance_mode']);
        }

        if (isset($data['notes']) && $data['notes'] !== null) {
            $data['notes'] = trim((string) $data['notes']);
        }

        if (isset($data['start_time'])) {
            $data['start_time'] = $this->convertExcelTime($data['start_time']);
        }

        if (isset($data['end_time'])) {
            $data['end_time'] = $this->convertExcelTime($data['end_time']);
        }

        if (isset($data['session_date'])) {
            $data['session_date'] = $this->convertExcelDate($data['session_date']);
        }

        if (! empty($data['subject_name'])) {
            if (! isset($this->subjects[$data['subject_name']])) {
                throw ValidationException::withMessages([
                    'subject_name' => "المادة '{$data['subject_name']}' غير موجودة في الصف {$rowNumber}.",
                ]);
            }

            $subject = $this->subjects[$data['subject_name']];

            if (empty($subject['lecturer_id'])) {
                throw ValidationException::withMessages([
                    'subject_name' => "المادة '{$data['subject_name']}' في الصف {$rowNumber} لا يوجد لها محاضر معيّن. يرجى تعيين محاضر للمادة أولًا.",
                ]);
            }

            $data['subject_id'] = $subject['id'];
            $data['lecturer_id'] = $subject['lecturer_id'];
        }

        if (! empty($data['hall_name'])) {
            if (! isset($this->halls[$data['hall_name']])) {
                throw ValidationException::withMessages([
                    'hall_name' => "القاعة '{$data['hall_name']}' غير موجودة في الصف {$rowNumber}. اكتب اسم القاعة أو كودها الصحيح.",
                ]);
            }

            $data['hall_id'] = $this->halls[$data['hall_name']];
        }

        if (isset($data['qr_refresh_rate']) && $data['qr_refresh_rate'] !== null && $data['qr_refresh_rate'] !== '') {
            $data['qr_refresh_rate'] = (int) $data['qr_refresh_rate'];
        }

        unset($data['subject_name'], $data['hall_name']);

        return $data;
    }

    public function model(array $row)
    {
        $lectureSession = LectureSession::withTrashed()->updateOrCreate(
            [
                'subject_id' => $row['subject_id'],
                'hall_id' => $row['hall_id'],
                'session_date' => $row['session_date'],
            ],
            [
                'lecturer_id' => $row['lecturer_id'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'status' => $row['status'] ?? 'scheduled',
                'attendance_mode' => $row['attendance_mode'] ?? 'qr_otp',
                'qr_refresh_rate' => $row['qr_refresh_rate'] ?? 120,
                'notes' => $row['notes'] ?? null,
            ]
        );

        if ($lectureSession->trashed()) {
            $lectureSession->restore();
        }

        return $lectureSession;
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'exists:subjects,id'],
            'lecturer_id' => ['required', 'exists:users,id'],
            'hall_id' => ['required', 'exists:halls,id'],
            'session_date' => ['required', 'date_format:Y-m-d'],
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
            'lecturer_id.required' => 'المحاضر مطلوب.',
            'lecturer_id.exists' => 'المحاضر المحدد غير موجود.',
            'hall_id.required' => __('validation.hall_required'),
            'hall_id.exists' => __('validation.hall_not_found'),
            'session_date.required' => __('validation.session_date_required'),
            'session_date.date_format' => __('validation.session_date_format'),
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
            'lecturer_id' => 'المحاضر',
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
