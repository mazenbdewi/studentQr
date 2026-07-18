<?php

use App\Models\AcademicTerm;
use App\Models\ImportBatch;
use App\Models\Subject;
use App\Models\SubjectSection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function reconciliationWorkbook(array $rows, string $sheetName = 'Schedule'): string
{
    $path = tempnam(sys_get_temp_dir(), 'reconciliation-').'.xlsx';
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle($sheetName);
    $sheet->fromArray([
        ['رمز الشعبة', 'نوع الفئة', 'رمز الفئة', 'اسم المدرس', 'اسم القاعة', 'سعة الفئة', 'عدد الطلاب', 'اسم المقرر الأساسي', 'كلية المقرر الأساسي', 'محصور بالكليات', 'محصور بالاختصاصات', 'السبت', 'الأحد', 'الاثنين'],
        ...$rows,
    ]);
    (new Xlsx($spreadsheet))->save($path);
    $spreadsheet->disconnectWorksheets();

    return $path;
}

function reconciliationSource(string $path, string $status = ImportBatch::STATUS_COMPLETED_WITH_ERRORS, string $type = ImportBatch::TYPE_WEEKLY_SCHEDULE): array
{
    $term = AcademicTerm::query()->create([
        'display_name' => 'الفصل الصيفي 2025/2026',
        'canonical_name' => 'الفصل الصيفي 2025/2026',
    ]);
    $source = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'reconciliation-source-'.uniqid()),
        'import_type' => ImportBatch::TYPE_ENROLLMENTS,
        'source_filename' => 'enrollments.xlsx',
        'status' => ImportBatch::STATUS_COMPLETED,
        'imported_rows' => 1,
        'total_rows' => 1,
    ]);
    $source->academicTerms()->attach($term->id, ['row_count' => 1]);
    $batch = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'reconciliation-schedule-'.uniqid()),
        'import_type' => $type,
        'source_filename' => basename($path),
        'source_fingerprint' => hash_file('sha256', $path),
        'source_import_batch_id' => $source->id,
        'status' => $status,
        'imported_rows' => 1,
        'total_rows' => 1,
        'summary' => ['imported_rows' => 1, 'rejected_rows' => 0, 'created_schedule_slots' => 0],
    ]);
    $batch->academicTerms()->attach($term->id, ['row_count' => 1]);
    $subject = Subject::query()->create([
        'code' => 'SCH101',
        'name' => 'مقرر الجدولة',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'is_active' => true,
    ]);
    $section = SubjectSection::query()->create([
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'section_type' => Subject::TYPE_THEORETICAL,
        'code' => 'T1',
        'raw_section_number' => '1',
    ]);

    return [$term, $source, $batch, $subject, $section];
}
