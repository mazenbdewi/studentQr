<?php

namespace App\Exports\Templates;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class StudentsTemplateExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize
{
    public function collection(): Collection
    {
        return collect([
            [
                'S001',
                'أحمد محمد',
                '123456789',
                'كلية الهندسة',
                'علوم الحاسب',
                '1',
                'student',
                '0123456789',
                'active',
                '',
                'true',
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
            'type',
            'phone',
            'status',
            'avatar',
            'is_active',
        ];
    }

    public function title(): string
    {
        return 'Students_Template';
    }
}