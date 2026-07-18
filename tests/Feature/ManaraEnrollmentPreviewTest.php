<?php

use App\Imports\ManaraStudentEnrollmentsPreviewImport;
use App\Models\AcademicTerm;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectSection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('previews practical-only zero rows without modifying the database or proposing P0 or T0', function (): void {
    $path = previewWorkbookPath([
        previewRow('2027001', 0, 1, 'الفصل  الصيفي  ٢٠٢٥/٢٠٢٦'),
        previewRow('2027002', '0.0', '0', 'الفصل الصيفي 2025/2026'),
    ]);

    try {
        $before = previewDatabaseCounts();
        $import = new ManaraStudentEnrollmentsPreviewImport();
        Excel::import($import, $path);
        $preview = $import->getPreview();

        expect(previewDatabaseCounts())->toBe($before)
            ->and($preview['total_rows'])->toBe(2)
            ->and($preview['valid_rows'])->toBe(1)
            ->and($preview['invalid_rows'])->toBe(1)
            ->and($preview['new_terms'])->toBe(1)
            ->and($preview['new_sections'])->toBe(1)
            ->and($preview['new_enrollments'])->toBe(1)
            ->and($preview['zero_section_values'])->toBe(3)
            ->and($preview['zero_sections_to_create'])->toBe(0)
            ->and($preview['terms'][0]['canonical_name'])->toBe('الفصل الصيفي 2025/2026')
            ->and($import->getErrors()[0]['error_message'])->toContain('At least one theoretical or practical section code is required');
    } finally {
        @unlink($path);
    }
});

it('blocks a preview when the academic term heading is missing', function (): void {
    $path = previewWorkbookPath([
        previewRow('2027003', 1, null, 'الفصل الصيفي 2025/2026'),
    ], includeAcademicTermHeading: false);

    try {
        $before = previewDatabaseCounts();
        $import = new ManaraStudentEnrollmentsPreviewImport();
        Excel::import($import, $path);
        $preview = $import->getPreview();

        expect(previewDatabaseCounts())->toBe($before)
            ->and($preview['can_apply'])->toBeFalse()
            ->and($preview['blocking_errors'])->not->toBeEmpty();
    } finally {
        @unlink($path);
    }
});

it('merges duplicate theoretical and practical rows into one proposed term enrollment', function (): void {
    $path = previewWorkbookPath([
        previewRow('2027004', 1, 0, 'الفصل الأول 2026/2027'),
        previewRow('2027004', 0, 1, 'الفصل الأول 2026/2027'),
    ]);

    try {
        $import = new ManaraStudentEnrollmentsPreviewImport();
        Excel::import($import, $path);
        $preview = $import->getPreview();

        expect($preview['valid_rows'])->toBe(2)
            ->and($preview['new_students'])->toBe(1)
            ->and($preview['new_subjects'])->toBe(1)
            ->and($preview['new_sections'])->toBe(2)
            ->and($preview['new_enrollments'])->toBe(1)
            ->and($preview['updated_enrollments'])->toBe(0)
            ->and($preview['zero_sections_to_create'])->toBe(0);
    } finally {
        @unlink($path);
    }
});

function previewWorkbookPath(array $rows, bool $includeAcademicTermHeading = true): string
{
    $headings = [
        'م', 'الرقم الجامعي', 'اسم الطالب', 'الكلية', 'الاختصاص', 'اسم المقرر',
        'رمز المقرر', 'تاريخ التسجيل', 'الفصل الدراسي', 'رمز الفئة النظرية',
        'رمز الفئة العملية', 'عبئ المقرر', 'حالة التسجيل', 'مستوى المقرر', 'سعة الفئة النظرية',
    ];

    if (! $includeAcademicTermHeading) {
        $termIndex = array_search('الفصل الدراسي', $headings, true);
        unset($headings[$termIndex]);
        $headings = array_values($headings);
        $rows = array_map(function (array $row) use ($termIndex): array {
            unset($row[$termIndex]);

            return array_values($row);
        }, $rows);
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray($headings, null, 'A1');

    foreach (array_values($rows) as $index => $row) {
        $sheet->fromArray($row, null, 'A'.($index + 2), true);
    }

    $path = sys_get_temp_dir().'/manara_preview_'.Str::uuid().'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

function previewRow(string $studentNumber, mixed $theoretical, mixed $practical, mixed $term): array
{
    return [
        null, $studentNumber, 'طالب المعاينة', 'كلية الهندسة', 'هندسة المعلوماتية',
        'برمجة', 'PRG101', '01/07/2026', $term, $theoretical, $practical,
        null, null, 3, null,
    ];
}

function previewDatabaseCounts(): array
{
    return [
        'terms' => AcademicTerm::query()->count(),
        'students' => Student::query()->count(),
        'subjects' => Subject::query()->count(),
        'sections' => SubjectSection::query()->count(),
        'enrollments' => Enrollment::query()->count(),
    ];
}
