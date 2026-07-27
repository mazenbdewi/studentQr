<?php

namespace App\Exports;

use App\Services\WeeklyScheduleReportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class WeeklyScheduleReportExport implements FromCollection, ShouldAutoSize, WithCustomStartCell, WithEvents, WithHeadings, WithTitle
{
    public function __construct(
        private readonly string $type,
        private readonly array $filters,
    ) {}

    public function collection(): Collection
    {
        return app(WeeklyScheduleReportService::class)->rows($this->type, $this->filters);
    }

    public function headings(): array
    {
        return app(WeeklyScheduleReportService::class)->headings($this->type);
    }

    public function startCell(): string
    {
        return 'A4';
    }

    public function title(): string
    {
        return __('weekly-schedule-reports.worksheet_titles.'.$this->type);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $service = app(WeeklyScheduleReportService::class);
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn();
                $filterText = collect($service->activeFilterLabels($this->filters))
                    ->map(fn (string $value, string $label): string => "{$label}: {$value}")
                    ->implode(' | ');

                $sheet->setRightToLeft(true);
                $sheet->setCellValue('A1', WeeklyScheduleReportService::reportTypes()[$this->type]);
                $sheet->setCellValue('A2', $filterText !== '' ? $filterText : __('weekly-schedule-reports.all_records'));
                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->mergeCells("A2:{$lastColumn}2");
                $sheet->freezePane('A5');
                $sheet->setAutoFilter("A4:{$lastColumn}4");
                $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->setSize(15);
                $sheet->getStyle("A1:{$lastColumn}2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("A4:{$lastColumn}4")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            },
        ];
    }
}
