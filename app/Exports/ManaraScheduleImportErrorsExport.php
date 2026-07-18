<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ManaraScheduleImportErrorsExport implements FromCollection, WithColumnWidths, WithHeadings
{
    public function __construct(private readonly array $errors) {}

    public function collection(): Collection
    {
        return collect($this->errors)->map(fn (array $error): array => [
            $error['row_number'] ?? null,
            $error['subject_code'] ?? null,
            $error['section_type'] ?? null,
            $error['section_number'] ?? null,
            $error['normalized_section_code'] ?? null,
            $error['teacher_name'] ?? null,
            $error['hall_name'] ?? null,
            $error['weekday'] ?? null,
            $error['time_range'] ?? null,
            $error['error_message'] ?? null,
            $error['issue_type'] ?? null,
            $error['severity'] ?? null,
            $error['resolution_status'] ?? null,
            $error['resolved_subject'] ?? null,
            $error['resolved_section'] ?? null,
            $error['resolution_note'] ?? null,
        ]);
    }

    public function headings(): array
    {
        return [
            'Excel row number',
            'Subject code',
            'Section type source value',
            'Section number source value',
            'Normalized section code',
            'Teacher source value',
            'Hall source value',
            'Weekday',
            'Time range source value',
            'Error or warning',
            'Issue category',
            'Severity',
            'Resolution status',
            'Selected replacement subject',
            'Selected replacement section',
            'Resolution note',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 20,
            'C' => 22,
            'D' => 24,
            'E' => 24,
            'F' => 34,
            'G' => 24,
            'H' => 18,
            'I' => 24,
            'J' => 90,
            'K' => 28,
            'L' => 16,
            'M' => 24,
            'N' => 28,
            'O' => 28,
            'P' => 45,
        ];
    }
}
