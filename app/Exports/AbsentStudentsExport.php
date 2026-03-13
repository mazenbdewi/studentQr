<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;

class AbsentStudentsExport implements FromCollection
{
    protected $students;

    public function __construct($students)
    {
        $this->students = $students;
    }

    public function collection()
    {
        return $this->students;
    }
}