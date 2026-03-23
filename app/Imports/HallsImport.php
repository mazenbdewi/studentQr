<?php

namespace App\Imports;

use App\Models\Hall;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class HallsImport implements ToModel, WithHeadingRow, WithValidation
{
    public function prepareForValidation($data, $index)
    {
        if (isset($data['code']) && $data['code'] !== null) {
            $data['code'] = trim((string) $data['code']);
        }

        if (isset($data['name']) && $data['name'] !== null) {
            $data['name'] = trim((string) $data['name']);
        }

        if (isset($data['floor']) && $data['floor'] !== null && $data['floor'] !== '') {
            $data['floor'] = (int) $data['floor'];
        }

        if (array_key_exists('is_active', $data) && $data['is_active'] !== null && $data['is_active'] !== '') {
            $normalized = strtolower(trim((string) $data['is_active']));

            $map = [
                'true'  => true,
                'false' => false,
                '1'     => true,
                '0'     => false,
                'yes'   => true,
                'no'    => false,
            ];

            $data['is_active'] = $map[$normalized] ?? null;
        } else {
            $data['is_active'] = true;
        }

        return $data;
    }

    public function model(array $row)
    {
        return new Hall([
            'code'      => $row['code'],
            'name'      => $row['name'],
            'floor'     => $row['floor'] ?? 0,
            'is_active' => $row['is_active'] ?? true,
        ]);
    }

    public function rules(): array
    {
        return [
            'code'      => ['required', 'string', 'max:255', Rule::unique('halls', 'code')],
            'name'      => ['required', 'string', 'max:255'],
            'floor'     => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'in:true,false,1,0,yes,no'],
        ];
    }

    public function customValidationMessages()
    {
        return [
            'code.required'    => __('validation.code_required'),
            'code.unique'      => __('validation.code_unique'),
            'code.max'         => __('validation.code_max'),

            'name.required'    => __('validation.name_required'),
            'name.max'         => __('validation.name_max'),

            'floor.required'   => 'حقل الدور مطلوب.',
            'floor.integer'    => __('validation.floor_integer'),
            'floor.min'        => __('import.floor_min'),

            'is_active.in'     => 'حقل الحالة يجب أن يكون إحدى القيم: true / false / 1 / 0 / yes / no.',
        ];
    }

    public function customValidationAttributes()
    {
        return [
            'code'      => 'الكود',
            'name'      => 'الاسم',
            'floor'     => 'الدور',
            'is_active' => 'الحالة',
        ];
    }
}