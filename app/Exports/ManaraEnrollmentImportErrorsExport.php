<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ManaraEnrollmentImportErrorsExport implements FromCollection, WithColumnWidths, WithHeadings
{
    public function __construct(private readonly array $errors) {}

    public function collection(): Collection
    {
        return collect($this->errors)->map(fn (array $error): array => [
            $error['row_number'] ?? null,
            $error['student_university_number'] ?? null,
            $error['subject_code'] ?? null,
            $error['error_message'] ?? null,
        ]);
    }

    public function headings(): array
    {
        return [
            'Row number',
            'Student university number',
            'Subject code',
            'Error message',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 26,
            'C' => 20,
            'D' => 90,
        ];
    }
}
