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
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class HallsTemplateExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
  public function collection(): Collection
{
    return collect([
        ['A101', 'B44', '1', 'true'],
    ]);
}

  public function headings(): array
{
    return [
        'code',
        'name',
        'floor',
        'is_active',
    ];
}

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            2 => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FFF2CC'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,
            'B' => 20,
            'C' => 8,
            'D' => 10,
            'E' => 10,
        ];
    }

    public function title(): string
    {
        return 'Halls_Template';
    }
}