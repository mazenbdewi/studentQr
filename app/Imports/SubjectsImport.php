<?php

namespace App\Imports;

use App\Models\Subject;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SubjectsImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        return new Subject([

            'code' => $row['code'] ?? null,
            'name' => $row['name'] ?? null,
            'lecturer_id' => $row['lecturer_id'] ?? null,
            'department_id' => $row['department_id'] ?? null,
            'credit_hours' => $row['credit_hours'] ?? null,
            'level' => $row['level'] ?? null,
            'semester' => $row['semester'] ?? null,
            'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),

        ]);
    }

    public function rules(): array
    {
        return [
            'code' => 'required|unique:subjects,code',
            'name' => 'required|string|max:255',
        ];
    }
}
