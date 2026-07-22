<?php

namespace App\Services;

use App\Models\Hall;
use App\Models\Lecturer;
use App\Models\LectureSession;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportIssueAction;
use App\Models\ScheduleImportRow;
use App\Models\ScheduleImportRowTimeOverride;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Policies\ScheduleImportRowPolicy;
use App\Support\WeeklyScheduleRowNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BlockedWeeklySlotReconciliationService
{
    public const ACTION_ASSIGN_LECTURER = 'assign_lecturer';

    public const ACTION_ASSIGN_HALL = 'assign_hall';

    public const ACTION_ASSIGN_LECTURER_AND_HALL = 'assign_lecturer_and_hall';

    public const ACTION_CREATE_LECTURER_FROM_SOURCE = 'create_lecturer_from_source';

    public const ACTION_CHANGE_TIME = 'change_time';

    public const ACTION_EXCLUDE_FROM_CURRENT_BATCH = 'exclude_from_current_batch';

    public function __construct(
        private readonly BlockedWeeklySlotReportService $reportService,
        private readonly ScheduleImportReconciliationService $reconciliationService,
        private readonly WeeklyScheduleSlotConflictDetector $conflictDetector,
        private readonly WeeklyScheduleRowNormalizer $normalizer,
    ) {}

    /** @return array<int, array{label: string, key: string, count: int, slot_ids: array<int, int>, description: string}> */
    public function savedGroups(): array
    {
        $rows = collect($this->reportService->latestReport()['rows']);

        return collect([
            [
                'key' => 'lecturer_not_found',
                'label' => 'المجموعة الأولى — مدرس غير موجود',
                'description' => 'خانات لها قيمة مدرس مصدر غير فارغة ولا تطابق هوية موجودة.',
                'slot_ids' => $this->ids($rows->filter(fn (array $row): bool => $this->has($row, 'missing_lecturer_identity')
                    && filled($row['قيمة المدرس الأصلية من الملف'] ?? null))),
            ],
            [
                'key' => 'missing_hall_only',
                'label' => 'المجموعة الثانية — القاعة فقط مفقودة',
                'description' => 'خانات مدرسها جاهز وتحتاج قاعة فقط.',
                'slot_ids' => $this->ids($rows->filter(fn (array $row): bool => $this->has($row, 'missing_hall')
                    && ! $this->has($row, 'missing_lecturer_identity')
                    && ! $this->isConflictRow($row))),
            ],
            [
                'key' => 'missing_lecturer_only',
                'label' => 'المجموعة الثالثة — المدرس فقط مفقود',
                'description' => 'خانات قاعتها موجودة وتحتاج مدرساً فقط.',
                'slot_ids' => $this->ids($rows->filter(fn (array $row): bool => $this->has($row, 'missing_lecturer_identity')
                    && ! $this->has($row, 'missing_hall')
                    && blank($row['قيمة المدرس الأصلية من الملف'] ?? null)
                    && ! $this->isConflictRow($row))),
            ],
            [
                'key' => 'missing_both',
                'label' => 'المجموعة الرابعة — المدرس والقاعة مفقودان',
                'description' => 'خانات تحتاج اختيار مدرس وقاعة معاً قبل إعادة معاينة التوليد.',
                'slot_ids' => $this->ids($rows->filter(fn (array $row): bool => $this->has($row, 'missing_lecturer_identity')
                    && $this->has($row, 'missing_hall')
                    && ! $this->isConflictRow($row))),
            ],
            [
                'key' => 'conflicts',
                'label' => 'المجموعة الخامسة — التعارضات',
                'description' => 'خانات مشاركة في تعارض أسبوعي أو تعارض مرشح جلسة.',
                'slot_ids' => $this->ids($rows->filter(fn (array $row): bool => $this->isConflictRow($row))),
            ],
        ])->map(fn (array $group): array => [
            ...$group,
            'count' => count($group['slot_ids']),
        ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function lecturerOptions(?string $search = null, int $limit = 50): array
    {
        $options = [];
        $lecturers = Lecturer::query()
            ->with('user.roles')
            ->when(filled($search), fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->limit($limit)
            ->get();

        foreach ($lecturers as $lecturer) {
            /** @var Lecturer $lecturer */
            $user = $lecturer->user;
            $options[] = [
                'id' => $lecturer->id,
                'label' => trim($lecturer->name.' — #'.$lecturer->id),
                'name' => $lecturer->name,
                'login_username' => $user instanceof User ? $user->login_username : null,
                'account_status' => $user instanceof User ? $user->status : 'لا يوجد حساب',
                'has_course_lecturer_role' => $user instanceof User && $user->hasRole('course_lecturer'),
                'is_ready' => $this->lecturerIsReady($lecturer),
            ];
        }

        return $options;
    }

    /** @return array<int, array<string, mixed>> */
    public function hallOptions(?string $search = null, int $limit = 50): array
    {
        $options = [];
        $halls = Hall::query()
            ->withoutTrashed()
            ->where('is_active', true)
            ->when(filled($search), fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('floor', 'like', '%'.$search.'%');
            }))
            ->orderBy('code')
            ->limit($limit)
            ->get();

        foreach ($halls as $hall) {
            /** @var Hall $hall */
            $attributes = $hall->getAttributes();
            $options[] = [
                'id' => $hall->id,
                'label' => trim(($hall->code ?: 'بدون رمز').' — '.$hall->name),
                'code' => $hall->code,
                'name' => $hall->name,
                'capacity' => $attributes['capacity'] ?? null,
                'type' => $attributes['type'] ?? null,
                'floor' => $hall->floor,
            ];
        }

        return $options;
    }

    /**
     * @param  array<int, int|string>  $slotIds
     * @param  array<string, mixed>  $proposal
     * @return array<string, mixed>
     */
    public function preview(array $slotIds, array $proposal, ?User $actor = null): array
    {
        if ($actor) {
            Gate::forUser($actor)->authorize(ScheduleImportRowPolicy::PREVIEW_BLOCKED_WEEKLY_SLOT_RECONCILIATION);
        }

        $slots = $this->slots($slotIds);
        $rows = $this->rowsForSlots($slots);
        $action = (string) ($proposal['action'] ?? '');
        $validation = $this->validateSelection($slots, $rows, $proposal, $actor, preview: true);
        $lecturerCandidate = filled($proposal['lecturer_id'] ?? null) ? Lecturer::query()->with('user.roles')->find((int) $proposal['lecturer_id']) : null;
        $hallCandidate = filled($proposal['hall_id'] ?? null) ? Hall::query()->withoutTrashed()->find((int) $proposal['hall_id']) : null;
        $proposedLecturer = $lecturerCandidate instanceof Lecturer ? $lecturerCandidate : null;
        $proposedHall = $hallCandidate instanceof Hall ? $hallCandidate : null;
        $proposedLecturerName = $proposedLecturer instanceof Lecturer ? $proposedLecturer->name : null;
        $proposedHallName = $proposedHall instanceof Hall ? $proposedHall->name : null;
        $sourceLecturerName = $this->confirmedSourceLecturerName($rows);
        $newConflicts = $validation['blocking_errors'] === []
            ? $this->newConflictsFor($slots, $proposal)
            : [];
        $expectedSessions = $this->expectedSessionCount($slots);
        $slotRows = [];
        foreach ($slots as $slot) {
            /** @var SubjectSectionScheduleSlot $slot */
            $row = $rows->first(fn (ScheduleImportRow $candidate): bool => in_array($slot->id, $candidate->relatedScheduleSlotIds(), true));
            $existingErrors = $this->existingErrorCodes($row);
            $wouldResolve = $this->resolvedErrorCodes($existingErrors, $proposal);
            $remaining = array_values(array_diff($existingErrors, $wouldResolve));

            if ($action === self::ACTION_CREATE_LECTURER_FROM_SOURCE && $this->sourceLecturerName($row) !== null) {
                $wouldResolve = array_values(array_unique([...$wouldResolve, ScheduleImportIssue::TYPE_LECTURER_MISSING]));
                $remaining = array_values(array_diff($remaining, [ScheduleImportIssue::TYPE_LECTURER_MISSING]));
            }

            $slotRows[] = [
                'slot_id' => $slot->id,
                'source_row_id' => $row?->id,
                'excel_row' => $row?->source_row_number,
                'subject' => $this->relationAttribute($slot, 'subject', 'name'),
                'section' => $this->relationAttribute($slot, 'subjectSection', 'code'),
                'weekday' => $slot->weekday,
                'weekday_label' => __('weekly-schedule.weekdays')[$slot->weekday] ?? (string) $slot->weekday,
                'time' => substr((string) $slot->start_time, 0, 5).' - '.substr((string) $slot->end_time, 0, 5),
                'current_lecturer' => $this->relationAttribute($slot, 'lecturer', 'name') ?? 'غير محدد',
                'proposed_lecturer' => $proposedLecturerName ?? ($action === self::ACTION_CREATE_LECTURER_FROM_SOURCE ? $this->sourceLecturerName($row) : null),
                'current_hall' => $this->relationAttribute($slot, 'hall', 'name') ?? 'غير محددة',
                'proposed_hall' => $proposedHallName,
                'existing_errors' => $existingErrors,
                'errors_resolved' => $wouldResolve,
                'errors_remaining' => $remaining,
                'expected_additional_sessions' => $expectedSessions[$slot->id] ?? 0,
                'raw_lecturer_value' => $this->sourceLecturerName($row),
                'raw_hall_value' => $this->sourceHallName($row),
            ];
        }

        $warnings = [];
        if ($proposedLecturer && ! $this->lecturerIsReady($proposedLecturer)) {
            $warnings[] = 'المدرس المحدد لا يملك حساب دخول فعالاً بدور course_lecturer، لذلك ستبقى الجلسات محجوبة حتى تهيئة الحساب.';
        }

        if ($action === self::ACTION_CREATE_LECTURER_FROM_SOURCE && $sourceLecturerName === null) {
            $validation['blocking_errors'][] = 'لا توجد قيمة مدرس مصدر موحدة لإنشاء هوية مدرس منها.';
        }

        $blockingErrors = array_values(array_unique([
            ...$validation['blocking_errors'],
            ...($newConflicts === [] ? [] : ['ستُدخل المعالجة تعارضاً جديداً غير محلول.']),
        ]));

        return [
            'action' => $action,
            'selected_count' => $slots->count(),
            'source_slot_ids' => $slots->pluck('id')->values()->all(),
            'term_ids' => $slots->pluck('academic_term_id')->unique()->values()->all(),
            'batch_ids' => $slots->pluck('import_batch_id')->unique()->values()->all(),
            'rows' => $slotRows,
            'warnings' => $warnings,
            'blocking_errors' => $blockingErrors,
            'confirm_enabled' => $blockingErrors === [],
            'new_conflicts' => $newConflicts,
            'readiness' => $this->readinessSimulation($slots, $proposal, $newConflicts),
            'expected_additional_sessions' => array_sum($expectedSessions),
            'source_lecturer_name' => $sourceLecturerName,
            'permission_checked' => $actor !== null,
            'writes_performed' => false,
        ];
    }

    /**
     * @param  array<int, int|string>  $slotIds
     * @param  array<string, mixed>  $proposal
     * @return array<string, mixed>
     */
    public function apply(array $slotIds, array $proposal, User $actor): array
    {
        Gate::forUser($actor)->authorize(ScheduleImportRowPolicy::RECONCILE_BLOCKED_WEEKLY_SLOTS);

        $preview = $this->preview($slotIds, $proposal, $actor);
        if (! $preview['confirm_enabled']) {
            throw new RuntimeException(implode("\n", $preview['blocking_errors']));
        }

        $results = [];
        $hasFailure = false;
        foreach ($this->slots($slotIds) as $slot) {
            /** @var SubjectSectionScheduleSlot $slot */
            try {
                $fresh = $slot->fresh();
                if (! $fresh instanceof SubjectSectionScheduleSlot) {
                    throw new RuntimeException('تعذر تحميل الموعد الأسبوعي قبل الحفظ.');
                }

                $results[] = DB::transaction(fn (): array => $this->applyOne($fresh, $proposal, $actor));
            } catch (Throwable $exception) {
                $hasFailure = true;
                $results[] = [
                    'slot_id' => $slot->id,
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return [
            'status' => $hasFailure ? 'completed_with_errors' : 'completed',
            'results' => $results,
            'writes_performed' => true,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function suggestedTreatmentRows(array $rows): array
    {
        return collect($rows)
            ->map(fn (array $row): array => [
                'رقم الموعد الأسبوعي' => $row['رقم الموعد الأسبوعي'] ?? '',
                'رقم صف Excel' => $row['رقم صف Excel'] ?? '',
                'المادة' => $row['المادة'] ?? '',
                'الشعبة' => $row['الشعبة'] ?? '',
                'اليوم' => $row['اليوم'] ?? '',
                'الوقت' => trim(($row['وقت البداية'] ?? '').' - '.($row['وقت النهاية'] ?? '')),
                'المدرس الحالي' => $row['المدرس'] ?? '',
                'المدرس المقترح' => '',
                'القاعة الحالية' => $row['القاعة'] ?? '',
                'القاعة المقترحة' => '',
                'المشكلات الحالية' => $row['المشكلات'] ?? '',
                'المشكلات التي ستبقى' => $row['المشكلات'] ?? '',
                'الجلسات المتوقعة بعد المعالجة' => $row['عدد الجلسات المتأثرة'] ?? 0,
                'القرار المطلوب' => $this->suggestDecision($row),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function conflictComparison(int $slotId): array
    {
        $report = $this->reportService->latestReport();
        $conflicts = collect($report['conflicts'])
            ->filter(fn (array $conflict): bool => (int) ($conflict['source_slot_id'] ?? 0) === $slotId
                || (int) ($conflict['conflicting_source_slot_id'] ?? 0) === $slotId)
            ->values()
            ->all();

        return [
            'slot_id' => $slotId,
            'conflicts' => $conflicts,
            'available_actions' => [
                'تغيير المدرس',
                'تغيير القاعة',
                'تغيير وقت البداية والنهاية',
                'استبعاد أحد الموعدين من برنامج هذا الفصل',
            ],
            'automatic_winner_selected' => false,
            'writes_performed' => false,
        ];
    }

    /** @param  EloquentCollection<int, SubjectSectionScheduleSlot>  $slots */
    private function validateSelection(EloquentCollection $slots, Collection $rows, array $proposal, ?User $actor, bool $preview): array
    {
        $errors = [];
        $action = (string) ($proposal['action'] ?? '');

        if ($slots->isEmpty()) {
            $errors[] = 'يجب اختيار موعد أسبوعي واحد على الأقل.';
        }

        if ($slots->pluck('academic_term_id')->unique()->count() > 1) {
            $errors[] = 'الخانات المحددة تمتد على أكثر من فصل دراسي.';
        }

        if ($slots->pluck('import_batch_id')->unique()->count() > 1) {
            $errors[] = 'الخانات المحددة تمتد على أكثر من دفعة استيراد.';
        }

        if ($rows->contains(fn (ScheduleImportRow $row): bool => $row->isExcludedFromWeeklySchedule())
            && $action !== self::ACTION_EXCLUDE_FROM_CURRENT_BATCH) {
            $errors[] = 'توجد خانات مستبعدة من هذه الدفعة وتتطلب إجراء استعادة مخصصاً.';
        }

        $generatedSlotIds = LectureSession::query()
            ->whereIn('subject_section_schedule_slot_id', $slots->pluck('id'))
            ->pluck('subject_section_schedule_slot_id')
            ->unique()
            ->values()
            ->all();

        if ($generatedSlotIds !== []) {
            $errors[] = 'توجد خانات لها جلسات مولدة بالفعل: '.implode(', ', $generatedSlotIds).'. يلزم مسار ترحيل مخصص.';
        }

        if (in_array($action, [self::ACTION_ASSIGN_LECTURER, self::ACTION_ASSIGN_LECTURER_AND_HALL], true)
            && ! Lecturer::query()->whereKey((int) ($proposal['lecturer_id'] ?? 0))->exists()) {
            $errors[] = 'المدرس المقترح غير صالح.';
        }

        if (in_array($action, [self::ACTION_ASSIGN_HALL, self::ACTION_ASSIGN_LECTURER_AND_HALL], true)
            && ! Hall::query()->withoutTrashed()->whereKey((int) ($proposal['hall_id'] ?? 0))->exists()) {
            $errors[] = 'القاعة المقترحة غير صالحة.';
        }

        if ($action === self::ACTION_CHANGE_TIME) {
            $start = substr((string) ($proposal['start_time'] ?? ''), 0, 5);
            $end = substr((string) ($proposal['end_time'] ?? ''), 0, 5);
            if (preg_match('/^\d{2}:\d{2}$/', $start) !== 1 || preg_match('/^\d{2}:\d{2}$/', $end) !== 1 || $end <= $start) {
                $errors[] = 'وقت البداية والنهاية غير صالحين.';
            }
        }

        if ($action === self::ACTION_EXCLUDE_FROM_CURRENT_BATCH && Str::length(trim((string) ($proposal['reason'] ?? ''))) < 5) {
            $errors[] = 'يتطلب الاستبعاد سبباً مكتوباً لا يقل عن خمسة أحرف.';
        }

        if ($actor && ! $actor->isSuperAdmin()) {
            $ability = match ($action) {
                self::ACTION_CREATE_LECTURER_FROM_SOURCE => ScheduleImportRowPolicy::CREATE_LECTURER_IDENTITY_FROM_SOURCE,
                self::ACTION_ASSIGN_LECTURER => ScheduleImportRowPolicy::CHANGE_RECONCILED_LECTURER,
                self::ACTION_ASSIGN_HALL => ScheduleImportRowPolicy::CHANGE_RECONCILED_HALL,
                self::ACTION_ASSIGN_LECTURER_AND_HALL => ScheduleImportRowPolicy::RECONCILE_BLOCKED_WEEKLY_SLOTS,
                self::ACTION_CHANGE_TIME => ScheduleImportRowPolicy::CHANGE_RECONCILED_WEEKLY_TIME,
                self::ACTION_EXCLUDE_FROM_CURRENT_BATCH => ScheduleImportRowPolicy::EXCLUDE_WEEKLY_SLOT_FROM_CURRENT_BATCH,
                default => ScheduleImportRowPolicy::RECONCILE_BLOCKED_WEEKLY_SLOTS,
            };

            if (Gate::forUser($actor)->denies($ability)) {
                $errors[] = 'لا يملك المستخدم صلاحية تنفيذ هذه المعالجة.';
            }
        }

        return ['blocking_errors' => $errors];
    }

    /** @return array<string, mixed> */
    private function applyOne(SubjectSectionScheduleSlot $slot, array $proposal, User $actor): array
    {
        $row = $this->rowForSlot($slot);
        $action = (string) ($proposal['action'] ?? '');
        $note = $proposal['note'] ?? null;

        return match ($action) {
            self::ACTION_ASSIGN_LECTURER => [
                'slot_id' => $slot->id,
                'status' => 'applied',
                'result' => $this->reconciliationService->assignLecturer($row, (int) $proposal['lecturer_id'], $actor, $note),
            ],
            self::ACTION_ASSIGN_HALL => [
                'slot_id' => $slot->id,
                'status' => 'applied',
                'result' => $this->reconciliationService->assignHall($row, (int) $proposal['hall_id'], $actor, $note),
            ],
            self::ACTION_ASSIGN_LECTURER_AND_HALL => $this->applyLecturerAndHall($row, $slot, $proposal, $actor),
            self::ACTION_CREATE_LECTURER_FROM_SOURCE => $this->applyCreateLecturerFromSource($row, $slot, $proposal, $actor),
            self::ACTION_CHANGE_TIME => $this->applyTimeOverride($row, $slot, $proposal, $actor),
            self::ACTION_EXCLUDE_FROM_CURRENT_BATCH => $this->applyBatchScopedExclusion($row, $slot, $proposal, $actor),
            default => throw new RuntimeException('إجراء غير مدعوم.'),
        };
    }

    /** @return array<string, mixed> */
    private function applyLecturerAndHall(ScheduleImportRow $row, SubjectSectionScheduleSlot $slot, array $proposal, User $actor): array
    {
        $before = $this->slotState($slot);
        $lecturer = $this->reconciliationService->assignLecturer($row, (int) $proposal['lecturer_id'], $actor, $proposal['note'] ?? null);
        $hall = $this->reconciliationService->assignHall($row->fresh(), (int) $proposal['hall_id'], $actor, $proposal['note'] ?? null);

        return [
            'slot_id' => $slot->id,
            'status' => 'applied',
            'previous_state' => $before,
            'new_state' => $this->slotState($slot->fresh()),
            'result' => ['lecturer' => $lecturer, 'hall' => $hall],
        ];
    }

    /** @return array<string, mixed> */
    private function applyCreateLecturerFromSource(ScheduleImportRow $row, SubjectSectionScheduleSlot $slot, array $proposal, User $actor): array
    {
        Gate::forUser($actor)->authorize(ScheduleImportRowPolicy::CREATE_LECTURER_IDENTITY_FROM_SOURCE);

        $name = $this->sourceLecturerName($row);
        if ($name === null) {
            throw new RuntimeException('لا توجد قيمة مدرس مصدر صالحة.');
        }

        $key = $this->normalizer->normalizeKey($name);
        $existing = Lecturer::query()->get(['id', 'name', 'canonical_name'])
            ->first(fn (Lecturer $lecturer): bool => $this->normalizer->normalizeKey($lecturer->canonical_name ?: $lecturer->name) === $key);

        if ($existing) {
            $result = $this->reconciliationService->assignLecturer($row, $existing->id, $actor, $proposal['note'] ?? null);

            return [
                'slot_id' => $slot->id,
                'status' => 'applied_existing_identity',
                'created_lecturer_id' => null,
                'lecturer_id' => $existing->id,
                'result' => $result,
            ];
        }

        $result = $this->reconciliationService->createLecturerIdentity($row, $name, $actor, $proposal['note'] ?? null);

        return [
            'slot_id' => $slot->id,
            'status' => 'applied_created_identity',
            'created_lecturer_id' => $result['created_lecturer_id'] ?? null,
            'result' => $result,
        ];
    }

    /** @return array<string, mixed> */
    private function applyTimeOverride(ScheduleImportRow $row, SubjectSectionScheduleSlot $slot, array $proposal, User $actor): array
    {
        Gate::forUser($actor)->authorize(ScheduleImportRowPolicy::CHANGE_RECONCILED_WEEKLY_TIME);
        $start = substr((string) $proposal['start_time'], 0, 5).':00';
        $end = substr((string) $proposal['end_time'], 0, 5).':00';
        $candidate = [
            'subject_section_id' => $slot->subject_section_id,
            'weekday' => (int) ($proposal['weekday'] ?? $slot->weekday),
            'start_time' => $start,
            'end_time' => $end,
            'lecturer_id' => $slot->lecturer_id,
            'hall_id' => $slot->hall_id,
        ];
        $conflicts = $this->conflictDetector->conflicts($row, $candidate, $slot->id, lock: true);
        if ($conflicts !== []) {
            throw new RuntimeException($this->conflictDetector->message($conflicts));
        }

        $before = $this->slotState($slot);
        ScheduleImportRowTimeOverride::query()->create([
            'schedule_import_row_id' => $row->id,
            'weekday' => $candidate['weekday'],
            'start_time' => $start,
            'end_time' => $end,
            'lecturer_id' => $slot->lecturer_id,
            'hall_id' => $slot->hall_id,
            'section_capacity' => $slot->section_capacity,
            'expected_student_count' => $slot->expected_student_count,
            'created_by' => $actor->id,
        ]);
        $slot->update([
            'weekday' => $candidate['weekday'],
            'start_time' => $start,
            'end_time' => $end,
        ]);
        $this->audit($row, ScheduleImportIssueAction::ACTION_ASSIGN_WEEKLY_TIME, $actor, $before, $this->slotState($slot->fresh()), [
            'manual_time_override' => true,
            'slot_id' => $slot->id,
            'original_time_preserved_in_source_payload' => true,
        ], $proposal['note'] ?? null, ScheduleImportIssueWorkflow::CONFLICT_ISSUES);

        return ['slot_id' => $slot->id, 'status' => 'applied', 'result' => ['time_override' => true]];
    }

    /** @return array<string, mixed> */
    private function applyBatchScopedExclusion(ScheduleImportRow $row, SubjectSectionScheduleSlot $slot, array $proposal, User $actor): array
    {
        Gate::forUser($actor)->authorize(ScheduleImportRowPolicy::EXCLUDE_WEEKLY_SLOT_FROM_CURRENT_BATCH);

        $reason = trim((string) ($proposal['reason'] ?? ''));
        if (Str::length($reason) < 5) {
            throw new RuntimeException('سبب الاستبعاد مطلوب.');
        }

        $before = $this->slotState($slot);
        $row->update([
            'current_reconciliation_status' => ScheduleImportRow::STATUS_EXCLUDED_FROM_BATCH_SCHEDULE,
            'excluded_from_weekly_schedule_at' => now(),
            'excluded_from_weekly_schedule_by' => $actor->id,
            'exclusion_note' => $reason,
            'resolution_updated_by' => $actor->id,
            'resolution_updated_at' => now(),
        ]);
        $this->audit($row->fresh(), ScheduleImportIssueAction::ACTION_EXCLUDE_FROM_BATCH_SCHEDULE, $actor, $before, $this->slotState($slot->fresh()), [
            'batch_scoped_exclusion' => true,
            'slot_deleted' => false,
            'slot_id' => $slot->id,
            'reason' => $reason,
        ], $reason, ScheduleImportIssue::issueTypes());

        return ['slot_id' => $slot->id, 'status' => 'applied', 'result' => ['slot_deleted' => false]];
    }

    private function audit(ScheduleImportRow $row, string $action, User $actor, array $previousState, array $newState, array $result, ?string $note, array $issueTypes): void
    {
        $issues = $row->issues()
            ->whereIn('issue_type', $issueTypes)
            ->orderBy('id')
            ->get();

        if ($issues->isEmpty()) {
            $issues = $row->issues()->orderBy('id')->limit(1)->get();
        }

        foreach ($issues as $issue) {
            ScheduleImportIssueAction::query()->create([
                'schedule_import_issue_id' => $issue->id,
                'actor_user_id' => $actor->id,
                'action' => $action,
                'previous_status' => $issue->resolution_status,
                'new_status' => $issue->resolution_status,
                'previous_subject_id' => $row->resolved_subject_id,
                'previous_subject_section_id' => $row->resolved_subject_section_id,
                'selected_subject_id' => $row->resolved_subject_id,
                'selected_subject_section_id' => $row->resolved_subject_section_id,
                'previous_state' => [
                    'import_batch_id' => $row->import_batch_id,
                    'academic_term_id' => $row->academic_term_id,
                    'source_row_id' => $row->id,
                    'source_row_number' => $row->source_row_number,
                    'weekly_slot' => $previousState,
                ],
                'new_state' => [
                    'import_batch_id' => $row->import_batch_id,
                    'academic_term_id' => $row->academic_term_id,
                    'source_row_id' => $row->id,
                    'source_row_number' => $row->source_row_number,
                    'weekly_slot' => $newState,
                ],
                'result' => $result,
                'note' => $note,
                'performed_at' => now(),
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function newConflictsFor(EloquentCollection $slots, array $proposal): array
    {
        $conflicts = [];

        foreach ($slots as $slot) {
            /** @var SubjectSectionScheduleSlot $slot */
            $row = $this->rowForSlot($slot);
            $candidate = [
                'subject_section_id' => $slot->subject_section_id,
                'weekday' => (int) ($proposal['weekday'] ?? $slot->weekday),
                'start_time' => filled($proposal['start_time'] ?? null) ? substr((string) $proposal['start_time'], 0, 5).':00' : $slot->start_time,
                'end_time' => filled($proposal['end_time'] ?? null) ? substr((string) $proposal['end_time'], 0, 5).':00' : $slot->end_time,
                'lecturer_id' => filled($proposal['lecturer_id'] ?? null) ? (int) $proposal['lecturer_id'] : $slot->lecturer_id,
                'hall_id' => filled($proposal['hall_id'] ?? null) ? (int) $proposal['hall_id'] : $slot->hall_id,
            ];

            foreach ($this->conflictDetector->conflicts($row, $candidate, $slot->id) as $conflict) {
                $conflicts[] = [
                    ...$conflict,
                    'source_slot_id' => $slot->id,
                    'dimension' => $conflict['type'] ?? 'unknown',
                ];
            }
        }

        /** @var Collection<int, array<string, mixed>> $conflictCollection */
        $conflictCollection = collect($conflicts);

        return $conflictCollection
            ->unique(fn (array $conflict): string => implode('|', [$conflict['source_slot_id'], $conflict['slot_id'], $conflict['dimension']]))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function readinessSimulation(EloquentCollection $slots, array $proposal, array $newConflicts): array
    {
        $ready = 0;
        $accountsMissing = 0;
        $hallsMissing = 0;
        $expected = $this->expectedSessionCount($slots);

        foreach ($slots as $slot) {
            /** @var SubjectSectionScheduleSlot $slot */
            $selectedLecturer = filled($proposal['lecturer_id'] ?? null)
                ? Lecturer::query()->with('user.roles')->find((int) $proposal['lecturer_id'])
                : null;
            $lecturer = filled($proposal['lecturer_id'] ?? null)
                ? ($selectedLecturer instanceof Lecturer ? $selectedLecturer : null)
                : $slot->lecturer;
            $hallId = filled($proposal['hall_id'] ?? null) ? (int) $proposal['hall_id'] : $slot->hall_id;
            $hasReadyLecturer = $lecturer instanceof Lecturer && $this->lecturerIsReady($lecturer);
            $hasHall = $hallId !== null && Hall::query()->withoutTrashed()->whereKey($hallId)->exists();

            $accountsMissing += $hasReadyLecturer ? 0 : 1;
            $hallsMissing += $hasHall ? 0 : 1;
            $ready += ($hasReadyLecturer && $hasHall && $newConflicts === []) ? 1 : 0;
        }

        return [
            'weekly_slots_that_would_become_ready' => $ready,
            'expected_dated_sessions_safe_to_create' => $newConflicts === [] ? array_sum($expected) : 0,
            'slots_still_blocked' => max(0, $slots->count() - $ready),
            'conflicts_remaining' => count($newConflicts),
            'accounts_still_missing' => $accountsMissing,
            'halls_still_missing' => $hallsMissing,
            'generation_remains_separate_action' => true,
            'writes_performed' => false,
        ];
    }

    /** @param  EloquentCollection<int, SubjectSectionScheduleSlot>  $slots */
    private function expectedSessionCount(EloquentCollection $slots): array
    {
        $counts = [];

        foreach ($slots as $slot) {
            /** @var SubjectSectionScheduleSlot $slot */
            $term = $slot->academicTerm;
            if (! $term instanceof Model || blank($term->getAttribute('teaching_start_date')) || blank($term->getAttribute('teaching_end_date'))) {
                $counts[$slot->id] = 0;

                continue;
            }

            $start = CarbonImmutable::parse($term->getAttribute('teaching_start_date'));
            $end = CarbonImmutable::parse($term->getAttribute('teaching_end_date'));
            $offset = ($slot->weekday - $start->isoWeekday() + 7) % 7;
            $count = 0;
            for ($date = $start->addDays($offset); $date->lte($end); $date = $date->addWeek()) {
                $count++;
            }

            $counts[$slot->id] = $count;
        }

        return $counts;
    }

    /** @param  array<int, int|string>  $slotIds */
    private function slots(array $slotIds): EloquentCollection
    {
        $ids = collect($slotIds)->filter(fn (mixed $id): bool => is_numeric($id))->map(fn (mixed $id): int => (int) $id)->unique()->values();

        /** @var EloquentCollection<int, SubjectSectionScheduleSlot> $slots */
        $slots = SubjectSectionScheduleSlot::query()
            ->with(['academicTerm', 'subject', 'subjectSection', 'lecturer.user.roles', 'hall'])
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        if ($slots->count() !== $ids->count()) {
            throw new RuntimeException('توجد خانات أسبوعية غير موجودة ضمن التحديد.');
        }

        return $slots;
    }

    /** @param  EloquentCollection<int, SubjectSectionScheduleSlot>  $slots */
    private function rowsForSlots(EloquentCollection $slots): Collection
    {
        $slotIds = $slots->pluck('id')->all();

        return ScheduleImportRow::query()
            ->with(['issues', 'resolvedLecturer', 'resolvedHall'])
            ->where('academic_term_id', $slots->first()?->academic_term_id)
            ->get()
            ->filter(fn (ScheduleImportRow $row): bool => collect($row->relatedScheduleSlotIds())->intersect($slotIds)->isNotEmpty())
            ->values();
    }

    private function rowForSlot(SubjectSectionScheduleSlot $slot): ScheduleImportRow
    {
        $row = ScheduleImportRow::query()
            ->with(['issues', 'resolvedLecturer', 'resolvedHall'])
            ->where('academic_term_id', $slot->academic_term_id)
            ->get()
            ->first(fn (ScheduleImportRow $candidate): bool => in_array($slot->id, $candidate->relatedScheduleSlotIds(), true));

        if (! $row) {
            throw new RuntimeException('لم يتم العثور على صف الاستيراد المرتبط بالموعد الأسبوعي.');
        }

        return $row;
    }

    private function confirmedSourceLecturerName(Collection $rows): ?string
    {
        $names = $rows->map(fn (ScheduleImportRow $row): ?string => $this->sourceLecturerName($row))->filter()->unique()->values();

        return $names->count() === 1 ? $names->first() : null;
    }

    private function sourceLecturerName(?ScheduleImportRow $row): ?string
    {
        $value = $row?->source_payload['lecturer'] ?? $row?->source_payload['lecturer_name'] ?? $row?->source_payload['اسم المدرس'] ?? $row?->normalized_payload['lecturer_name'] ?? null;
        $value ??= $row?->source_payload['teacher_name'] ?? $row?->normalized_payload['teacher_name'] ?? $row?->normalized_payload['teacher_name_source'] ?? null;

        return filled($value) ? trim((string) $value) : null;
    }

    private function sourceHallName(?ScheduleImportRow $row): ?string
    {
        $value = $row?->source_payload['hall'] ?? $row?->source_payload['hall_name'] ?? $row?->source_payload['اسم القاعة'] ?? $row?->normalized_payload['hall_name'] ?? null;

        return filled($value) ? trim((string) $value) : null;
    }

    /** @return array<int, string> */
    private function existingErrorCodes(?ScheduleImportRow $row): array
    {
        if (! $row) {
            return [];
        }

        return $row->issues
            ->filter(fn (ScheduleImportIssue $issue): bool => in_array($issue->resolution_status, [ScheduleImportIssue::STATUS_UNRESOLVED, ScheduleImportIssue::STATUS_RETRY_FAILED], true))
            ->pluck('issue_type')
            ->values()
            ->all();
    }

    /** @param  array<int, string>  $existingErrors */
    private function resolvedErrorCodes(array $existingErrors, array $proposal): array
    {
        $resolved = [];
        $action = (string) ($proposal['action'] ?? '');

        if (in_array($action, [self::ACTION_ASSIGN_LECTURER, self::ACTION_ASSIGN_LECTURER_AND_HALL], true)) {
            $resolved = [...$resolved, ...array_intersect($existingErrors, ScheduleImportIssueWorkflow::LECTURER_ISSUES)];
        }

        if (in_array($action, [self::ACTION_ASSIGN_HALL, self::ACTION_ASSIGN_LECTURER_AND_HALL], true)) {
            $resolved = [...$resolved, ...array_intersect($existingErrors, ScheduleImportIssueWorkflow::HALL_ISSUES)];
        }

        if ($action === self::ACTION_CHANGE_TIME) {
            $resolved = [...$resolved, ...array_intersect($existingErrors, ScheduleImportIssueWorkflow::CONFLICT_ISSUES)];
        }

        if ($action === self::ACTION_EXCLUDE_FROM_CURRENT_BATCH) {
            $resolved = [...$resolved, ...$existingErrors];
        }

        return array_values(array_unique($resolved));
    }

    private function lecturerIsReady(Lecturer $lecturer): bool
    {
        $user = $lecturer->user;

        return $user instanceof User
            && $user->status === 'active'
            && (bool) $user->is_active
            && $user->hasRole('course_lecturer');
    }

    private function relationAttribute(SubjectSectionScheduleSlot $slot, string $relation, string $attribute): ?string
    {
        $related = $slot->getRelationValue($relation);

        return $related instanceof Model ? (string) $related->getAttribute($attribute) : null;
    }

    private function slotState(SubjectSectionScheduleSlot $slot): array
    {
        return [
            'weekly_slot_id' => $slot->id,
            'import_batch_id' => $slot->import_batch_id,
            'academic_term_id' => $slot->academic_term_id,
            'lecturer_id' => $slot->lecturer_id,
            'hall_id' => $slot->hall_id,
            'weekday' => $slot->weekday,
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
        ];
    }

    private function has(array $row, string $code): bool
    {
        return in_array($code, $row['رموز المشكلات'] ?? [], true);
    }

    private function isConflictRow(array $row): bool
    {
        return collect($row['رموز المشكلات'] ?? [])->intersect(['weekly_schedule_overlap', 'scheduling_conflict'])->isNotEmpty();
    }

    private function ids(Collection $rows): array
    {
        return $rows->pluck('رقم الموعد الأسبوعي')->map(fn (mixed $id): int => (int) $id)->values()->all();
    }

    private function suggestDecision(array $row): string
    {
        $codes = $row['رموز المشكلات'] ?? [];

        return match (true) {
            in_array('missing_lecturer_identity', $codes, true) && filled($row['قيمة المدرس الأصلية من الملف'] ?? null) => 'تأكيد إنشاء هوية مدرس من قيمة المصدر أو اختيار هوية موجودة.',
            in_array('missing_lecturer_identity', $codes, true) && in_array('missing_hall', $codes, true) => 'اختيار مدرس وقاعة بعد مراجعة الخانة.',
            in_array('missing_lecturer_identity', $codes, true) => 'اختيار مدرس موجود يدوياً.',
            in_array('missing_hall', $codes, true) => 'اختيار قاعة موجودة يدوياً.',
            collect($codes)->intersect(['weekly_schedule_overlap', 'scheduling_conflict'])->isNotEmpty() => 'فتح مقارنة التعارض واتخاذ قرار صريح.',
            default => 'مراجعة يدوية.',
        };
    }
}
