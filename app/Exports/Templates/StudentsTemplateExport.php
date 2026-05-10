<?php

namespace App\Exports\Templates;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class StudentsTemplateExport implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    public function collection(): Collection
    {
        return collect([
            [
                '12345',
                'أحمد محمد',
                '123456789',
                'كلية الهندسة',
                'علوم الحاسب',
                '1',
                '0123456789',
                'active',
                'true',
                'CS101, CS102',
            ],
        ]);
    }

    public function headings(): array
    {
        return [
            'student_number',
            'name',
            'national_number',
            'faculty_name',
            'department_name',
            'year',
            'phone',
            'status',
            'is_active',
            'subject_codes',
        ];
    }

    public function title(): string
    {
        return 'Students_Template';
    }
}
