<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class SpreadsheetRowCounter
{
    public function countSourceRows(string $path): int
    {
        $worksheets = IOFactory::createReaderForFile($path)->listWorksheetInfo($path);
        $totalRows = (int) ($worksheets[0]['totalRows'] ?? 0);

        if ($totalRows < 1) {
            throw new RuntimeException('ملف الاستيراد لا يحتوي صف العناوين.');
        }

        return max(0, $totalRows - 1);
    }
}
