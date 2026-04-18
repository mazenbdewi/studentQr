<?php

namespace App\Exports\Templates;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class UsersTemplateExport implements WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            new UsersTemplateSheet(),
            new UsersTemplateInstructionsSheet(),
        ];
    }
}
