<?php

namespace App\Exports;

use App\Models\ImportBatch;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ScheduleImportReconciliationExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly ImportBatch $batch) {}

    public function collection(): Collection
    {
        return ScheduleImportRow::query()
            ->where('import_batch_id', $this->batch->id)
            ->with([
                'academicTerm',
                'issues.resolvedSubject',
                'issues.resolvedSubjectSection',
                'issues.resolver',
                ...(Schema::hasColumn('schedule_import_rows', 'excluded_from_weekly_schedule_by') ? ['excludedFromWeeklyScheduleBy'] : []),
            ])
            ->orderBy('source_row_number')
            ->get()
            ->flatMap(function (ScheduleImportRow $row): array {
                $issues = $row->issues->isEmpty() ? [null] : $row->issues->all();

                return collect($issues)->map(function (?ScheduleImportIssue $issue) use ($row): array {
                    $source = $row->source_payload;
                    $normalized = $row->normalized_payload;

                    return [
                        $row->source_sheet_name,
                        $row->source_row_number,
                        $source['subject_code'] ?? null,
                        $source['subject_name'] ?? null,
                        $source['section_type'] ?? null,
                        $source['section_number'] ?? null,
                        $normalized['section_code'] ?? null,
                        $source['expected_student_count'] ?? null,
                        $source['teacher_name'] ?? null,
                        $source['hall_name'] ?? null,
                        json_encode($source['weekday_values'] ?? [], JSON_UNESCAPED_UNICODE),
                        $row->academicTerm->display_name,
                        $row->isExcludedFromWeeklySchedule() ? 'excluded_from_batch_schedule' : null,
                        $row->isExcludedFromWeeklySchedule() ? $row->exclusion_note : null,
                        $row->isExcludedFromWeeklySchedule() ? $row->excludedFromWeeklyScheduleBy?->name : null,
                        $row->isExcludedFromWeeklySchedule() ? $row->excluded_from_weekly_schedule_at?->toDateTimeString() : null,
                        $issue?->issue_type,
                        $issue?->severity,
                        $issue?->reason_ar,
                        $issue instanceof ScheduleImportIssue ? $issue->resolution_status : $row->current_reconciliation_status,
                        $issue?->resolution_action,
                        $issue?->resolvedSubject?->code,
                        $issue?->resolvedSubjectSection?->code,
                        $issue?->resolution_note,
                        $issue?->resolver?->name,
                        $issue?->resolved_at?->toDateTimeString(),
                        json_encode($issue?->retry_result, JSON_UNESCAPED_UNICODE),
                    ];
                })->all();
            });
    }

    public function headings(): array
    {
        return [
            'Source sheet', 'Excel row number', 'Source subject code', 'Source subject name',
            'Source section type', 'Source section number', 'Normalized section code',
            'Expected student count', 'Original lecturer name', 'Original hall name',
            'Source weekday/time values', 'Academic term', 'Batch schedule outcome',
            'Exclusion reason', 'Excluded by', 'Excluded at', 'Issue category', 'Severity',
            'Arabic reason', 'Resolution status', 'Resolution action', 'Selected subject',
            'Selected section', 'Resolution note', 'Resolved by', 'Resolved at', 'Retry result',
        ];
    }
}
