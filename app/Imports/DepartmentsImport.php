<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\Faculty;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class DepartmentsImport implements ToModel, WithHeadingRow, WithValidation
{
    private $faculties;

    public function __construct()
    {

        $this->faculties = Faculty::pluck('id', 'name')->toArray();
    }

    public function prepareForValidation($data, $index)
    {
        $data['faculty_id'] = $this->faculties[$data['faculty_name']] ?? null;

        return $data;
    }

    public function model(array $row)
    {
        $row['total_students'] = $row['total_students'] ?? 0;
        $row['total_lecturers'] = $row['total_lecturers'] ?? 0;
        $row['is_active'] = isset($row['is_active'])
            ? filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN)
            : true;

        return new Department([
            'code'            => $row['code'],
            'name'            => $row['name'],
            'name_en'         => $row['name_en'] ?? null,
            'faculty_id'      => $row['faculty_id'],
            'total_students'  => $row['total_students'],
            'total_lecturers' => $row['total_lecturers'],
            'is_active'       => $row['is_active'],
        ]);
    }

    public function rules(): array
    {
        return [
            'code'       => ['required', 'string', 'max:255', Rule::unique('departments', 'code')],
            'name'       => ['required', 'string', 'max:255'],
            'name_en'    => ['nullable', 'string', 'max:255'],
            'faculty_id' => ['required', 'exists:faculties,id'],
            'total_students'  => ['nullable', 'integer', 'min:0'],
            'total_lecturers' => ['nullable', 'integer', 'min:0'],
            'is_active'       => ['nullable', 'boolean'],
        ];
    }

    public function customValidationMessages()
    {
        return [
            'code.required'       => __('validation.code_required'),
            'code.unique'         => __('validation.code_unique'),
            'code.max'            => __('validation.code_max'),

            'name.required'       => __('validation.name_required'),
            'name.max'            => __('validation.name_max'),

            'faculty_id.required' => __('validation.faculty_id_required'),
            'faculty_id.exists'   => __('validation.faculty_id_exists'),

            'total_students.integer' => __('validation.total_students_integer'),
            'total_students.min'     => __('validation.total_students_min'),

            'total_lecturers.integer' => __('validation.total_lecturers_integer'),
            'total_lecturers.min'     => __('validation.total_lecturers_min'),

            'is_active.boolean' => __('validation.is_active_boolean'),
        ];
    }

    public function customValidationAttributes()
    {
        return [
            'code'           => 'الكود',
            'name'           => 'الاسم',
            'faculty_id'     => 'الكلية',
            'total_students' => 'عدد الطلاب',
            'total_lecturers' => 'عدد المدرسين',
            'is_active'      => 'الحالة',
        ];
    }
}
