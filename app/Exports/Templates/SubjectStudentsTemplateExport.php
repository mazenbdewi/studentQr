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
            [
                '2024001',
                (string) ($this->subject?->semester ?? 1),
                (string) ($this->subject?->level ?? 1),
                'enrolled',
            ],
        ]);
    }

    public function headings(): array
    {
        return [
            'student_number',
            'semester',
            'year',
            'status',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,
            'B' => 10,
            'C' => 10,
            'D' => 15,
        ];
    }

    public function title(): string
    {
        return 'Subject_Students_Template';
    }
}
