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
            ['CS', 'علوم الحاسب', 'Computer Science', 'Faculty of Engineering', 'true'],
        ]);
    }

    public function headings(): array
    {
        return ['code', 'name', 'name_en', 'faculty_name', 'is_active'];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 25,
            'C' => 22,
            'D' => 28,
            'E' => 12,
        ];
    }

    public function title(): string
    {
        return 'Departments_Template';
    }
}