<?php

namespace App\Imports;

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
        $studentNumber = isset($row['student_number']) && $row['student_number'] !== ''
            ? (string) $row['student_number']
            : null;

        $name = isset($row['name']) && $row['name'] !== ''
            ? (string) $row['name']
            : null;

        $nationalNumber = isset($row['national_number']) && $row['national_number'] !== ''
            ? (string) $row['national_number']
            : null;

        $semester = isset($row['semester']) && $row['semester'] !== ''
            ? (int) $row['semester']
            : ($this->semester ?? 1);

        $year = isset($row['year']) && $row['year'] !== ''
            ? (int) $row['year']
            : ($this->year ?? 1);

        $student = Student::firstOrCreate(
            [
                'student_number' => $studentNumber,
            ],
            [
                'name' => $name,
                'national_number' => $nationalNumber,
            ]
        );

        $student->subjects()->syncWithoutDetaching([
            $this->subjectId => [
                'semester' => $semester,
                'year' => $year,
                'status' => 'enrolled',
            ],
        ]);

        return $student;
    }

    public function rules(): array
    {
        return [
            'student_number' => ['required', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'national_number' => ['nullable', 'max:20'],
            'semester' => ['nullable', 'integer', Rule::in([1, 2])],
            'year' => ['nullable', 'integer', 'min:1', 'max:6'],
        ];
    }

    public function prepareForValidation($data, $index)
    {
        if (isset($data['student_number']) && $data['student_number'] !== null && $data['student_number'] !== '') {
            $data['student_number'] = (string) $data['student_number'];
        }

        if (isset($data['national_number']) && $data['national_number'] !== null && $data['national_number'] !== '') {
            $data['national_number'] = (string) $data['national_number'];
        }

        if (isset($data['semester']) && $data['semester'] !== null && $data['semester'] !== '') {
            $data['semester'] = (int) $data['semester'];
        }

        if (isset($data['year']) && $data['year'] !== null && $data['year'] !== '') {
            $data['year'] = (int) $data['year'];
        }

        return $data;
    }

    public function customValidationMessages(): array
    {
        return [
            'student_number.required' => __('validation.student_number_required'),
            'name.required' => __('validation.name_required'),
            'name.max' => __('validation.name_max'),
            'semester.in' => 'الفصل الدراسي يجب أن يكون 1 أو 2.',
            'year.min' => 'السنة الدراسية يجب أن تكون رقمًا صحيحًا يبدأ من 1.',
            'year.max' => 'السنة الدراسية أكبر من المسموح.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'student_number' => 'رقم الطالب',
            'name' => 'الاسم',
            'national_number' => 'الرقم الوطني',
            'semester' => 'الفصل الدراسي',
            'year' => 'السنة الدراسية',
        ];
    }
}