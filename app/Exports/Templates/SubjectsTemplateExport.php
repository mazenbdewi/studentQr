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
            'college',
            'department',
            'subject_code',
            'subject_name',
            'subject_type',
            'sections',
            'lecturer_name',
            'year',
            'is_active',
        ];
    }

    public function collection(): Collection
    {
        return collect([
            [
                'كلية الهندسة',
                'علوم الحاسب',
                'CS101',
                'برمجة أساسية',
                'theoretical',
                'T1,T2,T3',
                'د. أحمد محمد',
                1,
                'true',
            ],
            [
                __('subjects.template_notes'),
                __('subjects.template_department_note'),
                __('subjects.template_code_note'),
                __('subjects.template_name_note'),
                __('subjects.template_subject_type_note'),
                __('subjects.template_sections_note'),
                __('subjects.template_lecturer_note'),
                __('subjects.template_year_note'),
                __('subjects.template_status_note'),
            ],
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = 'I';

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
