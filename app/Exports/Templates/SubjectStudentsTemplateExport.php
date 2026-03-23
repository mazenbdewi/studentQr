<?php

namespace App\Exports\Templates;

use App\Models\Subject;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class SubjectStudentsTemplateExport implements FromCollection, WithHeadings, WithTitle, WithColumnWidths
{
    public function __construct(public ?Subject $subject = null) {}

    public function collection(): Collection
    {
        return collect([
            ['S001', 'أحمد محمد', '123456789', '1', '1'],
        ]);
    }

    public function headings(): array
    {
        return [
            'student_number',
            'name',
            'national_number',
            'semester',
            'year',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,
            'B' => 20,
            'C' => 15,
            'D' => 10,
            'E' => 10,
        ];
    }

    public function title(): string
    {
        return 'Subject_Students_Template';
    }
}