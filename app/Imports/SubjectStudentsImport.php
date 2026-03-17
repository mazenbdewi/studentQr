<?php

namespace App\Imports;

use App\Models\Attendance;
use App\Models\LectureSession;
use App\Models\Student;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SubjectStudentsImport implements ToModel, WithHeadingRow, WithValidation
{
    private int $subjectId;
    private ?int $semester;
    private ?int $year;

    public function __construct(int $subjectId, ?int $semester = null, ?int $year = null)
    {
        $this->subjectId = $subjectId;
        $this->semester = $semester;
        $this->year = $year;
    }

    public function model(array $row)
    {
        $student = Student::firstOrCreate(
            [
                'student_number' => $row['student_number'] ?? null,
            ],
            [
                'name' => $row['name'] ?? null,
                'national_number' => $row['national_number'] ?? null,
            ]
        );


        $student->subjects()->syncWithoutDetaching([
            $this->subjectId => [
                'semester' => $row['semester'] ?? $this->semester,
                'year' => $row['year'] ?? $this->year,
                'status' => 'enrolled',
            ]
        ]);

        $sessions =
            LectureSession::where('subject_id', $this->subjectId)
            ->pluck('id')
            ->each(function ($sessionId) use ($student) {
                Attendance::firstOrCreate([
                    'student_id' => $student->id,
                    'lecture_session_id' => $sessionId,
                ], [
                    'attendance_status' => 'pending'
                ]);
            });


        return $student;
    }

    public function rules(): array
    {
        return [
            'student_number' => ['required'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'student_number.required' => __('validation.student_number_required'),
            'name.required' => __('validation.name_required'),
            'name.max' => __('validation.name_max'),
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'student_number' => 'رقم الطالب',
            'name' => 'الاسم',
        ];
    }
}
