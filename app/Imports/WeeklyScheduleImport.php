<?php

namespace App\Imports;

use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\Lecturer;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Services\ScheduleAcademicTermResolver;
use App\Services\SubjectSectionLecturerSynchronizationService;
use App\Support\WeeklyScheduleRowNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

class WeeklyScheduleImport
{
    private array $summary = [];

    private array $errors = [];

    private ?ImportBatch $batch = null;

    /** @var array<string, array<int, Subject>> */
    private array $subjects = [];

    /** @var array<string, SubjectSection> */
    private array $sections = [];

    /** @var array<string, array<int, Lecturer>> */
    private array $lecturers = [];

    /** @var array<string, array<int, User>> */
    private array $lecturerUsers = [];

    /** @var array<string, array<int, Hall>> */
    private array $halls = [];

    private array $createdLecturerIds = [];

    private array $reusedLecturerIds = [];

    private array $createdHallIds = [];

    private array $reusedHallIds = [];

    public function __construct(
        private readonly WeeklyScheduleRowNormalizer $normalizer,
        private readonly ScheduleAcademicTermResolver $termResolver,
        private readonly SubjectSectionLecturerSynchronizationService $sectionLecturerSynchronization,
    ) {}

    public function import(
        string $path,
        string $sourceFilename,
        ?string $sourceBatchUuid = null,
        int|string|null $createdBy = null,
        ?string $sourceFingerprint = null,
        ?string $sourceFilePath = null,
    ): void {
        $this->resetState();
        $rows = $this->readRows($path);
        $normalizedRows = [];
        $scheduleKeys = [];

        foreach ($rows as $row) {
            $normalized = $this->normalizer->normalizeRow($row);
            $normalizedRows[] = $normalized;

            if ($this->normalizer->validateCore($normalized) === []) {
                $scheduleKeys[] = [
                    'subject_code_key' => $normalized['subject_code_key'],
                    'section_code' => $normalized['section_code'],
                ];
            }
        }

        [$sourceBatch, $academicTerm] = $this->termResolver->resolve($scheduleKeys, $sourceBatchUuid);
        $sourceFingerprint ??= hash_file('sha256', $path) ?: null;

        if (! is_string($sourceFingerprint) || $sourceFingerprint === '') {
            throw new RuntimeException('تعذر حساب بصمة ملف الجدول المرفوع.');
        }

        $deduplicationKey = ImportBatch::deduplicationKey(
            ImportBatch::TYPE_WEEKLY_SCHEDULE,
            $sourceFingerprint,
            $sourceBatch->id,
        );
        $this->batch = ImportBatch::query()->where('deduplication_key', $deduplicationKey)->first();

        if ($this->batch && in_array($this->batch->status, [
            ImportBatch::STATUS_COMPLETED,
            ImportBatch::STATUS_COMPLETED_WITH_ERRORS,
        ], true)) {
            $this->summary = [
                ...($this->batch->summary ?? []),
                'already_imported' => true,
            ];

            return;
        }

        $this->batch ??= new ImportBatch(['deduplication_key' => $deduplicationKey]);
        $this->batch->fill([
            'import_type' => ImportBatch::TYPE_WEEKLY_SCHEDULE,
            'source_filename' => basename($sourceFilename),
            'source_file_path' => $sourceFilePath,
            'source_fingerprint' => $sourceFingerprint,
            'source_import_batch_id' => $sourceBatch->id,
            'status' => ImportBatch::STATUS_PROCESSING,
            'total_rows' => count($normalizedRows),
            'imported_rows' => 0,
            'rejected_rows' => 0,
            'summary' => null,
            'error_file_path' => null,
            'started_at' => now(),
            'completed_at' => null,
            'created_by' => $createdBy,
        ])->save();

        $this->initializeSummary($sourceBatch, $academicTerm->display_name, count($normalizedRows));

        try {
            $this->warmCaches($academicTerm->id);
            $candidates = $this->prepareCandidates($normalizedRows, $academicTerm->id);
            $groups = $this->deduplicateCandidates($candidates);

            DB::transaction(function () use ($groups, $academicTerm): void {
                $rowsWithImportedSlots = [];

                foreach ($groups as $group) {
                    if ($group['conflicting']) {
                        continue;
                    }

                    $candidate = $group['candidate'];

                    if ($this->existingSlotMetadataConflicts($candidate, $academicTerm->id)) {
                        foreach ($group['row_numbers'] as $rowNumber) {
                            $this->addError($candidate, 'تعارضت بيانات القاعة أو المدرس أو السعة مع شعبة أسبوعية موجودة.', $rowNumber);
                        }

                        $this->summary['conflicting_duplicates']++;

                        continue;
                    }

                    $lecturer = $this->resolveLecturer($candidate);

                    if ($lecturer === false) {
                        foreach ($group['row_numbers'] as $rowNumber) {
                            $this->addError($candidate, 'اسم المدرس يطابق أكثر من هوية، لذلك لم يتم اختيار مدرس تلقائياً.', $rowNumber);
                        }

                        continue;
                    }

                    $hall = $this->resolveHall($candidate);

                    if ($hall === false) {
                        foreach ($group['row_numbers'] as $rowNumber) {
                            $this->addError($candidate, 'اسم القاعة يطابق أكثر من قاعة موجودة.', $rowNumber);
                        }

                        continue;
                    }

                    $attributes = [
                        'import_batch_id' => $this->batch->id,
                        'academic_term_id' => $academicTerm->id,
                        'subject_id' => $candidate['subject_id'],
                        'subject_section_id' => $candidate['subject_section_id'],
                        'lecturer_id' => $lecturer?->id,
                        'hall_id' => $hall?->id,
                        'weekday' => $candidate['weekday'],
                        'start_time' => $candidate['start_time'],
                        'end_time' => $candidate['end_time'],
                        'section_capacity' => $candidate['section_capacity'],
                        'expected_student_count' => $candidate['expected_student_count'],
                        'raw_teacher_name' => $candidate['teacher_name_source'],
                        'raw_hall_name' => $candidate['hall_name_source'],
                    ];
                    $slot = SubjectSectionScheduleSlot::query()->where([
                        'academic_term_id' => $academicTerm->id,
                        'subject_section_id' => $candidate['subject_section_id'],
                        'weekday' => $candidate['weekday'],
                        'start_time' => $candidate['start_time'],
                        'end_time' => $candidate['end_time'],
                    ])->first();

                    if (! $slot) {
                        $slot = SubjectSectionScheduleSlot::query()->create($attributes);
                        $this->summary['created_schedule_slots']++;
                    } else {
                        $updates = $this->nonDestructiveUpdates($slot, $attributes);

                        if ($updates !== []) {
                            $slot->update($updates);
                            $this->summary['updated_schedule_slots']++;
                        }
                    }

                    foreach ($group['row_numbers'] as $rowNumber) {
                        $rowsWithImportedSlots[$rowNumber] = true;
                    }
                }

                $this->summary['imported_rows'] = count($rowsWithImportedSlots);
                $this->summary['rejected_rows'] = max(
                    0,
                    $this->summary['total_rows'] - $this->summary['imported_rows'],
                );
                $this->summary['created_halls'] = count($this->createdHallIds);
                $this->summary['reused_halls'] = count($this->reusedHallIds);
                $this->summary['created_lecturer_identities'] = count($this->createdLecturerIds);
                $this->summary['reused_lecturer_identities'] = count($this->reusedLecturerIds);

                $status = $this->errors === []
                    ? ImportBatch::STATUS_COMPLETED
                    : ImportBatch::STATUS_COMPLETED_WITH_ERRORS;
                $this->batch->academicTerms()->sync([
                    $academicTerm->id => ['row_count' => $this->summary['imported_rows']],
                ]);
                $this->batch->update([
                    'status' => $status,
                    'imported_rows' => $this->summary['imported_rows'],
                    'rejected_rows' => $this->summary['rejected_rows'],
                    'summary' => $this->summary,
                    'completed_at' => now(),
                ]);
                $sync = $this->sectionLecturerSynchronization->synchronizeBatch($this->batch);
                $this->summary['section_lecturer_synchronization'] = [
                    'unique_lecturer_count' => $sync['unique_lecturer_count'],
                    'no_lecturer_count' => $sync['no_lecturer_count'],
                    'multiple_lecturers_count' => $sync['multiple_lecturers_count'],
                ];
                $this->batch->update(['summary' => $this->summary]);
            });
        } catch (Throwable $exception) {
            $this->batch->update([
                'status' => ImportBatch::STATUS_FAILED,
                'summary' => ['error' => $exception->getMessage()],
                'completed_at' => now(),
            ]);

            throw $exception;
        }
    }

    public function getSummary(): array
    {
        return $this->summary;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getBatch(): ?ImportBatch
    {
        return $this->batch;
    }

    private function resetState(): void
    {
        $this->summary = [];
        $this->errors = [];
        $this->batch = null;
        $this->subjects = [];
        $this->sections = [];
        $this->lecturers = [];
        $this->lecturerUsers = [];
        $this->halls = [];
        $this->createdLecturerIds = [];
        $this->reusedLecturerIds = [];
        $this->createdHallIds = [];
        $this->reusedHallIds = [];
    }

    /** @return array<int, array> */
    private function readRows(string $path): array
    {
        $worksheet = IOFactory::load($path)->getActiveSheet();
        $rawRows = $worksheet->toArray(null, true, true, false);
        $headings = array_shift($rawRows) ?? [];
        $missingHeadings = $this->normalizer->captureHeadings($headings);

        if ($missingHeadings !== []) {
            throw new RuntimeException('أعمدة مطلوبة مفقودة من ملف الجدول: '.implode('، ', $missingHeadings));
        }

        $rows = [];

        foreach ($rawRows as $offset => $values) {
            $row = $this->normalizer->mapRow($values, $offset + 2);

            if (! $this->normalizer->rowIsEmpty($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function initializeSummary(ImportBatch $sourceBatch, string $academicTerm, int $totalRows): void
    {
        $this->summary = [
            'source_enrollment_batch' => $sourceBatch->uuid,
            'source_enrollment_filename' => $sourceBatch->source_filename,
            'academic_term' => $academicTerm,
            'total_rows' => $totalRows,
            'imported_rows' => 0,
            'rejected_rows' => 0,
            'created_schedule_slots' => 0,
            'updated_schedule_slots' => 0,
            'identical_duplicates_ignored' => 0,
            'conflicting_duplicates' => 0,
            'matched_theoretical_sections' => 0,
            'matched_practical_sections' => 0,
            'created_halls' => 0,
            'reused_halls' => 0,
            'created_lecturer_identities' => 0,
            'reused_lecturer_identities' => 0,
            'missing_lecturers' => 0,
            'ambiguous_lecturers' => 0,
            'missing_halls' => 0,
            'missing_subjects' => 0,
            'missing_sections' => 0,
            'invalid_weekday_time_cells' => 0,
            'already_imported' => false,
        ];
    }

    private function warmCaches(int $academicTermId): void
    {
        Subject::query()->withoutTrashed()->get(['id', 'code'])->each(function (Subject $subject): void {
            $this->subjects[$this->normalizer->normalizeKey($subject->code)][] = $subject;
        });

        SubjectSection::query()
            ->where('academic_term_id', $academicTermId)
            ->get(['id', 'academic_term_id', 'subject_id', 'code', 'section_type'])
            ->each(function (SubjectSection $section): void {
                $this->sections[$section->subject_id.'|'.SubjectSection::normalizeCode($section->code)] = $section;
            });

        Lecturer::query()->get()->each(function (Lecturer $lecturer): void {
            $key = $this->normalizer->normalizeKey($lecturer->canonical_name ?: $lecturer->name);
            $this->lecturers[$key][] = $lecturer;
        });

        User::query()
            ->where(function ($query): void {
                $query->where('role', 'course_lecturer')
                    ->orWhereHas('roles', fn ($roles) => $roles->where('name', 'course_lecturer'));
            })
            ->get(['id', 'name'])
            ->each(function (User $user): void {
                $this->lecturerUsers[$this->normalizer->normalizeKey($user->name)][] = $user;
            });

        Hall::withTrashed()->get(['id', 'code', 'name', 'deleted_at'])->each(function (Hall $hall): void {
            foreach (array_unique([
                $this->normalizer->normalizeKey($hall->name),
                $this->normalizer->normalizeKey($hall->code),
            ]) as $key) {
                if ($key !== '') {
                    $this->halls[$key][$hall->id] = $hall;
                }
            }
        });
    }

    /** @return array<int, array> */
    private function prepareCandidates(array $rows, int $academicTermId): array
    {
        $candidates = [];
        $matchedTheory = [];
        $matchedPractical = [];

        foreach ($rows as $row) {
            $coreErrors = $this->normalizer->validateCore($row);

            if ($coreErrors !== []) {
                $this->addError($row, implode(' | ', $coreErrors));

                continue;
            }

            $subjects = $this->subjects[$row['subject_code_key']] ?? [];

            if (count($subjects) !== 1) {
                $this->summary['missing_subjects']++;
                $this->addError($row, 'المقرر غير موجود أو رمز المقرر غير فريد في النظام.');

                continue;
            }

            $subject = reset($subjects);
            $section = $this->sections[$subject->id.'|'.$row['section_code']] ?? null;

            if (! $section) {
                $this->summary['missing_sections']++;
                $this->addError($row, 'الشعبة غير موجودة للمقرر ضمن الفصل الدراسي المرتبط.');

                continue;
            }

            if ($row['section_type'] === 'P') {
                $matchedPractical[$section->id] = true;
            } else {
                $matchedTheory[$section->id] = true;
            }

            $rowCandidates = 0;

            foreach ($row['weekday_values'] as $weekday => $sourceTime) {
                if ($this->normalizer->isMissingValue($sourceTime)) {
                    continue;
                }

                try {
                    $time = $this->normalizer->parseTimeRange($sourceTime);
                } catch (\InvalidArgumentException $exception) {
                    $this->summary['invalid_weekday_time_cells']++;
                    $this->addError($row, $exception->getMessage(), null, $weekday, $sourceTime);

                    continue;
                }

                if ($time === null) {
                    continue;
                }

                $candidates[] = [
                    ...$row,
                    ...$time,
                    'academic_term_id' => $academicTermId,
                    'subject_id' => $subject->id,
                    'subject_section_id' => $section->id,
                    'section' => $section,
                    'weekday' => $weekday,
                    'weekday_source_value' => $sourceTime,
                ];
                $rowCandidates++;
            }

            if ($rowCandidates === 0) {
                $this->addError($row, 'لا يحتوي السطر على أي نطاق وقت أسبوعي صالح.');
            }
        }

        $this->summary['matched_theoretical_sections'] = count($matchedTheory);
        $this->summary['matched_practical_sections'] = count($matchedPractical);

        return $candidates;
    }

    /** @return array<string, array{candidate: array, row_numbers: array<int, int>, conflicting: bool}> */
    private function deduplicateCandidates(array $candidates): array
    {
        $groups = [];

        foreach ($candidates as $candidate) {
            $key = $this->slotKey($candidate, $candidate['academic_term_id']);
            $metadata = $this->candidateMetadataSignature($candidate);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'candidate' => $candidate,
                    'metadata' => $metadata,
                    'row_numbers' => [$candidate['row_number']],
                    'conflicting' => false,
                ];

                continue;
            }

            $groups[$key]['row_numbers'][] = $candidate['row_number'];

            if ($groups[$key]['metadata'] === $metadata) {
                $this->summary['identical_duplicates_ignored']++;

                continue;
            }

            $groups[$key]['conflicting'] = true;
            $this->summary['conflicting_duplicates']++;
            $this->addError($candidate, 'صفوف مكررة لنفس الموعد تحتوي بيانات متعارضة؛ لم يتم حفظ هذا الموعد.');
            $this->addError(
                $groups[$key]['candidate'],
                'صفوف مكررة لنفس الموعد تحتوي بيانات متعارضة؛ لم يتم حفظ هذا الموعد.',
            );
        }

        return $groups;
    }

    private function candidateMetadataSignature(array $candidate): string
    {
        return json_encode([
            $candidate['teacher_name_key'],
            $candidate['hall_name_key'],
            $candidate['section_capacity'],
            $candidate['expected_student_count'],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function existingSlotMetadataConflicts(array $candidate, int $academicTermId): bool
    {
        $slot = SubjectSectionScheduleSlot::query()
            ->with(['lecturer:id,name,canonical_name', 'hall:id,name,code'])
            ->where([
                'academic_term_id' => $academicTermId,
                'subject_section_id' => $candidate['subject_section_id'],
                'weekday' => $candidate['weekday'],
                'start_time' => $candidate['start_time'],
                'end_time' => $candidate['end_time'],
            ])
            ->first();

        if (! $slot) {
            return false;
        }

        $existingLecturer = $slot->lecturer;
        $existingHall = $slot->hall;
        $existingTeacherKey = $existingLecturer instanceof Lecturer
            ? $this->normalizer->normalizeKey($existingLecturer->canonical_name ?: $existingLecturer->name)
            : '';
        $existingHallKeys = $existingHall instanceof Hall ? array_filter([
            $this->normalizer->normalizeKey($existingHall->name),
            $this->normalizer->normalizeKey($existingHall->code),
        ]) : [];

        return ($candidate['teacher_name_key'] !== '' && $existingTeacherKey !== '' && $candidate['teacher_name_key'] !== $existingTeacherKey)
            || ($candidate['hall_name_key'] !== '' && $existingHallKeys !== [] && ! in_array($candidate['hall_name_key'], $existingHallKeys, true))
            || ($candidate['section_capacity'] !== null && $slot->section_capacity !== null && $candidate['section_capacity'] !== $slot->section_capacity)
            || ($candidate['expected_student_count'] !== null && $slot->expected_student_count !== null && $candidate['expected_student_count'] !== $slot->expected_student_count);
    }

    private function resolveLecturer(array $candidate): Lecturer|false|null
    {
        $key = $candidate['teacher_name_key'];

        if ($key === '') {
            $this->summary['missing_lecturers']++;

            return null;
        }

        $matches = $this->lecturers[$key] ?? [];

        if (count($matches) > 1) {
            $this->summary['ambiguous_lecturers']++;

            return false;
        }

        if (count($matches) === 1) {
            $lecturer = reset($matches);
            $this->reusedLecturerIds[$lecturer->id] = true;

            return $lecturer;
        }

        $users = $this->lecturerUsers[$key] ?? [];

        if (count($users) > 1) {
            $this->summary['ambiguous_lecturers']++;

            return false;
        }

        $lecturer = Lecturer::query()->create([
            'user_id' => count($users) === 1 ? reset($users)->id : null,
            'name' => $candidate['teacher_name'],
            'canonical_name' => $key,
            'lecturer_id' => null,
            'email' => null,
            'is_active' => true,
        ]);
        $this->lecturers[$key][] = $lecturer;
        $this->createdLecturerIds[$lecturer->id] = true;

        return $lecturer;
    }

    private function resolveHall(array $candidate): Hall|false|null
    {
        $key = $candidate['hall_name_key'];

        if ($key === '') {
            $this->summary['missing_halls']++;

            return null;
        }

        $matches = array_values($this->halls[$key] ?? []);

        if (count($matches) > 1) {
            return false;
        }

        if (count($matches) === 1) {
            $hall = reset($matches);

            if ($hall->trashed()) {
                $hall->restore();
            }

            $this->reusedHallIds[$hall->id] = true;

            return $hall;
        }

        $hall = Hall::query()->create([
            'code' => $candidate['hall_name'],
            'name' => $candidate['hall_name'],
            'floor' => null,
            'is_active' => true,
        ]);
        $this->halls[$key][$hall->id] = $hall;
        $this->createdHallIds[$hall->id] = true;

        return $hall;
    }

    private function nonDestructiveUpdates(Model $model, array $attributes): array
    {
        $updates = [];

        foreach ($attributes as $key => $value) {
            if ($value === null && $model->getAttribute($key) !== null) {
                continue;
            }

            if ((string) $model->getAttribute($key) !== (string) $value) {
                $updates[$key] = $value;
            }
        }

        return $updates;
    }

    private function slotKey(array $candidate, int $academicTermId): string
    {
        return implode('|', [
            $academicTermId,
            $candidate['subject_section_id'],
            $candidate['weekday'],
            $candidate['start_time'],
            $candidate['end_time'],
        ]);
    }

    private function addError(
        array $row,
        string $reason,
        ?int $rowNumber = null,
        ?int $weekday = null,
        mixed $timeSource = null,
    ): void {
        $this->errors[] = [
            'row_number' => $rowNumber ?? $row['row_number'] ?? null,
            'subject_code' => $row['subject_code_source'] ?? $row['subject_code'] ?? null,
            'section_type' => $row['section_type_source'] ?? $row['section_type'] ?? null,
            'section_number' => $row['section_number_source'] ?? $row['section_number'] ?? null,
            'normalized_section_code' => $row['section_code'] ?? null,
            'teacher_name' => $row['teacher_name_source'] ?? null,
            'hall_name' => $row['hall_name_source'] ?? null,
            'weekday' => $weekday ? $this->normalizer->weekdayName($weekday) : null,
            'time_range' => $timeSource,
            'error_message' => $reason,
        ];
    }
}
