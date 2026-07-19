<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\Lecturer;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportRow;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Support\WeeklyScheduleRowNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WeeklyScheduleReportService
{
    public const COMPREHENSIVE = 'comprehensive';

    public const BY_LECTURER = 'by_lecturer';

    public const BY_HALL = 'by_hall';

    public const BY_SUBJECT = 'by_subject';

    public const BY_WEEKDAY = 'by_weekday';

    public const RECONCILIATION = 'reconciliation';

    public function __construct(private readonly WeeklyScheduleRowNormalizer $normalizer) {}

    /** @return array<string, string> */
    public static function reportTypes(): array
    {
        return __('weekly-schedule-reports.types');
    }

    public static function isSupportedType(string $type): bool
    {
        return array_key_exists($type, self::reportTypes());
    }

    /** @return Builder<SubjectSectionScheduleSlot> */
    public function slotQuery(array $filters): Builder
    {
        $filters = $this->normalizeFilters($filters);

        return SubjectSectionScheduleSlot::query()
            ->with([
                'academicTerm',
                'subject.department.faculty',
                'subjectSection',
                'lecturer',
                'hall',
                'importBatch',
            ])
            ->when($filters['academic_term_id'], fn (Builder $query, int $value): Builder => $query->where('academic_term_id', $value))
            ->when($filters['import_batch_id'], fn (Builder $query, int $value): Builder => $query->where('import_batch_id', $value))
            ->when($filters['faculty_id'], fn (Builder $query, int $value): Builder => $query->whereHas(
                'subject.department',
                fn (Builder $department): Builder => $department->where('faculty_id', $value),
            ))
            ->when($filters['department_id'], fn (Builder $query, int $value): Builder => $query->whereHas(
                'subject',
                fn (Builder $subject): Builder => $subject->where('department_id', $value),
            ))
            ->when($filters['subject_id'], fn (Builder $query, int $value): Builder => $query->where('subject_id', $value))
            ->when($filters['section_type'], fn (Builder $query, string $value): Builder => $query->whereHas(
                'subjectSection',
                fn (Builder $section): Builder => $section->where('section_type', $value),
            ))
            ->when($filters['subject_section_id'], fn (Builder $query, int $value): Builder => $query->where('subject_section_id', $value))
            ->when($filters['lecturer_id'], fn (Builder $query, int $value): Builder => $query->where('lecturer_id', $value))
            ->when($filters['hall_id'], fn (Builder $query, int $value): Builder => $query->where('hall_id', $value))
            ->when($filters['weekday'], fn (Builder $query, int $value): Builder => $query->where('weekday', $value))
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->orderBy('subject_id')
            ->orderBy('subject_section_id');
    }

    /** @return array<string, int> */
    public function summary(array $filters): array
    {
        $query = $this->slotQuery($filters);
        $reviewCounts = $this->reviewCounts($filters);

        return [
            'total' => (clone $query)->count(),
            'subjects' => (clone $query)->distinct()->count('subject_id'),
            'theoretical_sections' => (clone $query)
                ->whereHas('subjectSection', fn (Builder $section): Builder => $section->where('section_type', Subject::TYPE_THEORETICAL))
                ->distinct()
                ->count('subject_section_id'),
            'practical_sections' => (clone $query)
                ->whereHas('subjectSection', fn (Builder $section): Builder => $section->where('section_type', Subject::TYPE_PRACTICAL))
                ->distinct()
                ->count('subject_section_id'),
            'lecturers' => (clone $query)->whereNotNull('lecturer_id')->distinct()->count('lecturer_id'),
            'halls' => (clone $query)->whereNotNull('hall_id')->distinct()->count('hall_id'),
            'needs_review' => $reviewCounts['needs_attention'] + $reviewCounts['warnings'],
            'unscheduled' => $reviewCounts['unscheduled'],
        ];
    }

    /** @return array<string, int> */
    public function reviewCounts(array $filters): array
    {
        $rows = $this->reviewRows($filters);
        $unresolvedErrors = fn (ScheduleImportRow $row): bool => $row->issues->contains(fn (ScheduleImportIssue $issue): bool => $issue->severity === ScheduleImportIssue::SEVERITY_ERROR
            && in_array($issue->resolution_status, [ScheduleImportIssue::STATUS_UNRESOLVED, ScheduleImportIssue::STATUS_RETRY_FAILED], true));
        $unresolvedWarnings = fn (ScheduleImportRow $row): bool => $row->issues->contains(fn (ScheduleImportIssue $issue): bool => $issue->severity === ScheduleImportIssue::SEVERITY_WARNING
            && $issue->resolution_status === ScheduleImportIssue::STATUS_UNRESOLVED);
        $excluded = fn (ScheduleImportRow $row): bool => in_array($row->current_reconciliation_status, [
            ScheduleImportRow::STATUS_IGNORED,
            ScheduleImportRow::STATUS_INTENTIONALLY_UNSCHEDULED,
            ScheduleImportRow::STATUS_EXCLUDED_FROM_BATCH_SCHEDULE,
        ], true);

        return [
            'needs_attention' => $rows->filter(fn (ScheduleImportRow $row): bool => ! $excluded($row) && $unresolvedErrors($row))->count(),
            'warnings' => $rows->filter(fn (ScheduleImportRow $row): bool => ! $excluded($row) && ! $unresolvedErrors($row) && $unresolvedWarnings($row))->count(),
            'excluded' => $rows->filter($excluded)->count(),
            'successful' => $rows->filter(fn (ScheduleImportRow $row): bool => $row->current_reconciliation_status === ScheduleImportRow::STATUS_RESOLVED
                && ! $row->issues->contains('resolution_status', ScheduleImportIssue::STATUS_UNRESOLVED)
                && ($row->original_import_status !== ScheduleImportRow::ORIGINAL_UNSCHEDULED)
                && (($row->import_result['slot_ids'] ?? []) !== [] || ($row->import_result['reconciliation_slot_ids'] ?? []) !== []))->count(),
            'missing_subjects' => $this->countRowsWithIssueTypes($rows, [
                ScheduleImportIssue::TYPE_SUBJECT_NOT_FOUND,
                ScheduleImportIssue::TYPE_ZERO_STUDENT_SUBJECT_MISSING,
                ScheduleImportIssue::TYPE_NON_AUTHORITATIVE_SUBJECT_CODE,
            ]),
            'missing_sections' => $this->countRowsWithIssueTypes($rows, [
                ScheduleImportIssue::TYPE_SECTION_NOT_FOUND,
                ScheduleImportIssue::TYPE_ZERO_STUDENT_SECTION_MISSING,
            ]),
            'missing_lecturers' => $this->countRowsWithIssueTypes($rows, [ScheduleImportIssue::TYPE_LECTURER_MISSING]),
            'missing_halls' => $this->countRowsWithIssueTypes($rows, [ScheduleImportIssue::TYPE_HALL_MISSING]),
            'no_weekly_time' => $this->countRowsWithIssueTypes($rows, [ScheduleImportIssue::TYPE_NO_WEEKLY_TIME]),
            'unscheduled' => $rows->filter(fn (ScheduleImportRow $row): bool => $row->original_import_status === ScheduleImportRow::ORIGINAL_UNSCHEDULED
                || $row->current_reconciliation_status === ScheduleImportRow::STATUS_INTENTIONALLY_UNSCHEDULED
                || $row->current_reconciliation_status === ScheduleImportRow::STATUS_EXCLUDED_FROM_BATCH_SCHEDULE
                || $row->issues->contains('issue_type', ScheduleImportIssue::TYPE_NO_WEEKLY_TIME))->count(),
        ];
    }

    /** @return Collection<int, ScheduleImportRow> */
    public function reviewRows(array $filters): Collection
    {
        $filters = $this->normalizeFilters($filters);
        $query = ScheduleImportRow::query()
            ->with(['issues', 'academicTerm', 'importBatch'])
            ->when($filters['academic_term_id'], fn (Builder $query, int $value): Builder => $query->where('academic_term_id', $value))
            ->when($filters['import_batch_id'], fn (Builder $query, int $value): Builder => $query->where('import_batch_id', $value));

        $subjectKeys = $this->allowedSubjectKeys($filters);
        $section = $filters['subject_section_id'] ? SubjectSection::query()->with('subject')->find($filters['subject_section_id']) : null;
        $lecturer = $filters['lecturer_id'] ? Lecturer::query()->find($filters['lecturer_id']) : null;
        $hall = $filters['hall_id'] ? Hall::withTrashed()->find($filters['hall_id']) : null;

        return $query->get()->filter(function (ScheduleImportRow $row) use ($filters, $subjectKeys, $section, $lecturer, $hall): bool {
            $normalized = $row->normalized_payload ?? [];

            if ($subjectKeys !== null && ! in_array($normalized['subject_code_key'] ?? '', $subjectKeys, true)) {
                return false;
            }

            if ($filters['section_type']) {
                $expectedType = $filters['section_type'] === Subject::TYPE_PRACTICAL ? 'P' : 'T';

                if (($normalized['section_type'] ?? null) !== $expectedType) {
                    return false;
                }
            }

            if ($section instanceof SubjectSection) {
                $subjectKey = $section->subject instanceof Subject ? $this->normalizer->normalizeKey($section->subject->code) : null;

                if (($normalized['subject_code_key'] ?? null) !== $subjectKey
                    || ($normalized['section_code'] ?? null) !== SubjectSection::normalizeCode($section->code)) {
                    return false;
                }
            }

            if ($lecturer instanceof Lecturer && ($normalized['teacher_name_key'] ?? '') !== $this->normalizer->normalizeKey($lecturer->canonical_name ?: $lecturer->name)) {
                return false;
            }

            if ($hall instanceof Hall && ! in_array($normalized['hall_name_key'] ?? '', array_filter([
                $this->normalizer->normalizeKey($hall->name),
                $this->normalizer->normalizeKey($hall->code),
            ]), true)) {
                return false;
            }

            if ($filters['weekday'] && $this->normalizer->isMissingValue(($normalized['weekday_values'] ?? [])[$filters['weekday']] ?? null)) {
                return false;
            }

            return true;
        })->values();
    }

    /** @return Collection<int, array<int, mixed>> */
    public function rows(string $type, array $filters): Collection
    {
        if ($type === self::RECONCILIATION) {
            /** @var Collection<int, array<int, mixed>> $rows */
            $rows = collect($this->reviewCounts($filters))
                ->map(fn (int $count, string $key): array => [(string) __('weekly-schedule-reports.reconciliation.'.$key), $count])
                ->values();

            return $rows;
        }

        return $this->slotQuery($filters)->get()->map(fn (SubjectSectionScheduleSlot $slot): array => $this->mapSlot($slot, $type));
    }

    /** @return array<int, string> */
    public function headings(string $type): array
    {
        return __('weekly-schedule-reports.headings.'.$type);
    }

    /** @return array<string, string> */
    public function activeFilterLabels(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $labels = [];
        $resolvers = [
            'academic_term_id' => ['academic_term', fn (int $id): ?string => AcademicTerm::find($id)?->display_name],
            'import_batch_id' => ['import_batch', fn (int $id): ?string => ImportBatch::find($id)?->source_filename],
            'faculty_id' => ['faculty', fn (int $id): ?string => Faculty::find($id)?->name],
            'department_id' => ['department', fn (int $id): ?string => Department::find($id)?->name],
            'subject_id' => ['subject', fn (int $id): ?string => Subject::find($id)?->name],
            'subject_section_id' => ['subject_section', fn (int $id): ?string => SubjectSection::find($id)?->code],
            'lecturer_id' => ['lecturer', fn (int $id): ?string => Lecturer::find($id)?->name],
            'hall_id' => ['hall', fn (int $id): ?string => Hall::find($id)?->name],
        ];

        foreach ($resolvers as $key => [$label, $resolve]) {
            if (! $filters[$key]) {
                continue;
            }

            $value = $resolve($filters[$key]);

            if (filled($value)) {
                $labels[__('weekly-schedule-reports.filters.'.$label)] = (string) $value;
            }
        }

        if ($filters['section_type']) {
            $labels[__('weekly-schedule-reports.filters.section_type')] = Subject::subjectTypeOptions()[$filters['section_type']] ?? $filters['section_type'];
        }

        if ($filters['weekday']) {
            $labels[__('weekly-schedule-reports.filters.weekday')] = $this->weekdayLabel($filters['weekday']);
        }

        return $labels;
    }

    /** @return array<string, int|string|null> */
    public function normalizeFilters(array $filters): array
    {
        $integerKeys = [
            'academic_term_id', 'import_batch_id', 'faculty_id', 'department_id', 'subject_id',
            'subject_section_id', 'lecturer_id', 'hall_id', 'weekday',
        ];
        $normalized = [];

        foreach ($integerKeys as $key) {
            $value = $filters[$key] ?? null;
            $normalized[$key] = filled($value) && is_numeric($value) ? (int) $value : null;
        }

        $sectionType = $filters['section_type'] ?? null;
        $normalized['section_type'] = in_array($sectionType, [Subject::TYPE_THEORETICAL, Subject::TYPE_PRACTICAL], true)
            ? $sectionType
            : null;

        return $normalized;
    }

    public function weekdayLabel(int $weekday): string
    {
        return __('weekly-schedule.weekdays')[$weekday] ?? (string) $weekday;
    }

    public function formatTime(?string $time): string
    {
        return substr((string) $time, 0, 5);
    }

    /** @return array<int, mixed> */
    private function mapSlot(SubjectSectionScheduleSlot $slot, string $type): array
    {
        $subject = $slot->subject instanceof Subject ? $slot->subject : null;
        $section = $slot->subjectSection instanceof SubjectSection ? $slot->subjectSection : null;
        $term = $slot->academicTerm instanceof AcademicTerm ? $slot->academicTerm : null;
        $department = $subject?->department instanceof Department ? $subject->department : null;
        $faculty = $department?->faculty instanceof Faculty ? $department->faculty : null;
        $lecturer = $slot->lecturer?->name ?: $slot->raw_teacher_name ?: __('weekly-schedule.not_specified');
        $hall = $slot->hall?->name ?: $slot->raw_hall_name ?: __('weekly-schedule.not_specified');
        $weekday = $this->weekdayLabel($slot->weekday);
        $start = $this->formatTime($slot->start_time);
        $end = $this->formatTime($slot->end_time);

        return match ($type) {
            self::BY_LECTURER => [$lecturer, $subject?->name, $section?->code, $weekday, "{$start} - {$end}", $hall, $term?->display_name],
            self::BY_HALL => [$hall, $weekday, "{$start} - {$end}", $subject?->name, $section?->code, $lecturer, $slot->expected_student_count],
            self::BY_SUBJECT => [$subject?->code, $subject?->name, $section?->code, $lecturer, $hall, $weekday, $start, $end, $slot->expected_student_count],
            self::BY_WEEKDAY => [$weekday, $subject?->name, $section?->code, $lecturer, $hall, $start, $end, $term?->display_name],
            default => [
                $term?->display_name,
                $faculty?->name,
                $department?->name,
                $subject?->code,
                $subject?->name,
                $section?->code,
                Subject::subjectTypeOptions()[$section?->section_type] ?? $section?->section_type,
                $lecturer,
                $hall,
                $weekday,
                $start,
                $end,
                $slot->section_capacity,
                $slot->expected_student_count,
            ],
        };
    }

    /** @return array<int, string>|null */
    private function allowedSubjectKeys(array $filters): ?array
    {
        if (! $filters['faculty_id'] && ! $filters['department_id'] && ! $filters['subject_id']) {
            return null;
        }

        return Subject::query()
            ->withoutTrashed()
            ->when($filters['subject_id'], fn (Builder $query, int $value): Builder => $query->whereKey($value))
            ->when($filters['department_id'], fn (Builder $query, int $value): Builder => $query->where('department_id', $value))
            ->when($filters['faculty_id'], fn (Builder $query, int $value): Builder => $query->whereHas(
                'department',
                fn (Builder $department): Builder => $department->where('faculty_id', $value),
            ))
            ->pluck('code')
            ->map(fn (string $code): string => $this->normalizer->normalizeKey($code))
            ->values()
            ->all();
    }

    /** @param Collection<int, ScheduleImportRow> $rows */
    private function countRowsWithIssueTypes(Collection $rows, array $types): int
    {
        return $rows->filter(fn (ScheduleImportRow $row): bool => $row->issues->contains(
            fn (ScheduleImportIssue $issue): bool => in_array($issue->issue_type, $types, true),
        ))->count();
    }
}
