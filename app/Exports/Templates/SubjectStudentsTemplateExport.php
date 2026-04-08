<?php

namespace App\Exports\Templates;

use App\Models\Subject;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class SubjectStudentsTemplateExport implements FromCollection, WithColumnWidths, WithHeadings, WithTitle
{
    public function __construct(public ?Subject $subject = null) {}

    public function collection(): Collection
    {
        return collect([
            ['12345', 'أحمد محمد', '123456789', '1', '1'],
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
