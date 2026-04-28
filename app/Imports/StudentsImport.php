<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Student;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Validation\Rule;

class StudentsImport implements ToModel, WithHeadingRow, WithValidation
{
    private int $importedCount = 0;

    private array $faculties = [];
    private array $departmentsByName = [];
    private array $departmentsByFacultyAndName = [];

    public function __construct()
    {
        $this->faculties = Faculty::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Faculty $faculty): array => [
                $this->normalizeLookupValue($faculty->name) => $faculty->id,
            ])
            ->toArray();

        Department::query()
            ->get(['id', 'name', 'faculty_id'])
            ->each(function (Department $department): void {
                $normalizedDepartmentName = $this->normalizeLookupValue($department->name);

                $this->departmentsByName[$normalizedDepartmentName] ??= $department->id;
                $this->departmentsByFacultyAndName[$department->faculty_id . '|' . $normalizedDepartmentName] = $department->id;
            });
    }

    public function prepareForValidation($data, $index)
    {
        $facultyName = $this->normalizeLookupValue($data['faculty_name'] ?? null);
        $departmentName = $this->normalizeLookupValue($data['department_name'] ?? null);

        $data['faculty_name'] = $facultyName;
        $data['department_name'] = $departmentName;
        $data['faculty_id'] = $facultyName !== null ? ($this->faculties[$facultyName] ?? null) : null;

        $data['department_id'] = null;

        if ($departmentName !== null) {
            if ($data['faculty_id'] !== null) {
                $data['department_id'] = $this->departmentsByFacultyAndName[$data['faculty_id'] . '|' . $departmentName] ?? null;
            }

            $data['department_id'] ??= $this->departmentsByName[$departmentName] ?? null;
        }

        return $data;
    }

    public function uniqueBy()
    {
        return 'student_number';
    }

    public function model(array $row)
    {
        $this->importedCount++;

        return new Student([
            'national_number' => $row['national_number'] ?? null,
            'student_number' => $row['student_number'] ?? null,
            'name' => $row['name'] ?? null,
            'faculty_id' => $row['faculty_id'],
            'department_id' => $row['department_id'],
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
            'student_number' => 'required|unique:students,student_number',
            'national_number' => 'nullable|unique:students,national_number',
            'year' => 'nullable|integer|min:1|max:6',
            'faculty_id' => 'required|exists:faculties,id',
            'department_id' => 'required|exists:departments,id',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => __('validation.name_required'),
            'name.max' => __('validation.name_max'),
            'student_number.required' => __('validation.student_number_required'),
            'student_number.unique' => __('validation.student_number_unique'),
            'national_number.unique' => __('validation.national_number_unique'),
            'year.min' => 'السنة الدراسية يجب أن تكون رقمًا صحيحًا يبدأ من 1.',
            'year.max' => 'السنة الدراسية أكبر من المسموح.',
            'faculty_id.required' => __('validation.faculty_required'),
            'faculty_id.exists' => __('validation.faculty_not_found_in_row', ['row' => ':row']),
            'department_id.required' => __('validation.department_required'),
            'department_id.exists' => __('validation.department_not_found_in_row', ['row' => ':row']),
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'name' => __('validation.name'),
            'student_number' => __('validation.student_number'),
            'national_number' => __('validation.national_number'),
            'year' => __('student.year'),
            'faculty_id' => __('validation.faculty'),
            'department_id' => __('validation.department'),
        ];
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    private function normalizeLookupValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = str_replace("\xc2\xa0", ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value ?? '');
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return Str::lower($value);
    }
}
