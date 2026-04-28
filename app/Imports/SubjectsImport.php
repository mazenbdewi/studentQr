<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\ValidationException;

class SubjectsImport implements ToModel, WithHeadingRow, WithValidation
{
    private int $importedCount = 0;

    private $lecturers;
    private $departments;

    public function __construct()
    {

        $this->lecturers = User::whereHas('roles', fn($q) => $q->where('name', 'course_lecturer'))
            ->pluck('id', 'name')
            ->toArray();


        $this->departments = Department::pluck('id', 'name')->toArray();
    }
    public function prepareForValidation($data, $index)
    {
        $rowNumber = $index + 2;

        if (!empty($data['department_name'])) {
            $departmentName = trim((string) $data['department_name']);

            if (!isset($this->departments[$departmentName])) {
                $data['department_id'] = null;
            } else {
                $data['department_id'] = $this->departments[$departmentName];
            }
        }

        if (!empty($data['lecturer_name'])) {
            $lecturerName = trim((string) $data['lecturer_name']);

            if (!isset($this->lecturers[$lecturerName])) {
                $data['lecturer_id'] = null;
            } else {
                $data['lecturer_id'] = $this->lecturers[$lecturerName];
            }
        }

        if (isset($data['semester']) && filled($data['semester'])) {
            $data['semester'] = Subject::normalizeSemester($data['semester']);
        }

        return $data;
    }
    public function model(array $row)
    {
        $this->importedCount++;

        return new Subject([
            'code' => $row['code'] ?? null,
            'name' => $row['name'] ?? null,
            'lecturer_id' => $row['lecturer_id'] ?? null,
            'department_id' => $row['department_id'] ?? null,
            'level' => $row['year'] ?? null,
            'semester' => Subject::normalizeSemester($row['semester'] ?? null),
            'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => 'required|unique:subjects,code',
            'name' => 'required|string|max:255',
            'semester' => ['required', Rule::in(array_keys(Subject::semesterOptions()))],
            'year' => ['nullable', 'integer', 'min:1', 'max:6'],
        ];
    }
    public function customValidationMessages(): array
    {
        return [
            'code.required' => __('validation.code_required'),
            'code.unique'   => __('validation.code_unique'),
            'name.required' => __('validation.name_required'),
            'name.max'      => __('validation.name_max'),
            'semester.required' => __('subjects.semester_required'),
            'semester.in' => __('subjects.semester_invalid'),
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'code' => __('subjects.code'),
            'name' => __('subjects.name'),
            'lecturer_id' => __('subjects.lecturer'),
            'department_id' => __('subjects.department_id'),
            'semester' => __('subjects.semester'),
            'year' => __('subjects.academic_year'),
        ];
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }
}
