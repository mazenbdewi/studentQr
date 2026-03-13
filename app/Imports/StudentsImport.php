<?php

namespace App\Imports;

use App\Models\Student;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Validation\Rule;

class StudentsImport implements ToModel, WithHeadingRow, WithValidation
{
    public function uniqueBy()
    {
        return 'student_number';
    }

    public function model(array $row)
    {
        return new Student([

            'national_number' => $row['national_number'] ?? null,

            'student_number' => $row['student_number'] ?? null,

            'name' => $row['name'] ?? null,
            'faculty_id' => $row['faculty_id'] ?? null,
            'department_id' => $row['department_id'] ?? null,
            'year' => $row['year'] ?? null,
            'type' => $row['type'] ?? 'student',
            'phone' => $row['phone'] ?? null,
            'status' => $row['status'] ?? 'pending',
            'avatar' => $row['avatar'] ?? null,
            'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'student_number' => 'nullable|unique:students,student_number',
            'national_number' => 'nullable|unique:students,national_number',
        ];
    }
}
