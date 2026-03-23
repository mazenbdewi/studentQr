<?php

namespace App\Exports\Templates;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SubjectsTemplateExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    public function headings(): array
    {
        return [
            'code',
            'name',
            'lecturer_name',
            'department_name',
            'credit_hours',
            'level',
            'semester',
            'is_active',
        ];
    }

    public function collection(): Collection
    {
        return collect([
            [
                'CS101',
                'برمجة أساسية',
                'د. أحمد محمد',
                'علوم الحاسب',
                3,
                1,
                1,
                'true',
            ],
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = 'H';

        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);

        $sheet->getStyle("A1:{$lastColumn}1")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('D9EAF7');

        $sheet->getStyle("A2:{$lastColumn}2")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('F8F9FA');

        return [];
    }
}