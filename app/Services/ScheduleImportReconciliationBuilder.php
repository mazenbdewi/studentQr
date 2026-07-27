<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\Lecturer;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportRow;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Support\WeeklyScheduleRowNormalizer;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class ScheduleImportReconciliationBuilder
{
    public function __construct(
        private readonly WeeklyScheduleRowNormalizer $normalizer,
        private readonly ScheduleImportSuggestionService $suggestions,
    ) {}

    public function build(
        ImportBatch $batch,
        string $workbookPath,
        ?string $originalFilename = null,
        ?string $retainedFilePath = null,
    ): array {
        [$sourceBatch, $academicTerm] = $this->verify($batch, $workbookPath);
        [$sheetName, $normalizedRows] = $this->readRows($workbookPath);
        $analysis = $this->analyze($batch, $academicTerm, $normalizedRows);
        $slotCountBefore = SubjectSectionScheduleSlot::query()->where('import_batch_id', $batch->id)->count();

        $result = DB::transaction(function () use (
            $batch,
            $sourceBatch,
            $academicTerm,
            $sheetName,
            $analysis,
            $originalFilename,
            $retainedFilePath,
            $slotCountBefore,
        ): array {
            $createdRows = 0;
            $reusedRows = 0;
            $createdIssues = 0;
            $reusedIssues = 0;

            foreach ($analysis as $item) {
                $row = ScheduleImportRow::query()->firstOrCreate(
                    [
                        'import_batch_id' => $batch->id,
                        'source_sheet_name' => $sheetName,
                        'source_row_number' => $item['normalized']['row_number'],
                    ],
                    [
                        'academic_term_id' => $academicTerm->id,
                        'row_fingerprint' => $this->fingerprint($item['source']),
                        'source_payload' => $item['source'],
                        'normalized_payload' => $item['normalized'],
                        'original_import_status' => $item['original_status'],
                        'current_reconciliation_status' => $item['current_status'],
                        'import_result' => $item['import_result'],
                    ],
                );

                $row->wasRecentlyCreated ? $createdRows++ : $reusedRows++;

                foreach ($item['issues'] as $position => $issue) {
                    $issueRecord = ScheduleImportIssue::query()->firstOrCreate(
                        ['deduplication_key' => hash('sha256', implode('|', [
                            $batch->id,
                            $sheetName,
                            $item['normalized']['row_number'],
                            $issue['type'],
                            $issue['context'] ?? $position,
                        ]))],
                        [
                            'schedule_import_row_id' => $row->id,
                            'issue_type' => $issue['type'],
                            'severity' => $issue['severity'],
                            'reason_ar' => $issue['reason'],
                            'suggested_matches' => $issue['suggestions'] ?? null,
                            'resolution_status' => ScheduleImportIssue::STATUS_UNRESOLVED,
                        ],
                    );

                    $issueRecord->wasRecentlyCreated ? $createdIssues++ : $reusedIssues++;
                }
            }

            $filenameCorrection = null;

            if (filled($originalFilename) && basename((string) $originalFilename) !== $batch->source_filename) {
                $filenameCorrection = [
                    'previous' => $batch->source_filename,
                    'current' => basename((string) $originalFilename),
                ];
                $batch->source_filename = basename((string) $originalFilename);
            }

            if (filled($retainedFilePath) && blank($batch->source_file_path)) {
                $batch->source_file_path = $retainedFilePath;
            }

            $counts = $this->reconciliationCounts($batch->id);
            $summary = $batch->summary ?? [];
            $summary['reconciliation'] = [
                ...$counts,
                'built_at' => now()->toISOString(),
                'source_enrollment_batch' => $sourceBatch->uuid,
                'academic_term' => $academicTerm->display_name,
                'filename_correction' => $filenameCorrection,
            ];
            $batch->summary = $summary;
            $batch->save();

            $slotCountAfter = SubjectSectionScheduleSlot::query()->where('import_batch_id', $batch->id)->count();

            if ($slotCountAfter !== $slotCountBefore) {
                throw new RuntimeException('تغير عدد المواعيد الأسبوعية أثناء بناء تقرير المراجعة؛ تم إلغاء العملية.');
            }

            return [
                'parsed_rows' => count($analysis),
                'created_rows' => $createdRows,
                'reused_rows' => $reusedRows,
                'created_issues' => $createdIssues,
                'reused_issues' => $reusedIssues,
                ...$counts,
                'slot_count_before' => $slotCountBefore,
                'slot_count_after' => $slotCountAfter,
                'filename_correction' => $filenameCorrection,
            ];
        });

        return $result;
    }

    /** @return array{0: ImportBatch, 1: AcademicTerm} */
    private function verify(ImportBatch $batch, string $workbookPath): array
    {
        if ($batch->import_type !== ImportBatch::TYPE_WEEKLY_SCHEDULE || ! in_array($batch->status, [
            ImportBatch::STATUS_COMPLETED,
            ImportBatch::STATUS_COMPLETED_WITH_ERRORS,
        ], true)) {
            throw new RuntimeException('دفعة الجدول غير مؤهلة لبناء تقرير المراجعة.');
        }

        if (! is_file($workbookPath)) {
            throw new RuntimeException('ملف برنامج الدوام المحتفظ به غير موجود.');
        }

        $fingerprint = hash_file('sha256', $workbookPath);

        if (! is_string($fingerprint) || ! hash_equals((string) $batch->source_fingerprint, $fingerprint)) {
            throw new RuntimeException('بصمة ملف برنامج الدوام لا تطابق بصمة دفعة الاستيراد.');
        }

        $batch->loadMissing(['sourceImportBatch.academicTerms', 'academicTerms']);
        $sourceBatch = $batch->sourceImportBatch;

        if (! $sourceBatch?->isEligibleEnrollmentSource()) {
            throw new RuntimeException('دفعة تسجيل الطلاب المصدر غير موجودة أو غير مكتملة.');
        }

        if ($batch->academicTerms->count() !== 1 || $sourceBatch->academicTerms->count() !== 1) {
            throw new RuntimeException('يجب أن ترتبط دفعة الجدول ودفعة التسجيل بفصل دراسي واحد.');
        }

        $academicTerm = $batch->academicTerms->sole();

        if ($sourceBatch->academicTerms->sole()->id !== $academicTerm->id) {
            throw new RuntimeException('الفصل الدراسي لدفعة الجدول لا يطابق دفعة تسجيل الطلاب المصدر.');
        }

        return [$sourceBatch, $academicTerm];
    }

    /** @return array{0: string, 1: array<int, array>} */
    private function readRows(string $workbookPath): array
    {
        $worksheet = IOFactory::load($workbookPath)->getActiveSheet();
        $rawRows = $worksheet->toArray(null, true, true, false);
        $headings = array_shift($rawRows) ?? [];
        $missing = $this->normalizer->captureHeadings($headings);

        if ($missing !== []) {
            throw new RuntimeException('أعمدة مطلوبة مفقودة: '.implode('، ', $missing));
        }

        $rows = [];

        foreach ($rawRows as $offset => $values) {
            $mapped = $this->normalizer->mapRow($values, $offset + 2);

            if (! $this->normalizer->rowIsEmpty($mapped)) {
                $rows[] = $this->normalizer->normalizeRow($mapped);
            }
        }

        return [$worksheet->getTitle() ?: 'Worksheet', $rows];
    }

    /** @return array<int, array> */
    private function analyze(ImportBatch $batch, AcademicTerm $term, array $rows): array
    {
        $subjectsByCode = [];
        Subject::query()->withoutTrashed()->get(['id', 'code', 'name'])->each(function (Subject $subject) use (&$subjectsByCode): void {
            $subjectsByCode[$this->normalizer->normalizeKey($subject->code)][] = $subject;
        });
        $sections = SubjectSection::query()
            ->where('academic_term_id', $term->id)
            ->get(['id', 'subject_id', 'academic_term_id', 'code', 'section_type'])
            ->keyBy(fn (SubjectSection $section): string => $section->subject_id.'|'.SubjectSection::normalizeCode($section->code));
        $lecturerCounts = Lecturer::query()->get(['id', 'name', 'canonical_name'])
            ->countBy(fn (Lecturer $lecturer): string => $this->normalizer->normalizeKey($lecturer->canonical_name ?: $lecturer->name));
        $hallIdsByKey = [];
        Hall::query()->withoutTrashed()->get(['id', 'code', 'name'])->each(function (Hall $hall) use (&$hallIdsByKey): void {
            foreach (array_unique(array_filter([
                $this->normalizer->normalizeKey($hall->code),
                $this->normalizer->normalizeKey($hall->name),
            ])) as $key) {
                $hallIdsByKey[$key][$hall->id] = true;
            }
        });

        $analysis = [];
        $candidateGroups = [];

        foreach ($rows as $row) {
            $issues = [];
            $candidates = [];
            $core = $this->normalizer->validateCore($row);

            foreach ($core as $index => $reason) {
                $issues[] = $this->issue(ScheduleImportIssue::TYPE_CORE_VALIDATION, ScheduleImportIssue::SEVERITY_ERROR, $reason, "core:{$index}");
            }

            $subject = null;

            if ($core === []) {
                $matches = $subjectsByCode[$row['subject_code_key']] ?? [];
                $suggestions = $this->suggestions->suggest($row, $term->id);

                if (count($matches) === 0) {
                    $isBracketAlias = collect($suggestions)->contains(fn (array $suggestion): bool => in_array('outer_brackets_removed', $suggestion['match_reasons'], true));
                    $isZero = ($row['expected_student_count'] ?? null) === 0;
                    $type = $isBracketAlias
                        ? ScheduleImportIssue::TYPE_NON_AUTHORITATIVE_SUBJECT_CODE
                        : ($isZero ? ScheduleImportIssue::TYPE_ZERO_STUDENT_SUBJECT_MISSING : ScheduleImportIssue::TYPE_SUBJECT_NOT_FOUND);
                    $severity = $isZero ? ScheduleImportIssue::SEVERITY_WARNING : ScheduleImportIssue::SEVERITY_ERROR;
                    $issues[] = $this->issue($type, $severity, 'المقرر غير موجود أو رمز المقرر غير معتمد في النظام.', 'subject', $suggestions);
                } elseif (count($matches) > 1) {
                    $issues[] = $this->issue(ScheduleImportIssue::TYPE_SUBJECT_NOT_UNIQUE, ScheduleImportIssue::SEVERITY_ERROR, 'رمز المقرر يطابق أكثر من مقرر.', 'subject', $suggestions);
                } else {
                    $subject = reset($matches);
                }
            }

            $section = null;

            if ($subject instanceof Subject) {
                $section = $sections->get($subject->id.'|'.SubjectSection::normalizeCode($row['section_code']));

                if (! $section) {
                    $isZero = ($row['expected_student_count'] ?? null) === 0;
                    $issues[] = $this->issue(
                        $isZero ? ScheduleImportIssue::TYPE_ZERO_STUDENT_SECTION_MISSING : ScheduleImportIssue::TYPE_SECTION_NOT_FOUND,
                        $isZero ? ScheduleImportIssue::SEVERITY_WARNING : ScheduleImportIssue::SEVERITY_ERROR,
                        'الشعبة غير موجودة للمقرر ضمن الفصل الدراسي المرتبط.',
                        'section',
                        $this->suggestions->suggest($row, $term->id),
                    );
                }
            }

            if (($row['teacher_name_key'] ?? '') === '') {
                $issues[] = $this->issue(ScheduleImportIssue::TYPE_LECTURER_MISSING, ScheduleImportIssue::SEVERITY_WARNING, 'اسم المدرس مفقود.', 'lecturer');
            } elseif (($lecturerCounts[$row['teacher_name_key']] ?? 0) > 1) {
                $issues[] = $this->issue(ScheduleImportIssue::TYPE_LECTURER_AMBIGUOUS, ScheduleImportIssue::SEVERITY_ERROR, 'اسم المدرس يطابق أكثر من هوية.', 'lecturer');
            }

            if (($row['hall_name_key'] ?? '') === '') {
                $issues[] = $this->issue(ScheduleImportIssue::TYPE_HALL_MISSING, ScheduleImportIssue::SEVERITY_WARNING, 'اسم القاعة مفقود.', 'hall');
            } elseif (count($hallIdsByKey[$row['hall_name_key']] ?? []) > 1) {
                $issues[] = $this->issue(ScheduleImportIssue::TYPE_HALL_AMBIGUOUS, ScheduleImportIssue::SEVERITY_WARNING, 'اسم القاعة يطابق أكثر من قاعة.', 'hall');
            }

            if ($section instanceof SubjectSection) {
                foreach ($row['weekday_values'] as $weekday => $sourceTime) {
                    if ($this->normalizer->isMissingValue($sourceTime)) {
                        continue;
                    }

                    try {
                        $time = $this->normalizer->parseTimeRange($sourceTime);
                    } catch (\InvalidArgumentException $exception) {
                        $issues[] = $this->issue(ScheduleImportIssue::TYPE_INVALID_WEEKDAY_TIME, ScheduleImportIssue::SEVERITY_ERROR, $exception->getMessage(), "weekday:{$weekday}");

                        continue;
                    }

                    if ($time) {
                        $candidate = [
                            'subject_id' => $subject->id,
                            'subject_section_id' => $section->id,
                            'weekday' => (int) $weekday,
                            ...$time,
                        ];
                        $candidates[] = $candidate;
                        $key = implode('|', [$term->id, $section->id, $weekday, $time['start_time'], $time['end_time']]);
                        $candidateGroups[$key][] = [
                            'row_number' => $row['row_number'],
                            'metadata' => $this->metadataSignature($row),
                        ];
                    }
                }

                if ($candidates === []) {
                    $issues[] = $this->issue(ScheduleImportIssue::TYPE_NO_WEEKLY_TIME, ScheduleImportIssue::SEVERITY_WARNING, 'لا يحتوي السطر على موعد أسبوعي صالح.', 'weekly-time');
                }
            }

            $slotIds = collect($candidates)->map(function (array $candidate) use ($term): ?int {
                return SubjectSectionScheduleSlot::query()->where([
                    'academic_term_id' => $term->id,
                    'subject_section_id' => $candidate['subject_section_id'],
                    'weekday' => $candidate['weekday'],
                    'start_time' => $candidate['start_time'],
                    'end_time' => $candidate['end_time'],
                ])->value('id');
            })->filter()->values()->all();

            $analysis[$row['row_number']] = [
                'source' => $this->sourcePayload($row),
                'normalized' => $row,
                'issues' => $issues,
                'candidates' => $candidates,
                'import_result' => ['slot_ids' => $slotIds],
            ];
        }

        foreach ($candidateGroups as $key => $group) {
            if (count($group) < 2 || count(array_unique(array_column($group, 'metadata'))) === 1) {
                continue;
            }

            foreach ($group as $candidate) {
                $analysis[$candidate['row_number']]['issues'][] = $this->issue(
                    ScheduleImportIssue::TYPE_DUPLICATE_CONFLICT,
                    ScheduleImportIssue::SEVERITY_ERROR,
                    'صفوف مكررة لنفس الموعد تحتوي بيانات متعارضة.',
                    "duplicate:{$key}",
                );
            }
        }

        foreach ($analysis as &$item) {
            $hasError = collect($item['issues'])->contains('severity', ScheduleImportIssue::SEVERITY_ERROR);
            $hasWarning = collect($item['issues'])->contains('severity', ScheduleImportIssue::SEVERITY_WARNING);
            $hasSlots = ($item['import_result']['slot_ids'] ?? []) !== [];
            $noTime = collect($item['issues'])->contains('type', ScheduleImportIssue::TYPE_NO_WEEKLY_TIME);

            $item['original_status'] = $hasSlots && $hasError
                ? ScheduleImportRow::ORIGINAL_PARTIALLY_IMPORTED
                : ($hasError
                    ? ScheduleImportRow::ORIGINAL_REJECTED
                    : ($noTime
                        ? ScheduleImportRow::ORIGINAL_UNSCHEDULED
                        : ($hasWarning ? ScheduleImportRow::ORIGINAL_WARNING_ONLY : ScheduleImportRow::ORIGINAL_IMPORTED)));
            $item['current_status'] = ($hasError || $hasWarning)
                ? ScheduleImportRow::STATUS_UNRESOLVED
                : ScheduleImportRow::STATUS_RESOLVED;
        }
        unset($item);

        return array_values($analysis);
    }

    private function issue(string $type, string $severity, string $reason, string $context, ?array $suggestions = null): array
    {
        return compact('type', 'severity', 'reason', 'context', 'suggestions');
    }

    private function sourcePayload(array $row): array
    {
        return [
            'subject_code' => $row['subject_code_source'] ?? null,
            'subject_name' => $row['subject_name_source'] ?? null,
            'section_type' => $row['section_type_source'] ?? null,
            'section_number' => $row['section_number_source'] ?? null,
            'expected_student_count' => $row['expected_student_count'] ?? null,
            'teacher_name' => $row['teacher_name_source'] ?? null,
            'hall_name' => $row['hall_name_source'] ?? null,
            'weekday_values' => $row['weekday_values'] ?? [],
            'subject_faculty' => $row['subject_faculty_source'] ?? null,
            'restricted_faculties' => $row['restricted_faculties_source'] ?? null,
            'restricted_departments' => $row['restricted_departments_source'] ?? null,
        ];
    }

    private function metadataSignature(array $row): string
    {
        return hash('sha256', json_encode([
            $row['teacher_name_key'] ?? '',
            $row['hall_name_key'] ?? '',
            $row['section_capacity'] ?? null,
            $row['expected_student_count'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function fingerprint(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function reconciliationCounts(int $batchId): array
    {
        $rows = ScheduleImportRow::query()->where('import_batch_id', $batchId);
        $issues = ScheduleImportIssue::query()->whereHas('importRow', fn ($query) => $query->where('import_batch_id', $batchId));

        return [
            'errors' => (clone $issues)->where('severity', ScheduleImportIssue::SEVERITY_ERROR)->count(),
            'warnings' => (clone $issues)->where('severity', ScheduleImportIssue::SEVERITY_WARNING)->count(),
            'successful_rows' => (clone $rows)->where('original_import_status', ScheduleImportRow::ORIGINAL_IMPORTED)->count(),
            'unscheduled_rows' => (clone $rows)->where('original_import_status', ScheduleImportRow::ORIGINAL_UNSCHEDULED)->count(),
            'unresolved_errors' => (clone $issues)->where('severity', ScheduleImportIssue::SEVERITY_ERROR)->whereIn('resolution_status', [ScheduleImportIssue::STATUS_UNRESOLVED, ScheduleImportIssue::STATUS_RETRY_FAILED])->count(),
            'resolved_errors' => (clone $issues)->where('severity', ScheduleImportIssue::SEVERITY_ERROR)->where('resolution_status', ScheduleImportIssue::STATUS_RESOLVED)->count(),
            'ignored_rows' => (clone $rows)->where('current_reconciliation_status', ScheduleImportRow::STATUS_IGNORED)->count(),
            'intentionally_unscheduled_rows' => (clone $rows)->where('current_reconciliation_status', ScheduleImportRow::STATUS_INTENTIONALLY_UNSCHEDULED)->count(),
            'excluded_from_batch_schedule_rows' => (clone $rows)->where('current_reconciliation_status', ScheduleImportRow::STATUS_EXCLUDED_FROM_BATCH_SCHEDULE)->count(),
            'newly_created_slots' => 0,
            'remaining_warnings' => (clone $issues)->where('severity', ScheduleImportIssue::SEVERITY_WARNING)->where('resolution_status', ScheduleImportIssue::STATUS_UNRESOLVED)->count(),
        ];
    }
}
