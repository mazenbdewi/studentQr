<?php

namespace App\Exports;

use App\Services\HallMetadataService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class HallMetadataReportExport implements WithMultipleSheets
{
    /** @param array<int, array<string, mixed>> $rows */
    private function __construct(
        private readonly string $title,
        private readonly array $headings,
        private readonly array $rows,
    ) {}

    /** @param array<int, array<string, mixed>> $rows */
    public static function success(array $rows): self
    {
        return new self(
            HallMetadataService::WORKSHEET_SUCCESS,
            ['رقم الصف', 'رمز القاعة', 'اسم القاعة', 'النتيجة', 'الملاحظة'],
            $rows,
        );
    }

    /** @param array<int, array<string, mixed>> $rows */
    public static function errors(array $rows): self
    {
        return new self(
            HallMetadataService::WORKSHEET_ERRORS,
            ['رقم الصف', 'رمز القاعة', 'رمز الخطأ', 'السبب بالعربية', 'الإجراء المقترح'],
            $rows,
        );
    }

    public function sheets(): array
    {
        return (new ArabicArrayWorkbookExport([
            [
                'title' => $this->title,
                'headings' => $this->headings,
                'rows' => $this->rows,
            ],
        ]))->sheets();
    }
}
