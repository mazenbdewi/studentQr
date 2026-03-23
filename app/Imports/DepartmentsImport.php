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
 public function prepareForValidation($data, $index)
{
    if (isset($data['code']) && $data['code'] !== null) {
        $data['code'] = trim((string) $data['code']);
    }

    if (isset($data['name']) && $data['name'] !== null) {
        $data['name'] = trim((string) $data['name']);
    }

    if (isset($data['name_en']) && $data['name_en'] !== null) {
        $data['name_en'] = trim((string) $data['name_en']);
    }

    if (isset($data['faculty_name']) && $data['faculty_name'] !== null) {
        $data['faculty_name'] = trim((string) $data['faculty_name']);
    }

    $faculty = null;

    if (!empty($data['faculty_name'])) {
        $faculty = Faculty::query()
            ->where('name', $data['faculty_name'])
            ->first();
    }

    $data['faculty_id'] = $faculty?->id;

    if (array_key_exists('is_active', $data) && $data['is_active'] !== null && $data['is_active'] !== '') {
        $normalized = strtolower(trim((string) $data['is_active']));

        $booleanMap = [
            'true' => true,
            'false' => false,
            '1' => true,
            '0' => false,
            'yes' => true,
            'no' => false,
        ];

        $data['is_active'] = $booleanMap[$normalized] ?? $data['is_active'];
    } else {
        $data['is_active'] = true;
    }

    return $data;
}
  public function model(array $row)
{
    return new Department([
        'code'        => $row['code'],
        'name'        => $row['name'],
        'name_en'     => $row['name_en'] ?? null,
        'faculty_id'  => $row['faculty_id'],
        'is_active'   => $row['is_active'] ?? true,
    ]);
}

    public function rules(): array
    {
        return [
            'code'       => ['required', 'string', 'max:255', Rule::unique('departments', 'code')],
            'name'       => ['required', 'string', 'max:255'],
            'name_en'    => ['nullable', 'string', 'max:255'],
            'faculty_id' => ['required', 'exists:faculties,id'],
            'is_active'  => ['nullable', 'boolean'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'code.required'       => __('validation.code_required'),
            'code.unique'         => __('validation.code_unique'),
            'code.max'            => __('validation.code_max'),
            'name.required'       => __('validation.name_required'),
            'name.max'            => __('validation.name_max'),
            'faculty_id.required' => 'حقل الكلية مطلوب في الصف :row. تأكد أن faculty_name يطابق اسم كلية موجودة في النظام.',
            'faculty_id.exists'   => 'الكلية المحددة في الصف :row غير موجودة في النظام.',
            'is_active.boolean'   => __('validation.is_active_boolean'),
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'code'       => 'الكود',
            'name'       => 'الاسم',
            'faculty_id' => 'الكلية',
            'is_active'  => 'الحالة',
        ];
    }
}