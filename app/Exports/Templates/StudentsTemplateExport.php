<?php

namespace App\Exports\Templates;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class StudentsTemplateExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    public function collection(): Collection
    {
        return collect([
            ['S001', 'أحمد محمد', '123456789', 'Faculty of Engineering', 'Computer Science', '1', 'student', '0123456789', 'active', '', 'true'],
        ]);
    }

    public function headings(): array
    {
        return [
            'student_number', 'name', 'national_number', 'faculty_name', 'department_name', 
            'year', 'type', 'phone', 'status', 'avatar', 'is_active'
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheetStyle = [];

        // Header row styling: bold, gray background, center align
        $sheetStyle['1']->font = ['bold' => true, 'color' => ['rgb' => 'FFFFFF']];
        $sheetStyle['1']->fill = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']];
        $sheetStyle['1']->alignment = ['horizontal' => Alignment::HORIZONTAL_CENTER];

        // Example row highlight: light yellow
        $sheetStyle['2']->fill = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']];

        return $sheetStyle;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, 'B' => 20, 'C' => 15, 'D' => 20, 'E' => 20,
            'F' => 8, 'G' => 12, 'H' => 15, 'I' => 12, 'J' => 12, 'K' => 12,
        ];
    }

    public function title(): string
    {
        return 'Students_Template';
    }
}

