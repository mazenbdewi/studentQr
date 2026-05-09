<?php

namespace App\Exports\Templates;

use App\Models\AppSetting;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class LectureSessionsTemplateExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithEvents
{
    public function collection(): Collection
    {
        return collect([
            [
                'برمجة أساسية',
                'B44',
                ExcelDate::dateTimeToExcel(new \DateTimeImmutable('2026-04-28')),
                ExcelDate::dateTimeToExcel(new \DateTimeImmutable('1970-01-01 08:30:00')),
                ExcelDate::dateTimeToExcel(new \DateTimeImmutable('1970-01-01 10:00:00')),
                'scheduled',
                'qr_otp',
                (string) AppSetting::defaultQrRefreshRate(),
                'ملاحظات',
            ],
        ]);
    }

    public function headings(): array
    {
        return [
            'subject_name',
            'hall_name',
            'session_date',
            'start_time',
            'end_time',
            'status',
            'attendance_mode',
            'qr_refresh_rate',
            'notes',
        ];
    }

    public function title(): string
    {
        return 'LectureSessions_Template';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('C2:C1000')
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_DATE_YYYYMMDD2);

                $sheet->getStyle('D2:E1000')
                    ->getNumberFormat()
                    ->setFormatCode('hh:mm');

                for ($row = 2; $row <= 1000; $row++) {
                    $this->applyDateValidation($sheet, "C{$row}");
                    $this->applyTimeValidation($sheet, "D{$row}");
                    $this->applyTimeValidation($sheet, "E{$row}");
                }
            },
        ];
    }

    private function applyDateValidation(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $cell): void
    {
        $validation = $sheet->getCell($cell)->getDataValidation();
        $validation->setType(DataValidation::TYPE_DATE);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(false);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setPromptTitle(__('lecture-session.template_date_prompt_title'));
        $validation->setPrompt(__('lecture-session.template_date_prompt'));
        $validation->setErrorTitle(__('lecture-session.template_date_error_title'));
        $validation->setError(__('lecture-session.template_date_error'));
        $validation->setOperator(DataValidation::OPERATOR_BETWEEN);
        $validation->setFormula1('DATE(2000,1,1)');
        $validation->setFormula2('DATE(2100,12,31)');
    }

    private function applyTimeValidation(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $cell): void
    {
        $validation = $sheet->getCell($cell)->getDataValidation();
        $validation->setType(DataValidation::TYPE_TIME);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(false);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setPromptTitle(__('lecture-session.template_time_prompt_title'));
        $validation->setPrompt(__('lecture-session.template_time_prompt'));
        $validation->setErrorTitle(__('lecture-session.template_time_error_title'));
        $validation->setError(__('lecture-session.template_time_error'));
        $validation->setOperator(DataValidation::OPERATOR_BETWEEN);
        $validation->setFormula1('TIME(0,0,0)');
        $validation->setFormula2('TIME(23,59,0)');
    }
}
