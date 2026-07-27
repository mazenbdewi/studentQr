<?php

use App\Exports\Templates\UsersTemplateExport;
use App\Exports\Templates\UsersTemplateSheet;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['super-admin', 'admin', 'manager', 'course_lecturer', 'student'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

it('exports a username-only users template with stable import headings', function (): void {
    $book = usersTemplateSpreadsheetFromXlsxBytes(Excel::raw(new UsersTemplateExport, ExcelWriter::XLSX));
    $data = $book->getSheetByName('Users_Template');
    $instructions = $book->getSheetByName('Instructions');

    expect((new UsersTemplateSheet)->headings())->toBe(['name', 'login_username', 'password', 'role'])
        ->and($data)->not->toBeNull()
        ->and($instructions)->not->toBeNull()
        ->and($data->toArray()[0])->toBe(['name', 'login_username', 'password', 'role'])
        ->and($data->getCell('B2')->getValue())->toBe('admin_example')
        ->and(usersTemplateSpreadsheetCellValues($book))->toContain('اسم المستخدم / Login username')
        ->and(usersTemplateSpreadsheetCellValues($book))->toContain(
            'super_admin, admin, manager, attendance_monitor, course_lecturer, student'
        )
        ->and(usersTemplateSpreadsheetCellValues($book))->not->toContain('email')
        ->and(usersTemplateSpreadsheetCellValues($book))->not->toContain('email'.'_verified_at');
});

function usersTemplateSpreadsheetFromXlsxBytes(string $bytes): Spreadsheet
{
    $path = tempnam(sys_get_temp_dir(), 'users-template-');
    file_put_contents($path, $bytes);

    try {
        return IOFactory::load($path);
    } finally {
        @unlink($path);
    }
}

function usersTemplateSpreadsheetCellValues(Spreadsheet $spreadsheet): array
{
    $values = [];

    foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
        foreach ($worksheet->toArray() as $row) {
            foreach ($row as $value) {
                if ($value !== null && $value !== '') {
                    $values[] = (string) $value;
                }
            }
        }
    }

    return $values;
}
