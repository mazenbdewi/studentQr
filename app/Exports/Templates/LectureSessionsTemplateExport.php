<?php

namespace App\Exports\Templates;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class LectureSessionsTemplateExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize
{
    public function collection(): Collection
    {
        return collect([
            [
                'برمجة أساسية',
                'B44',
                '15-10-2024',
                '09:00',
                '10:30',
                'scheduled',
                'qr_otp',
                '120',
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
}