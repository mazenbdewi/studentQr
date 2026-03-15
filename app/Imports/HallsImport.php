<?php

namespace App\Imports;

use App\Models\Hall;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class HallsImport implements ToModel, WithHeadingRow, WithValidation
{

    public function model(array $row)
    {
        $booleanFields = ['has_projector', 'has_computer', 'is_active'];
        foreach ($booleanFields as $field) {
            if (isset($row[$field])) {
                $row[$field] = filter_var($row[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            } else {
                $row[$field] = false;
            }
        }

        $row['floor'] = $row['floor'] ?? 0;
        $row['capacity'] = $row['capacity'] ?? 1;

        return new Hall([
            'code'             => $row['code'],
            'name'             => $row['name'],
            'floor'            => $row['floor'],
            'capacity'         => $row['capacity'],
            'has_projector'    => $row['has_projector'],
            'has_computer'     => $row['has_computer'],
            'network_ssid'     => $row['network_ssid'] ?? null,
            'ip_range_start'   => $row['ip_range_start'] ?? null,
            'ip_range_end'     => $row['ip_range_end'] ?? null,
            'is_active'        => $row['is_active'],
        ]);
    }


    public function rules(): array
    {
        return [
            'code'           => ['required', 'string', 'max:255', Rule::unique('halls', 'code')],
            'name'           => ['required', 'string', 'max:255'],
            'floor'          => ['nullable', 'integer', 'min:0'],
            'capacity'       => ['nullable', 'integer', 'min:1'],
            'has_projector'  => ['nullable', 'boolean'],
            'has_computer'   => ['nullable', 'boolean'],
            'network_ssid'   => ['nullable', 'string', 'max:255'],
            'ip_range_start' => ['nullable', 'ip'],
            'ip_range_end'   => ['nullable', 'ip'],
            'is_active'      => ['nullable', 'boolean'],
        ];
    }
}
