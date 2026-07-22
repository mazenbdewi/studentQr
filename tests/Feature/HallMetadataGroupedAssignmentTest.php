<?php

use App\Exports\HallMetadataReportExport;
use App\Exports\HallMetadataTemplateExport;
use App\Models\AcademicTerm;
use App\Models\Faculty;
use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\Lecturer;
use App\Models\LectureSession;
use App\Models\LectureSessionGenerationRun;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Services\BlockedWeeklySlotReconciliationService;
use App\Services\GroupedHallAssignmentPreparationService;
use App\Services\HallMetadataService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function hallMetadataActor(bool $withWarningPermission = true): User
{
    foreach (['admin', 'manager', 'course_lecturer'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    foreach ([
        'preview grouped hall assignment',
        'confirm grouped hall assignment with warning',
        'preview hall metadata import',
        'import hall metadata',
        'export hall metadata',
        'manage hall metadata',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $actor = User::factory()->create([
        'email' => 'hall-admin-'.uniqid().'@example.test',
        'role' => $withWarningPermission ? 'admin' : 'manager',
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);
    $actor->assignRole($withWarningPermission ? 'admin' : 'manager');
    $actor->givePermissionTo('preview grouped hall assignment');

    if ($withWarningPermission) {
        $actor->givePermissionTo('confirm grouped hall assignment with warning');
    }

    return $actor;
}

function hallFixtureLecturer(string $name = 'مدرس قاعات'): Lecturer
{
    Role::findOrCreate('course_lecturer', 'web');

    $user = User::factory()->create([
        'name' => $name,
        'email' => null,
        'login_username' => 'lec'.random_int(100000, 999999),
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);
    $user->assignRole('course_lecturer');

    return Lecturer::query()->create([
        'user_id' => $user->id,
        'name' => $name,
        'canonical_name' => $name,
        'is_active' => true,
    ]);
}

/** @return array{term: AcademicTerm, batch: ImportBatch, lecturer: Lecturer} */
function hallFixtureBase(): array
{
    $term = AcademicTerm::query()->create([
        'display_name' => 'الفصل الصيفي 2025/2026',
        'canonical_name' => 'summer-2025-2026-'.uniqid(),
        'teaching_start_date' => '2026-07-04',
        'teaching_end_date' => '2026-08-15',
    ]);
    $batch = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'hall-batch-'.uniqid('', true)),
        'import_type' => ImportBatch::TYPE_WEEKLY_SCHEDULE,
        'source_filename' => 'schedule.xlsx',
        'source_fingerprint' => hash('sha256', uniqid('', true)),
        'status' => ImportBatch::STATUS_COMPLETED_WITH_ERRORS,
        'total_rows' => 1,
        'imported_rows' => 1,
    ]);
    $batch->academicTerms()->attach($term->id, ['row_count' => 1]);

    return [
        'term' => $term,
        'batch' => $batch,
        'lecturer' => hallFixtureLecturer(),
    ];
}

function hallFixtureSlot(array $base, string $subjectName, string $sectionCode, int $weekday, string $start, string $end, int $students, ?Hall $hall = null): SubjectSectionScheduleSlot
{
    $subject = Subject::query()->create([
        'code' => 'HALL'.substr(hash('sha1', $subjectName.$sectionCode.uniqid()), 0, 8),
        'name' => $subjectName,
        'subject_type' => str_starts_with($sectionCode, 'P') ? Subject::TYPE_PRACTICAL : Subject::TYPE_THEORETICAL,
        'is_active' => true,
    ]);
    $section = SubjectSection::query()->create([
        'academic_term_id' => $base['term']->id,
        'subject_id' => $subject->id,
        'section_type' => str_starts_with($sectionCode, 'P') ? Subject::TYPE_PRACTICAL : Subject::TYPE_THEORETICAL,
        'code' => $sectionCode,
        'raw_section_number' => preg_replace('/^[TP]/', '', $sectionCode),
        'capacity' => $students,
    ]);

    return SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $base['batch']->id,
        'academic_term_id' => $base['term']->id,
        'subject_id' => $subject->id,
        'subject_section_id' => $section->id,
        'lecturer_id' => $base['lecturer']->id,
        'hall_id' => $hall?->id,
        'weekday' => $weekday,
        'start_time' => $start,
        'end_time' => $end,
        'section_capacity' => $students,
        'expected_student_count' => $students,
    ]);
}

function hallFixtureGenerationRun(AcademicTerm $term, array $slots): void
{
    LectureSessionGenerationRun::query()->create([
        'academic_term_id' => $term->id,
        'teaching_start_date' => $term->teaching_start_date,
        'teaching_end_date' => $term->teaching_end_date,
        'status' => 'completed_with_errors',
        'source_slot_count' => count($slots),
        'candidate_session_count' => 0,
        'created_session_count' => 0,
        'skipped_session_count' => count($slots),
        'blocked_slot_count' => count($slots),
        'conflict_count' => 0,
        'summary' => [
            'error_report' => collect($slots)->map(fn (SubjectSectionScheduleSlot $slot): array => [
                'الموعد الأسبوعي المصدر' => $slot->id,
                'المادة' => $slot->subject->name,
                'الشعبة' => $slot->subjectSection->code,
                'رمز الخطأ' => 'missing_hall',
                'السبب بالعربية' => 'القاعة غير محددة.',
                'الإجراء المقترح' => 'اختيار قاعة.',
            ])->all(),
            'blocked_slots' => collect($slots)->map(fn (SubjectSectionScheduleSlot $slot): array => [
                'source_slot_id' => $slot->id,
                'occurrence_count' => 7,
            ])->all(),
            'conflicts' => [],
        ],
        'started_at' => now(),
        'completed_at' => now(),
    ]);
}

it('adds nullable hall metadata columns while existing halls keep explicit data only', function (): void {
    $hall = Hall::query()->create([
        'code' => 'META-1',
        'name' => 'قاعة اختبار',
        'floor' => 1,
        'is_active' => true,
    ]);

    expect(Schema::hasColumn('halls', 'capacity'))->toBeTrue()
        ->and(Schema::hasColumn('halls', 'hall_type'))->toBeTrue()
        ->and(Schema::hasColumn('halls', 'building_name'))->toBeTrue()
        ->and(Schema::hasColumn('halls', 'faculty_id'))->toBeTrue()
        ->and(Schema::hasColumn('halls', 'notes'))->toBeTrue()
        ->and($hall->fresh()->capacity)->toBeNull()
        ->and($hall->fresh()->hall_type)->toBeNull()
        ->and($hall->fresh()->building_name)->toBeNull();
});

it('exports an Arabic RTL xlsx hall metadata template containing existing halls', function (): void {
    Hall::query()->create(['code' => 'RTL-1', 'name' => 'قاعة عربية', 'floor' => 2, 'is_active' => true]);

    $bytes = Excel::raw(new HallMetadataTemplateExport, ExcelWriter::XLSX);
    $spreadsheet = spreadsheetFromXlsxBytes($bytes);
    $sheet = $spreadsheet->getSheetByName('بيانات القاعات');
    $values = spreadsheetCellValues($spreadsheet);
    exec('git status --short -- "*.xlsx"', $gitStatus);

    expect($bytes)->toStartWith('PK')
        ->and($sheet)->not->toBeNull()
        ->and($sheet->getRightToLeft())->toBeTrue()
        ->and($sheet->getCell('A1')->getValue())->toBe('رمز القاعة')
        ->and($sheet->getCell('C1')->getValue())->toBe('السعة')
        ->and($values)->toContain('RTL-1')
        ->and($spreadsheet->getSheetByName('تعليمات التعبئة')->getRightToLeft())->toBeTrue()
        ->and(trim(implode("\n", $gitStatus)))->toBe('');
});

it('previews and imports hall metadata by exact code without creating or deleting halls', function (): void {
    $faculty = Faculty::query()->create(['name' => 'كلية الاختبار', 'is_active' => true]);
    $hall = Hall::query()->create([
        'code' => 'EXACT-1',
        'name' => 'قاعة أصلية',
        'floor' => 1,
        'capacity' => 20,
        'hall_type' => Hall::TYPE_LECTURE_HALL,
        'building_name' => 'المبنى أ',
        'faculty_id' => $faculty->id,
        'is_active' => true,
        'notes' => 'تبقى',
    ]);
    $beforeCount = Hall::query()->count();
    $rows = [
        ['رمز القاعة' => 'EXACT-1', 'اسم القاعة' => '', 'السعة' => '25', 'نوع القاعة' => 'مخبر', 'المبنى' => '', 'الكلية' => '', 'الطابق' => '', 'فعالة' => '', 'ملاحظات' => ''],
        ['رمز القاعة' => 'MISSING-1', 'اسم القاعة' => 'لا تنشأ'],
        ['رمز القاعة' => 'EXACT-1', 'السعة' => '0'],
        ['رمز القاعة' => 'EXACT-1', 'نوع القاعة' => 'نوع غريب'],
    ];

    $preview = app(HallMetadataService::class)->preview($rows);
    $result = app(HallMetadataService::class)->import([$rows[0]]);
    $fresh = $hall->fresh();
    $successBook = spreadsheetFromXlsxBytes(Excel::raw(HallMetadataReportExport::success($result['success_rows']), ExcelWriter::XLSX));
    $errorBook = spreadsheetFromXlsxBytes(Excel::raw(HallMetadataReportExport::errors($preview['error_rows']), ExcelWriter::XLSX));

    expect($preview['writes_performed'])->toBeFalse()
        ->and($preview['error_rows'])->toHaveCount(3)
        ->and(collect($preview['error_rows'])->pluck('رمز الخطأ')->all())->toContain('hall_not_found', 'invalid_capacity', 'invalid_hall_type')
        ->and($result['writes_performed'])->toBeTrue()
        ->and(Hall::query()->count())->toBe($beforeCount)
        ->and($fresh->name)->toBe('قاعة أصلية')
        ->and($fresh->capacity)->toBe(25)
        ->and($fresh->hall_type)->toBe(Hall::TYPE_LABORATORY)
        ->and($fresh->building_name)->toBe('المبنى أ')
        ->and($fresh->notes)->toBe('تبقى')
        ->and($successBook->getSheetByName('العمليات الناجحة')->getRightToLeft())->toBeTrue()
        ->and($errorBook->getSheetByName('الأخطاء والحالات المستبعدة')->getRightToLeft())->toBeTrue();
});

it('previews grouped hall assignment with capacity type active metadata and conflict guardrails only', function (): void {
    $actor = hallMetadataActor();
    $base = hallFixtureBase();
    $workshopSlotA = hallFixtureSlot($base, 'تدريب في الورشة', 'P1', 4, '08:30:00', '12:30:00', 14);
    $workshopSlotB = hallFixtureSlot($base, 'تدريب في الورشة', 'P1', 4, '12:30:00', '16:30:00', 14);
    $physics = hallFixtureSlot($base, 'فيزياء 2', 'T1', 6, '08:30:00', '10:30:00', 5);
    $sales = hallFixtureSlot($base, 'ترويج المبيعات', 'T1', 6, '09:30:00', '11:30:00', 2);
    hallFixtureGenerationRun($base['term'], [$workshopSlotA, $workshopSlotB, $physics, $sales]);

    $workshopHall = Hall::query()->create(['code' => 'W-1', 'name' => 'ورشة آمنة', 'floor' => 0, 'capacity' => 14, 'hall_type' => Hall::TYPE_WORKSHOP, 'is_active' => true]);
    $lectureHall = Hall::query()->create(['code' => 'L-1', 'name' => 'قاعة نظرية', 'floor' => 1, 'capacity' => 30, 'hall_type' => Hall::TYPE_LECTURE_HALL, 'is_active' => true]);
    $smallWorkshop = Hall::query()->create(['code' => 'S-1', 'name' => 'ورشة صغيرة', 'floor' => 1, 'capacity' => 3, 'hall_type' => Hall::TYPE_WORKSHOP, 'is_active' => true]);
    $inactiveHall = Hall::query()->create(['code' => 'I-1', 'name' => 'قاعة مغلقة', 'floor' => 1, 'capacity' => 30, 'hall_type' => Hall::TYPE_LECTURE_HALL, 'is_active' => false]);
    $unknownHall = Hall::query()->create(['code' => 'U-1', 'name' => 'قاعة ناقصة البيانات', 'floor' => 1, 'is_active' => true]);

    $service = app(GroupedHallAssignmentPreparationService::class);
    $groups = collect($service->groups())->keyBy('label');
    $workshopKey = $groups->first(fn (array $group): bool => str_contains($group['label'], 'Group A'))['key'];
    $physicsKey = $groups->first(fn (array $group): bool => str_contains($group['label'], 'Group C'))['key'];
    $salesKey = $groups->first(fn (array $group): bool => str_contains($group['label'], 'Group E'))['key'];
    $countsBefore = [
        'slots' => SubjectSectionScheduleSlot::query()->count(),
        'sessions' => LectureSession::query()->count(),
        'halls' => Hall::query()->count(),
    ];

    $safe = $service->preview($workshopKey, $workshopHall->id, $actor, false, null);
    $wrongType = $service->preview($workshopKey, $lectureHall->id, $actor, false, null);
    $small = $service->preview($workshopKey, $smallWorkshop->id, $actor, false, null);
    $inactive = $service->preview($physicsKey, $inactiveHall->id, $actor, false, null);
    $unknownWithoutAck = $service->preview($workshopKey, $unknownHall->id, $actor, false, null);
    $unknownWithAck = $service->preview($workshopKey, $unknownHall->id, $actor, true, 'تم التحقق إدارياً.');
    $planned = $service->previewPlannedAssignments([$physicsKey => $lectureHall->id, $salesKey => $lectureHall->id]);
    $activeOptions = collect(app(BlockedWeeklySlotReconciliationService::class)->hallOptions())->pluck('id')->all();

    expect($groups)->toHaveCount(3)
        ->and($safe['classification'])->toBe(GroupedHallAssignmentPreparationService::CLASS_WARNING)
        ->and($safe['confirm_enabled'])->toBeFalse()
        ->and($safe['expected_additional_sessions'])->toBe(12)
        ->and($safe['rows'])->toHaveCount(2)
        ->and($wrongType['classification'])->toBe(GroupedHallAssignmentPreparationService::CLASS_WRONG_TYPE)
        ->and($wrongType['confirm_enabled'])->toBeFalse()
        ->and($small['classification'])->toBe(GroupedHallAssignmentPreparationService::CLASS_INSUFFICIENT_CAPACITY)
        ->and($inactive['classification'])->toBe(GroupedHallAssignmentPreparationService::CLASS_INACTIVE)
        ->and($activeOptions)->not->toContain($inactiveHall->id)
        ->and($unknownWithoutAck['acknowledgement_text'])->toBe(GroupedHallAssignmentPreparationService::ADMIN_ACKNOWLEDGEMENT)
        ->and($unknownWithoutAck['confirm_enabled'])->toBeFalse()
        ->and($unknownWithAck['confirm_enabled'])->toBeTrue()
        ->and($planned['confirm_enabled'])->toBeFalse()
        ->and($planned['conflicts'][0]['dimension'])->toBe('planned_same_hall_overlap')
        ->and(SubjectSectionScheduleSlot::query()->count())->toBe($countsBefore['slots'])
        ->and(LectureSession::query()->count())->toBe($countsBefore['sessions'])
        ->and(Hall::query()->count())->toBe($countsBefore['halls']);
});

it('requires warning-confirmation permission and never authorizes managers by default', function (): void {
    $actor = hallMetadataActor(withWarningPermission: false);
    $base = hallFixtureBase();
    $slot = hallFixtureSlot($base, 'فيزياء 2', 'T1', 6, '08:30:00', '10:30:00', 5);
    hallFixtureGenerationRun($base['term'], [$slot]);
    $unknownHall = Hall::query()->create(['code' => 'WARN-1', 'name' => 'قاعة تحتاج تحقق', 'floor' => 1, 'is_active' => true]);
    $groupKey = app(GroupedHallAssignmentPreparationService::class)->groups()[0]['key'];

    $preview = app(GroupedHallAssignmentPreparationService::class)->preview($groupKey, $unknownHall->id, $actor, true, 'تحقق إداري.');

    $manager = User::factory()->create(['role' => 'manager', 'type' => 'admin', 'status' => 'active', 'is_active' => true]);
    Role::findOrCreate('manager', 'web');
    $manager->assignRole('manager');

    expect($preview['confirm_enabled'])->toBeFalse()
        ->and(Gate::forUser($manager)->allows('manage hall metadata'))->toBeFalse()
        ->and(Gate::forUser($manager)->allows('preview grouped hall assignment'))->toBeFalse();
});
