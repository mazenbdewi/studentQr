<?php

use App\Exports\ScheduleImportReconciliationExport;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportRow;

require_once __DIR__.'/../Support/ScheduleImportReconciliationFixtures.php';

it('exports issue classification, resolution, mapping, actor, and retry fields', function (): void {
    $path = reconciliationWorkbook([]);

    try {
        [$term, , $batch, $subject, $section] = reconciliationSource($path);
        $row = ScheduleImportRow::query()->create([
            'import_batch_id' => $batch->id, 'academic_term_id' => $term->id, 'source_sheet_name' => 'Schedule',
            'source_row_number' => 2, 'row_fingerprint' => hash('sha256', 'export-row'),
            'source_payload' => ['subject_code' => 'OLD', 'subject_name' => 'اسم مصدر', 'weekday_values' => [6 => '08:30-10:30']],
            'normalized_payload' => ['section_code' => 'T1'],
            'original_import_status' => ScheduleImportRow::ORIGINAL_REJECTED, 'current_reconciliation_status' => ScheduleImportRow::STATUS_RESOLVED,
        ]);
        ScheduleImportIssue::query()->create([
            'schedule_import_row_id' => $row->id, 'deduplication_key' => hash('sha256', 'export-issue'),
            'issue_type' => ScheduleImportIssue::TYPE_SUBJECT_NOT_FOUND, 'severity' => ScheduleImportIssue::SEVERITY_ERROR,
            'reason_ar' => 'مقرر مفقود', 'resolved_subject_id' => $subject->id, 'resolved_subject_section_id' => $section->id,
            'resolution_status' => ScheduleImportIssue::STATUS_RESOLVED, 'resolution_action' => 'link', 'resolution_note' => 'تم الربط',
        ]);
        $export = new ScheduleImportReconciliationExport($batch);
        $exported = $export->collection()->sole();

        expect($export->headings())->toContain('Issue category', 'Severity', 'Resolution status', 'Selected subject', 'Selected section', 'Resolution note', 'Retry result')
            ->and($exported[12])->toBe(ScheduleImportIssue::TYPE_SUBJECT_NOT_FOUND)
            ->and($exported[17])->toBe('SCH101')
            ->and($exported[18])->toBe('T1');
    } finally {
        @unlink($path);
    }
});
