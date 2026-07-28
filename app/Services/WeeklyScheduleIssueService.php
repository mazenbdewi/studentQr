<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Hall;
use App\Models\Lecturer;
use App\Models\ScheduleImportRow;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WeeklyScheduleIssueService
{
    public function __construct(private readonly LectureSessionGenerationService $generation) {}

    public function result(AcademicTerm $term, ?int $importBatchId = null): WeeklyScheduleIssueResult
    {
        $preview = $this->generation->preview($term);
        $reasonsBySlot = [];

        foreach ($preview['blocked_slots'] as $blocked) {
            $slotId = (int) ($blocked['source_slot_id'] ?? 0);
            if ($slotId > 0) {
                $reasonsBySlot[$slotId] = array_values(array_unique([
                    ...($reasonsBySlot[$slotId] ?? []),
                    ...($blocked['reasons'] ?? []),
                ]));
            }
        }

        foreach ($preview['conflicts'] as $conflict) {
            foreach (['source_slot_id', 'conflicting_source_slot_id'] as $key) {
                $slotId = (int) ($conflict[$key] ?? 0);
                if ($slotId > 0) {
                    $reasonsBySlot[$slotId] = array_values(array_unique([
                        ...($reasonsBySlot[$slotId] ?? []),
                        (string) ($conflict['reason'] ?? 'scheduling_conflict'),
                    ]));
                }
            }
        }

        /** @var Collection<int, SubjectSectionScheduleSlot> $slots */
        $slots = SubjectSectionScheduleSlot::query()
            ->with(['subject.department.faculty', 'subjectSection', 'lecturer', 'hall', 'importBatch'])
            ->where('academic_term_id', $term->id)
            ->when($importBatchId, fn ($query) => $query->where('import_batch_id', $importBatchId))
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get();

        /** @var array<int, ScheduleImportRow> $sourceRows */
        $sourceRows = [];
        foreach (ScheduleImportRow::query()
            ->with(['resolutionUpdater', 'excludedFromWeeklyScheduleBy'])
            ->where('academic_term_id', $term->id)
            ->when($importBatchId, fn ($query) => $query->where('import_batch_id', $importBatchId))
            ->get() as $sourceRow) {
            foreach ($sourceRow->relatedScheduleSlotIds() as $slotId) {
                $sourceRows[$slotId] = $sourceRow;
            }
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = [];
        foreach ($slots as $slot) {
            $reasons = $reasonsBySlot[$slot->id] ?? [];
            $sourceRow = $sourceRows[$slot->id] ?? null;
            $excluded = $sourceRow instanceof ScheduleImportRow && $sourceRow->isExcludedFromWeeklySchedule();
            $subject = $slot->getRelation('subject');
            $section = $slot->getRelation('subjectSection');
            $lecturer = $slot->getRelation('lecturer');
            $hall = $slot->getRelation('hall');
            $department = $subject instanceof Subject ? $subject->getRelation('department') : null;
            $faculty = $department instanceof Department ? $department->getRelation('faculty') : null;
            $updater = $sourceRow instanceof ScheduleImportRow ? $sourceRow->getRelation('resolutionUpdater') : null;
            $exclusionUpdater = $sourceRow instanceof ScheduleImportRow ? $sourceRow->getRelation('excludedFromWeeklyScheduleBy') : null;

            $rows[] = [
                'slot_id' => $slot->id,
                'import_batch_id' => $slot->import_batch_id,
                'academic_term_id' => $slot->academic_term_id,
                'faculty' => $faculty instanceof Faculty ? $faculty->name : '—',
                'department' => $department instanceof Department ? $department->name : '—',
                'subject_code' => $subject instanceof Subject ? $subject->code : '—',
                'subject' => $subject instanceof Subject ? $subject->name : '—',
                'section' => $section instanceof SubjectSection ? $section->code : '—',
                'section_type' => $section instanceof SubjectSection ? $section->section_type : '—',
                'weekday' => __('weekly-schedule.weekdays')[$slot->weekday] ?? (string) $slot->weekday,
                'weekday_value' => $slot->weekday,
                'start_time' => substr((string) $slot->start_time, 0, 5),
                'end_time' => substr((string) $slot->end_time, 0, 5),
                'time' => substr((string) $slot->start_time, 0, 5).' - '.substr((string) $slot->end_time, 0, 5),
                'lecturer_id' => $slot->lecturer_id,
                'lecturer' => $lecturer instanceof Lecturer ? $lecturer->name : '—',
                'hall_id' => $slot->hall_id,
                'hall' => $hall instanceof Hall ? $hall->displayLabel() : '—',
                'reasons' => $reasons,
                'status' => $excluded ? 'excluded' : ($reasons === [] ? 'resolved' : 'needs_attention'),
                'exclusion_note' => $sourceRow?->exclusion_note,
                'resolved_at' => $sourceRow?->resolution_updated_at?->toDateTimeString(),
                'updated_by' => $updater instanceof User ? $updater->name : ($exclusionUpdater instanceof User ? $exclusionUpdater->name : '—'),
            ];
        }

        $reasonCounts = collect($rows)
            ->reject(fn (array $row): bool => $row['status'] === 'excluded')
            ->flatMap(fn (array $row): array => $row['reasons'])
            ->countBy()
            ->map(fn (int $count): int => $count)
            ->all();

        $affected = collect($rows)->filter(fn (array $row): bool => $row['status'] === 'needs_attention');

        return new WeeklyScheduleIssueResult(
            academicTermId: $term->id,
            importBatchId: $importBatchId,
            preview: $preview,
            issues: $rows,
            uniqueAffectedSlots: $affected->count(),
            issueCountsByKey: $reasonCounts,
            readySlots: collect($rows)->where('status', 'resolved')->count(),
            excludedSlots: collect($rows)->where('status', 'excluded')->count(),
        );
    }

    /** @return array<string, mixed> */
    public function forTerm(AcademicTerm $term, ?int $importBatchId = null): array
    {
        return $this->result($term, $importBatchId)->toArray();
    }

    /**
     * Keep the expensive consistency calculation on the server, while exposing
     * only the requested page of simple rows to a Livewire render.
     *
     * @param  array<string, string|int|null>  $filters
     * @return array{summary: array<string, mixed>, rows: array<int, array<string, mixed>>, filters: array<string, array<int|string, mixed>>, pagination: array<string, int>}
     */
    public function page(AcademicTerm $term, ?int $importBatchId, array $filters, int $page = 1, int $perPage = 50): array
    {
        $startedAt = hrtime(true);
        $queryLogWasEnabled = app()->hasDebugModeEnabled() && DB::logging();
        $queryLogStart = $queryLogWasEnabled ? count(DB::getQueryLog()) : 0;
        if (app()->hasDebugModeEnabled() && ! $queryLogWasEnabled) {
            DB::flushQueryLog();
            DB::enableQueryLog();
        }

        $result = $this->result($term, $importBatchId);
        $allRows = collect($result->issues);
        $values = fn (string $key): array => $allRows->pluck($key)->filter(fn ($value) => filled($value) && $value !== '—')->unique()->sort()->values()->all();
        $rows = $allRows
            ->when(($filters['status'] ?? null) && $filters['status'] !== 'all', fn (Collection $rows) => $rows->where('status', $filters['status']))
            ->when($filters['reason'] ?? null, fn (Collection $rows) => $rows->filter(fn (array $row): bool => in_array($filters['reason'], $row['reasons'], true)))
            ->when($filters['faculty'] ?? null, fn (Collection $rows) => $rows->where('faculty', $filters['faculty']))
            ->when($filters['department'] ?? null, fn (Collection $rows) => $rows->where('department', $filters['department']))
            ->when($filters['subject'] ?? null, fn (Collection $rows) => $rows->where('subject', $filters['subject']))
            ->when($filters['section'] ?? null, fn (Collection $rows) => $rows->where('section', $filters['section']))
            ->when($filters['lecturer'] ?? null, fn (Collection $rows) => $rows->where('lecturer', $filters['lecturer']))
            ->when($filters['hall'] ?? null, fn (Collection $rows) => $rows->where('hall', $filters['hall']))
            ->when($filters['weekday'] ?? null, fn (Collection $rows) => $rows->where('weekday_value', (int) $filters['weekday']))
            ->values();

        $perPage = max(1, min(50, $perPage));
        $lastPage = max(1, (int) ceil($rows->count() / $perPage));
        $page = min(max(1, $page), $lastPage);
        $pageRows = $rows->forPage($page, $perPage)->map(fn (array $row): array => [
            'slot_id' => $row['slot_id'], 'subject_code' => $row['subject_code'], 'subject' => $row['subject'],
            'section' => $row['section'], 'weekday' => $row['weekday'], 'time' => $row['time'],
            'lecturer' => $row['lecturer'], 'hall' => $row['hall'], 'reasons' => $row['reasons'], 'status' => $row['status'],
        ])->values()->all();

        if (app()->hasDebugModeEnabled()) {
            $sqlCount = count(DB::getQueryLog()) - $queryLogStart;
            Log::debug('schedule-import-issues timing', [
                'stage' => 'weekly_issue_service_finished',
                'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
                'slot_count' => $allRows->count(), 'issue_count' => $result->uniqueAffectedSlots,
                'displayed_rows' => count($pageRows), 'sql_queries' => $sqlCount,
            ]);
            if (! $queryLogWasEnabled) {
                DB::flushQueryLog();
                DB::disableQueryLog();
            }
        }

        $response = [
            'summary' => [
                'unique_affected_slots' => $result->uniqueAffectedSlots,
                'issue_counts_by_key' => $result->issueCountsByKey,
                'ready_slots' => $result->readySlots,
                'excluded_slots' => $result->excludedSlots,
            ],
            'rows' => $pageRows,
            'filters' => [
                'reasons' => collect($result->issueCountsByKey)->keys()->mapWithKeys(fn (string $reason): array => [$reason => __('lecture-session.lecture_generation.reasons.'.$reason)])->all(),
                'faculties' => $values('faculty'), 'departments' => $values('department'), 'subjects' => $values('subject'),
                'sections' => $values('section'), 'lecturers' => $values('lecturer'), 'halls' => $values('hall'),
                'weekdays' => $allRows->pluck('weekday_value')->unique()->sort()->mapWithKeys(fn (int $day): array => [(string) $day => __('weekly-schedule.weekdays')[$day] ?? (string) $day])->all(),
            ],
            'pagination' => ['current_page' => $page, 'last_page' => $lastPage, 'total' => $rows->count(), 'per_page' => $perPage],
        ];

        if (app()->hasDebugModeEnabled()) {
            Log::debug('schedule-import-issues timing', [
                'stage' => 'response_page_built',
                'response_json_bytes' => strlen((string) json_encode($response)),
                'displayed_rows' => count($pageRows),
            ]);
        }

        return $response;
    }
}
