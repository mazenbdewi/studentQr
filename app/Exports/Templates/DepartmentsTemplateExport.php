<?php

namespace App\Exports\Templates;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class DepartmentsTemplateExport implements FromCollection, WithHeadings, WithTitle, WithColumnWidths
{
    public function collection(): Collection
    {
        return collect([
            ['علوم الحاسب', 'كلية الهندسة', 'true'],
        ]);
    }

    public function headings(): array
    {
        return ['name', 'faculty_name', 'is_active'];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 28,
            'C' => 12,
        ];
    }

    public function title(): string
    {
        return 'Departments_Template';
    }
}
