<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\Subject;
use App\Models\User;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\ValidationException;

class SubjectsImport implements ToModel, WithHeadingRow, WithValidation
{

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
            if (!isset($this->departments[$data['department_name']])) {
                $data['department_id'] = null;
            } else {
                $data['department_id'] = $this->departments[$data['department_name']];
            }
        }

        if (!empty($data['lecturer_name'])) {
            if (!isset($this->lecturers[$data['lecturer_name']])) {
                $data['lecturer_id'] = null;
            } else {
                $data['lecturer_id'] = $this->lecturers[$data['lecturer_name']];
            }
        }

        return $data;
    }
    public function model(array $row)
    {
        return new Subject([
            'code' => $row['code'] ?? null,
            'name' => $row['name'] ?? null,
            'lecturer_id' => $row['lecturer_id'],
            'department_id' => $row['department_id'],
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
    public function customValidationMessages(): array
    {
        return [
            'code.required' => __('validation.code_required'),
            'code.unique'   => __('validation.code_unique'),
            'name.required' => __('validation.name_required'),
            'name.max'      => __('validation.name_max'),
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'code' => 'الكود',
            'name' => 'الاسم',
            'lecturer_id' => 'المحاضر',
            'department_id' => 'القسم',
        ];
    }
}
