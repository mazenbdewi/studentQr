<?php

use App\Models\ImportBatch;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportRow;
use App\Models\Subject;
use App\Models\SubjectSectionScheduleSlot;
use App\Services\ScheduleImportReconciliationBuilder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__.'/../Support/ScheduleImportReconciliationFixtures.php';

it('accepts completed-with-errors and builds idempotent metadata without changing slots or original summary', function (): void {
    $path = reconciliationWorkbook([
        ['SCH101', 'T', 1, null, null, 20, 18, 'مقرر الجدولة', null, null, null, '08:30AM-10:30AM', '-', '-'],
    ], 'Summer schedule');

    try {
        [$term, , $batch, $subject, $section] = reconciliationSource($path);
        SubjectSectionScheduleSlot::query()->create([
            'import_batch_id' => $batch->id,
            'academic_term_id' => $term->id,
            'subject_id' => $subject->id,
            'subject_section_id' => $section->id,
            'weekday' => 6,
            'start_time' => '08:30:00',
            'end_time' => '10:30:00',
        ]);
        $originalSummary = $batch->summary;
        $builder = app(ScheduleImportReconciliationBuilder::class);
        $first = $builder->build($batch, $path, 'summer2026_schedule.xlsx', 'retained.xlsx');
        $second = $builder->build($batch->fresh(), $path, 'summer2026_schedule.xlsx', 'retained.xlsx');

        expect($first['created_rows'])->toBe(1)
            ->and($second['reused_rows'])->toBe(1)
            ->and(ScheduleImportRow::query()->count())->toBe(1)
            ->and(ScheduleImportIssue::query()->count())->toBe(2)
            ->and(SubjectSectionScheduleSlot::query()->count())->toBe(1)
            ->and($first['slot_count_before'])->toBe(1)
            ->and($first['slot_count_after'])->toBe(1)
            ->and($batch->fresh()->source_filename)->toBe('summer2026_schedule.xlsx')
            ->and($batch->fresh()->source_file_path)->toBe('retained.xlsx')
            ->and(collect($originalSummary)->diffAssoc(collect($batch->fresh()->summary)->except('reconciliation')))->toBeEmpty();
    } finally {
        @unlink($path);
    }
});

it('rejects failed and non-schedule batches plus missing or mismatched retained workbooks before metadata writes', function (): void {
    $path = reconciliationWorkbook([['SCH101', 'T', 1, null, null, 20, 18, null, null, null, null, '-', '-', '-']]);

    try {
        [, , $failed] = reconciliationSource($path, ImportBatch::STATUS_FAILED);
        expect(fn () => app(ScheduleImportReconciliationBuilder::class)->build($failed, $path))->toThrow(RuntimeException::class);

        foreach ([ImportBatch::STATUS_PENDING, ImportBatch::STATUS_PROCESSING] as $ineligibleStatus) {
            $failed->update(['status' => $ineligibleStatus]);
            expect(fn () => app(ScheduleImportReconciliationBuilder::class)->build($failed, $path))->toThrow(RuntimeException::class);
        }

        $failed->update(['status' => ImportBatch::STATUS_COMPLETED, 'import_type' => ImportBatch::TYPE_ENROLLMENTS]);
        expect(fn () => app(ScheduleImportReconciliationBuilder::class)->build($failed, $path))->toThrow(RuntimeException::class);

        $failed->update(['import_type' => ImportBatch::TYPE_WEEKLY_SCHEDULE, 'source_fingerprint' => str_repeat('0', 64)]);
        expect(fn () => app(ScheduleImportReconciliationBuilder::class)->build($failed, $path))->toThrow(RuntimeException::class, 'بصمة')
            ->and(fn () => app(ScheduleImportReconciliationBuilder::class)->build($failed, $path.'.missing'))->toThrow(RuntimeException::class, 'غير موجود')
            ->and(ScheduleImportRow::query()->count())->toBe(0);
    } finally {
        @unlink($path);
    }
});

it('stores deterministic outer-bracket suggestions without applying them', function (): void {
    $path = reconciliationWorkbook([['[SCH101]', 'T', 1, null, null, 20, 18, 'مقرر الجدولة', null, null, null, '-', '-', '-']]);

    try {
        [, , $batch] = reconciliationSource($path);
        app(ScheduleImportReconciliationBuilder::class)->build($batch, $path);
        $issue = ScheduleImportIssue::query()->where('issue_type', ScheduleImportIssue::TYPE_NON_AUTHORITATIVE_SUBJECT_CODE)->sole();

        expect($issue->resolved_subject_id)->toBeNull()
            ->and($issue->suggested_matches[0]['subject']['code'])->toBe('SCH101')
            ->and($issue->suggested_matches[0]['match_reasons'])->toContain('outer_brackets_removed');
    } finally {
        @unlink($path);
    }
});

it('preserves an existing set of 890 weekly slots while building metadata', function (): void {
    $path = reconciliationWorkbook([
        ['SCH101', 'T', 1, null, null, 20, 18, 'مقرر الجدولة', null, null, null, '-', '-', '-'],
    ]);

    try {
        [$term, , $batch, $subject, $firstSection] = reconciliationSource($path);
        $now = now();
        $sections = [];

        for ($number = 2; $number <= 890; $number++) {
            $sections[] = [
                'academic_term_id' => $term->id,
                'subject_id' => $subject->id,
                'section_type' => Subject::TYPE_THEORETICAL,
                'code' => "T{$number}",
                'raw_section_number' => (string) $number,
                'section_number' => $number,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($sections, 200) as $chunk) {
            DB::table('subject_sections')->insert($chunk);
        }

        $sectionIds = DB::table('subject_sections')->where('academic_term_id', $term->id)->pluck('id');
        $slots = $sectionIds->map(fn (int $sectionId): array => [
            'import_batch_id' => $batch->id,
            'academic_term_id' => $term->id,
            'subject_id' => $subject->id,
            'subject_section_id' => $sectionId,
            'weekday' => 6,
            'start_time' => '08:30:00',
            'end_time' => '10:30:00',
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        foreach (array_chunk($slots, 200) as $chunk) {
            DB::table('subject_section_schedule_slots')->insert($chunk);
        }

        $result = app(ScheduleImportReconciliationBuilder::class)->build($batch, $path);

        expect($firstSection->exists)->toBeTrue()
            ->and($result['slot_count_before'])->toBe(890)
            ->and($result['slot_count_after'])->toBe(890)
            ->and(SubjectSectionScheduleSlot::query()->count())->toBe(890);
    } finally {
        @unlink($path);
    }
});

it('intentionally analyzes only the active worksheet and stores its sheet identity', function (): void {
    $path = reconciliationWorkbook([
        ['SCH101', 'T', 1, null, null, 20, 18, 'مقرر الجدولة', null, null, null, '08:30AM-10:30AM', '-', '-'],
    ], 'Active schedule');

    try {
        $spreadsheet = IOFactory::load($path);
        $spreadsheet->createSheet()->setTitle('Ignored sheet')->fromArray([
            ['رمز الشعبة', 'نوع الفئة', 'رمز الفئة', 'اسم المدرس', 'اسم القاعة', 'سعة الفئة', 'عدد الطلاب', 'اسم المقرر الأساسي', 'كلية المقرر الأساسي', 'محصور بالكليات', 'محصور بالاختصاصات', 'السبت', 'الأحد', 'الاثنين'],
            ['MISSING', 'T', 1, null, null, 20, 18, 'مقرر آخر', null, null, null, '08:30AM-10:30AM', '-', '-'],
        ]);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        [, , $batch] = reconciliationSource($path);
        $result = app(ScheduleImportReconciliationBuilder::class)->build($batch, $path);

        expect($result['parsed_rows'])->toBe(1)
            ->and(ScheduleImportRow::query()->sole()->source_sheet_name)->toBe('Active schedule');
    } finally {
        @unlink($path);
    }
});
