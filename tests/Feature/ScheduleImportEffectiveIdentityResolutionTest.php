<?php

use App\Filament\Pages\ScheduleImportReconciliationReport;
use App\Models\Hall;
use App\Models\Lecturer;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportIssueAction;
use App\Models\ScheduleImportRow;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Services\ScheduleImportReconciliationService;
use App\Services\ScheduleImportRowResolutionContext;
use Filament\Facades\Filament;
use Livewire\Livewire;

require_once __DIR__.'/../Support/ScheduleImportReconciliationFixtures.php';

function effectiveIdentityRow(mixed $lecturerSource, mixed $hallSource): array
{
    $path = reconciliationWorkbook([]);
    $suffix = strtoupper(substr(hash('sha256', uniqid('', true)), 0, 8));
    [$term, , $batch, $subject, $section] = reconciliationSource($path, type: \App\Models\ImportBatch::TYPE_WEEKLY_SCHEDULE, suffix: $suffix);
    $row = ScheduleImportRow::query()->create([
        'import_batch_id' => $batch->id,
        'academic_term_id' => $term->id,
        'source_sheet_name' => 'Schedule',
        'source_row_number' => 379,
        'row_fingerprint' => hash('sha256', uniqid('effective-identity-', true)),
        'source_payload' => ['teacher_name' => $lecturerSource, 'hall_name' => $hallSource],
        'normalized_payload' => [
            'subject_code_key' => strtolower($subject->code),
            'section_code' => 'T1',
            'section_type' => 'T',
            'teacher_name_source' => $lecturerSource,
            'hall_name_source' => $hallSource,
            'weekday_values' => [1 => '12:30PM-02:30PM'],
            'section_capacity' => 36,
            'expected_student_count' => 26,
        ],
        'original_import_status' => ScheduleImportRow::ORIGINAL_REJECTED,
        'current_reconciliation_status' => ScheduleImportRow::STATUS_UNRESOLVED,
        'resolved_subject_id' => $subject->id,
        'resolved_subject_section_id' => $section->id,
    ]);

    return [$path, $term, $batch, $subject, $section, $row];
}

it('uses manual canonical identities before exact original source matches', function (): void {
    [$path, , , , , $row] = effectiveIdentityRow('مدرس مصدر', 'SOURCE-HALL');

    try {
        Lecturer::query()->create(['name' => 'مدرس مصدر', 'canonical_name' => 'مدرس مصدر', 'is_active' => true]);
        Hall::query()->create(['code' => 'SOURCE-HALL', 'name' => 'قاعة مصدر', 'is_active' => true]);
        $manualLecturer = Lecturer::query()->create(['name' => 'مدرس معتمد', 'canonical_name' => 'مدرس معتمد', 'is_active' => true]);
        $manualHall = Hall::query()->create(['code' => 'MANUAL-HALL', 'name' => 'قاعة معتمدة', 'is_active' => true]);
        $row->update(['resolved_lecturer_id' => $manualLecturer->id, 'resolved_hall_id' => $manualHall->id]);
        $context = app(ScheduleImportRowResolutionContext::class);

        expect($context->effectiveLecturerResolution($row->fresh()))->toMatchArray([
            'id' => $manualLecturer->id,
            'source' => ScheduleImportRowResolutionContext::SOURCE_MANUAL,
            'status' => ScheduleImportRowResolutionContext::STATUS_RESOLVED,
        ])->and($context->effectiveHallResolution($row->fresh()))->toMatchArray([
            'id' => $manualHall->id,
            'source' => ScheduleImportRowResolutionContext::SOURCE_MANUAL,
            'status' => ScheduleImportRowResolutionContext::STATUS_RESOLVED,
        ]);
    } finally {
        @unlink($path);
    }
});

it('uses exact importer normalization for source lecturer and hall identities', function (): void {
    [$path, , , , , $row] = effectiveIdentityRow("  نتالي\u{00A0}محمد   موسى ", ' f-۰۲a ');

    try {
        $lecturer = Lecturer::query()->create(['name' => 'نتالي محمد موسى', 'canonical_name' => 'نتالي محمد موسى', 'is_active' => true]);
        $hall = Hall::query()->create(['code' => 'F-02A', 'name' => 'قاعة F-02A', 'is_active' => true]);
        $context = app(ScheduleImportRowResolutionContext::class);

        expect($context->effectiveLecturerResolution($row))->toMatchArray([
            'id' => $lecturer->id,
            'source' => ScheduleImportRowResolutionContext::SOURCE_ORIGINAL_EXACT_MATCH,
            'status' => ScheduleImportRowResolutionContext::STATUS_RESOLVED,
        ])->and($context->effectiveHallResolution($row))->toMatchArray([
            'id' => $hall->id,
            'source' => ScheduleImportRowResolutionContext::SOURCE_ORIGINAL_EXACT_MATCH,
            'status' => ScheduleImportRowResolutionContext::STATUS_RESOLVED,
        ])->and($row->fresh()->resolved_lecturer_id)->toBeNull()
            ->and($row->fresh()->resolved_hall_id)->toBeNull();
    } finally {
        @unlink($path);
    }
});

it('treats importer sentinel identity values as missing', function (mixed $value): void {
    [$path, , , , , $row] = effectiveIdentityRow($value, $value);

    try {
        $context = app(ScheduleImportRowResolutionContext::class);

        expect($context->effectiveLecturerResolution($row)['status'])->toBe(ScheduleImportRowResolutionContext::STATUS_MISSING)
            ->and($context->effectiveHallResolution($row)['status'])->toBe(ScheduleImportRowResolutionContext::STATUS_MISSING)
            ->and($context->effectiveLecturerId($row))->toBeNull()
            ->and($context->effectiveHallId($row))->toBeNull();
    } finally {
        @unlink($path);
    }
})->with([null, '', ' ', 0, 0.0, '0', '0.0', '-', 'NaN']);

it('returns ambiguity without selecting the first exact identity match', function (): void {
    [$path, , , , , $row] = effectiveIdentityRow('اسم مكرر', 'HALL-DUP');

    try {
        Lecturer::query()->create(['name' => 'الأول', 'canonical_name' => 'اسم مكرر', 'is_active' => true]);
        Lecturer::query()->create(['name' => 'الثاني', 'canonical_name' => 'اسم مكرر', 'is_active' => true]);
        Hall::query()->create(['code' => 'HALL-DUP', 'name' => 'القاعة الأولى', 'is_active' => true]);
        Hall::query()->create(['code' => 'HALL-2', 'name' => 'HALL-DUP', 'is_active' => true]);
        $context = app(ScheduleImportRowResolutionContext::class);
        $lecturer = $context->effectiveLecturerResolution($row);
        $hall = $context->effectiveHallResolution($row);

        expect($lecturer['id'])->toBeNull()
            ->and($lecturer['status'])->toBe(ScheduleImportRowResolutionContext::STATUS_AMBIGUOUS)
            ->and($lecturer['match_ids'])->toHaveCount(2)
            ->and($hall['id'])->toBeNull()
            ->and($hall['status'])->toBe(ScheduleImportRowResolutionContext::STATUS_AMBIGUOUS)
            ->and($hall['match_ids'])->toHaveCount(2);
    } finally {
        @unlink($path);
    }
});

it('retries with one exact source hall null lecturer and no canonical identity backfill', function (): void {
    [$path, , , , , $row] = effectiveIdentityRow('نتالي محمد موسى', 'F-02A');

    try {
        $hall = Hall::query()->create(['code' => 'F-02A', 'name' => 'F-02A', 'is_active' => true]);
        $issue = ScheduleImportIssue::query()->create([
            'schedule_import_row_id' => $row->id,
            'deduplication_key' => hash('sha256', 'cpfc302-'.$row->id),
            'issue_type' => ScheduleImportIssue::TYPE_NON_AUTHORITATIVE_SUBJECT_CODE,
            'severity' => ScheduleImportIssue::SEVERITY_ERROR,
            'reason_ar' => 'رمز محاط بأقواس',
            'resolution_status' => ScheduleImportIssue::STATUS_UNRESOLVED,
        ]);
        $actor = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        $result = app(ScheduleImportReconciliationService::class)->retryRow($row, $actor);
        $slot = SubjectSectionScheduleSlot::query()->sole();
        $action = ScheduleImportIssueAction::query()->latest('id')->firstOrFail();

        expect($result['created_slot_ids'])->toBe([$slot->id])
            ->and($result['hall_resolution'])->toMatchArray([
                'id' => $hall->id,
                'source' => ScheduleImportRowResolutionContext::SOURCE_ORIGINAL_EXACT_MATCH,
                'status' => ScheduleImportRowResolutionContext::STATUS_RESOLVED,
            ])->and($result['lecturer_resolution']['status'])->toBe(ScheduleImportRowResolutionContext::STATUS_MISSING)
            ->and($slot->hall_id)->toBe($hall->id)
            ->and($slot->lecturer_id)->toBeNull()
            ->and($row->fresh()->resolved_hall_id)->toBeNull()
            ->and($row->fresh()->resolved_lecturer_id)->toBeNull()
            ->and($issue->fresh()->resolution_status)->toBe(ScheduleImportIssue::STATUS_RESOLVED)
            ->and($row->issues()->where('issue_type', ScheduleImportIssue::TYPE_LECTURER_MISSING)->where('resolution_status', ScheduleImportIssue::STATUS_UNRESOLVED)->count())->toBe(1)
            ->and($row->fresh()->current_reconciliation_status)->toBe(ScheduleImportRow::STATUS_UNRESOLVED)
            ->and(data_get($action->new_state, 'resolution.hall.source'))->toBe(ScheduleImportRowResolutionContext::SOURCE_ORIGINAL_EXACT_MATCH)
            ->and(Lecturer::query()->count())->toBe(0)
            ->and(Hall::query()->count())->toBe(1);
    } finally {
        @unlink($path);
    }
});

it('uses the same effective identities for manual weekly-time creation', function (): void {
    [$path, , , , , $row] = effectiveIdentityRow('مدرس موجود', 'EXACT-HALL');

    try {
        $lecturer = Lecturer::query()->create(['name' => 'مدرس موجود', 'canonical_name' => 'مدرس موجود', 'is_active' => true]);
        $hall = Hall::query()->create(['code' => 'EXACT-HALL', 'name' => 'قاعة مطابقة', 'is_active' => true]);
        ScheduleImportIssue::query()->create([
            'schedule_import_row_id' => $row->id,
            'deduplication_key' => hash('sha256', 'no-time-'.$row->id),
            'issue_type' => ScheduleImportIssue::TYPE_NO_WEEKLY_TIME,
            'severity' => ScheduleImportIssue::SEVERITY_WARNING,
            'reason_ar' => 'لا موعد',
            'resolution_status' => ScheduleImportIssue::STATUS_UNRESOLVED,
        ]);
        $actor = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        $result = app(ScheduleImportReconciliationService::class)->addWeeklyTimes($row, [
            ['weekday' => 1, 'start_time' => '12:30', 'end_time' => '14:30'],
        ], $actor);
        $slot = SubjectSectionScheduleSlot::query()->findOrFail($result['created_slot_ids'][0]);

        expect($slot->lecturer_id)->toBe($lecturer->id)
            ->and($slot->hall_id)->toBe($hall->id)
            ->and($row->fresh()->resolved_lecturer_id)->toBeNull()
            ->and($row->fresh()->resolved_hall_id)->toBeNull();
    } finally {
        @unlink($path);
    }
});

it('creates unresolved ambiguity warnings while leaving optional slot identities null', function (): void {
    [$path, , , , , $row] = effectiveIdentityRow('مدرس ملتبس', 'قاعة ملتبسة');

    try {
        Lecturer::query()->create(['name' => 'أ', 'canonical_name' => 'مدرس ملتبس', 'is_active' => true]);
        Lecturer::query()->create(['name' => 'ب', 'canonical_name' => 'مدرس ملتبس', 'is_active' => true]);
        Hall::query()->create(['code' => 'A', 'name' => 'قاعة ملتبسة', 'is_active' => true]);
        Hall::query()->create(['code' => 'B', 'name' => 'قاعة ملتبسة', 'is_active' => true]);
        $actor = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        $result = app(ScheduleImportReconciliationService::class)->retryRow($row, $actor);
        $slot = SubjectSectionScheduleSlot::query()->sole();

        expect($result['lecturer_resolution']['status'])->toBe(ScheduleImportRowResolutionContext::STATUS_AMBIGUOUS)
            ->and($result['hall_resolution']['status'])->toBe(ScheduleImportRowResolutionContext::STATUS_AMBIGUOUS)
            ->and($slot->lecturer_id)->toBeNull()
            ->and($slot->hall_id)->toBeNull()
            ->and($row->issues()->where('issue_type', ScheduleImportIssue::TYPE_LECTURER_AMBIGUOUS)->where('resolution_status', ScheduleImportIssue::STATUS_UNRESOLVED)->count())->toBe(1)
            ->and($row->issues()->where('issue_type', ScheduleImportIssue::TYPE_HALL_AMBIGUOUS)->where('resolution_status', ScheduleImportIssue::STATUS_UNRESOLVED)->count())->toBe(1);
    } finally {
        @unlink($path);
    }
});

it('keeps a mapped row visible and performs the controlled map then retry Filament sequence', function (): void {
    [$path, , $batch, $subject, $section, $row] = effectiveIdentityRow('نتالي محمد موسى', 'F-02A');

    try {
        $row->update(['resolved_subject_id' => null, 'resolved_subject_section_id' => null]);
        $hall = Hall::query()->create(['code' => 'F-02A', 'name' => 'F-02A', 'is_active' => true]);
        ScheduleImportIssue::query()->create([
            'schedule_import_row_id' => $row->id,
            'deduplication_key' => hash('sha256', 'ui-cpfc302-'.$row->id),
            'issue_type' => ScheduleImportIssue::TYPE_NON_AUTHORITATIVE_SUBJECT_CODE,
            'severity' => ScheduleImportIssue::SEVERITY_ERROR,
            'reason_ar' => 'رمز محاط بأقواس',
            'resolution_status' => ScheduleImportIssue::STATUS_UNRESOLVED,
        ]);
        $actor = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $component = Livewire::actingAs($actor)->test(ScheduleImportReconciliationReport::class, ['batch' => $batch->uuid]);

        $component->callTableAction('link-subject', $row, [
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'note' => 'اختبار الربط المتحكم به',
        ]);

        expect($row->fresh()->resolved_subject_id)->toBe($subject->id)
            ->and($row->fresh()->resolved_subject_section_id)->toBe($section->id)
            ->and($component->instance()->tabCounts()['needs_attention'])->toBe(1);

        $component->callTableAction('retry-row', $row->fresh(), ['note' => 'اختبار إعادة المعالجة المتحكم بها']);
        $slot = SubjectSectionScheduleSlot::query()->sole();

        expect($slot->hall_id)->toBe($hall->id)
            ->and($slot->lecturer_id)->toBeNull()
            ->and($row->fresh()->current_reconciliation_status)->toBe(ScheduleImportRow::STATUS_UNRESOLVED)
            ->and($component->instance()->tabCounts()['warnings'])->toBe(1)
            ->and(ScheduleImportIssueAction::query()->count())->toBe(2);
    } finally {
        @unlink($path);
    }
});
