<?php

use App\Imports\WeeklyScheduleImport;
use App\Models\AcademicTerm;
use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\Lecturer;
use App\Models\LectureSession;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('imports multiple weekdays, creates no dated sessions, and is idempotent', function (): void {
    [$term, $sourceBatch, $subject, $section] = weeklyScheduleSource();
    $path = weeklyScheduleWorkbook([
        ['SCH101', 'T', '1.00', 'مدرس جديد', 'F-03', 20, 18, '08:30AM-10:30AM', '-', '10:30AM-12:30PM'],
    ]);

    try {
        $fingerprint = hash_file('sha256', $path);
        $import = app(WeeklyScheduleImport::class);
        $import->import($path, 'schedule.xlsx', $sourceBatch->uuid, null, $fingerprint);

        expect(SubjectSectionScheduleSlot::query()->count())->toBe(2)
            ->and(LectureSession::query()->count())->toBe(0)
            ->and(Hall::query()->where('name', 'F-03')->count())->toBe(1)
            ->and(Lecturer::query()->where('canonical_name', 'مدرس جديد')->count())->toBe(1)
            ->and($import->getSummary()['created_schedule_slots'])->toBe(2);

        app(WeeklyScheduleImport::class)->import($path, 'schedule.xlsx', $sourceBatch->uuid, null, $fingerprint);

        expect(SubjectSectionScheduleSlot::query()->count())->toBe(2)
            ->and(ImportBatch::query()->where('import_type', ImportBatch::TYPE_WEEKLY_SCHEDULE)->count())->toBe(1)
            ->and(AcademicTerm::query()->count())->toBe(1)
            ->and(Subject::query()->count())->toBe(1)
            ->and(SubjectSection::query()->count())->toBe(1);
    } finally {
        @unlink($path);
    }
});

it('rejects missing subjects and sections without creating catalog data', function (): void {
    [, $sourceBatch] = weeklyScheduleSource();
    $path = weeklyScheduleWorkbook([
        ['MISSING101', 'P', '1.00', null, null, 20, 18, '08:30AM-10:30AM', '-', '-'],
        ['SCH101', 'P', '1.00', null, null, 20, 18, '08:30AM-10:30AM', '-', '-'],
    ]);

    try {
        $import = app(WeeklyScheduleImport::class);
        $import->import($path, 'missing.xlsx', $sourceBatch->uuid);

        expect($import->getSummary()['missing_subjects'])->toBe(1)
            ->and($import->getSummary()['missing_sections'])->toBe(1)
            ->and($import->getSummary()['rejected_rows'])->toBe(2)
            ->and(SubjectSectionScheduleSlot::query()->count())->toBe(0)
            ->and(Subject::query()->count())->toBe(1)
            ->and(SubjectSection::query()->count())->toBe(1);
    } finally {
        @unlink($path);
    }
});

it('does not select a newest batch when direct resolution has multiple compatible batches', function (): void {
    [$term, $sourceBatch] = weeklyScheduleSource();
    $otherBatch = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'other-compatible-source'),
        'import_type' => ImportBatch::TYPE_ENROLLMENTS,
        'source_filename' => 'other-enrollments.xlsx',
        'status' => ImportBatch::STATUS_COMPLETED,
        'total_rows' => 1,
        'imported_rows' => 1,
    ]);
    $otherBatch->academicTerms()->attach($term->id, ['row_count' => 1]);
    $path = weeklyScheduleWorkbook([
        ['SCH101', 'T', 1, null, 0, 20, 18, '08:30AM-10:30AM', '-', '-'],
    ]);

    try {
        expect(fn () => app(WeeklyScheduleImport::class)->import($path, 'ambiguous-batches.xlsx'))
            ->toThrow(RuntimeException::class, 'أكثر من دفعة تسجيل متوافقة');
        expect(ImportBatch::query()->where('import_type', ImportBatch::TYPE_WEEKLY_SCHEDULE)->count())->toBe(0);
    } finally {
        @unlink($path);
    }
});

it('ignores identical duplicates and rejects conflicting duplicate metadata', function (): void {
    [, $sourceBatch] = weeklyScheduleSource();
    $path = weeklyScheduleWorkbook([
        ['SCH101', 'T', 1, 'مدرس أول', 'A-01', 20, 18, '08:30AM-10:30AM', '-', '-'],
        ['SCH101', 'T', 1, 'مدرس أول', 'A-01', 20, 18, '08:30AM-10:30AM', '-', '-'],
        ['SCH101', 'T', 1, 'مدرس آخر', 'B-01', 20, 18, '08:30AM-10:30AM', '-', '-'],
    ]);

    try {
        $import = app(WeeklyScheduleImport::class);
        $import->import($path, 'conflicts.xlsx', $sourceBatch->uuid);

        expect($import->getSummary()['identical_duplicates_ignored'])->toBe(1)
            ->and($import->getSummary()['conflicting_duplicates'])->toBe(1)
            ->and(SubjectSectionScheduleSlot::query()->count())->toBe(0)
            ->and(Lecturer::query()->count())->toBe(0)
            ->and(Hall::query()->count())->toBe(0);
    } finally {
        @unlink($path);
    }
});

it('reports ambiguous normalized lecturer names instead of choosing one', function (): void {
    [, $sourceBatch] = weeklyScheduleSource();
    Lecturer::query()->create(['name' => 'مدرس مكرر', 'canonical_name' => 'مدرس مكرر', 'is_active' => true]);
    Lecturer::query()->create(['name' => 'مدرس  مكرر', 'canonical_name' => 'مدرس مكرر', 'is_active' => true]);
    $path = weeklyScheduleWorkbook([
        ['SCH101', 'T', 1, 'مدرس مكرر', 'A-01', 20, 18, '08:30AM-10:30AM', '-', '-'],
    ]);

    try {
        $import = app(WeeklyScheduleImport::class);
        $import->import($path, 'ambiguous.xlsx', $sourceBatch->uuid);

        expect($import->getSummary()['ambiguous_lecturers'])->toBe(1)
            ->and(SubjectSectionScheduleSlot::query()->count())->toBe(0)
            ->and($import->getErrors())->not->toBeEmpty();
    } finally {
        @unlink($path);
    }
});

function weeklyScheduleSource(): array
{
    $term = AcademicTerm::query()->create([
        'display_name' => 'الفصل الصيفي 2025/2026',
        'canonical_name' => 'الفصل الصيفي 2025/2026',
    ]);
    $batch = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'weekly-source-'.uniqid()),
        'import_type' => ImportBatch::TYPE_ENROLLMENTS,
        'source_filename' => 'enrollments.xlsx',
        'status' => ImportBatch::STATUS_COMPLETED,
        'total_rows' => 1,
        'imported_rows' => 1,
    ]);
    $batch->academicTerms()->attach($term->id, ['row_count' => 1]);
    $subject = Subject::query()->create([
        'code' => 'SCH101',
        'name' => 'جدولة',
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

    return [$term, $batch, $subject, $section];
}

function weeklyScheduleWorkbook(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'weekly-schedule-').'.xlsx';
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['رمز الشعبة', 'نوع الفئة', 'رمز الفئة', 'اسم المدرس', 'اسم القاعة', 'سعة الفئة', 'عدد الطلاب', 'السبت', 'الأحد', 'الاثنين'],
        ...$rows,
    ]);
    (new Xlsx($spreadsheet))->save($path);
    $spreadsheet->disconnectWorksheets();

    return $path;
}
