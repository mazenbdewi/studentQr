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
            'faculty_name',
            'department_name',
            'year',
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
                'كلية الهندسة',
                'علوم الحاسب',
                1,
                'first',
                'true',
            ],
            [
                __('subjects.template_notes'),
                __('subjects.template_code_note'),
                __('subjects.template_lecturer_note'),
                __('subjects.template_faculty_note'),
                __('subjects.template_department_note'),
                __('subjects.template_year_note'),
                __('subjects.template_semester_note'),
                __('subjects.template_status_note'),
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
