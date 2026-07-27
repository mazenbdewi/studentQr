<?php

namespace App\Exports;

use App\Exports\Sheets\ArabicArraySheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ArabicArrayWorkbookExport implements WithMultipleSheets
{
    /**
     * @param  array<int, array{title: string, headings: array<int, string>, rows: array<int, array<string, mixed>>}>  $sheets
     */
    public function __construct(private readonly array $sheets) {}

    public function sheets(): array
    {
        return collect($this->sheets)
            ->map(fn (array $sheet): ArabicArraySheet => new ArabicArraySheet(
                $sheet['title'],
                $sheet['headings'],
                $sheet['rows'],
            ))
            ->all();
    }
}
