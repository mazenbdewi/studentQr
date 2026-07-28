<?php

use App\Exceptions\ScheduleAssignmentConflictException;
use App\Exports\BlockedWeeklySlotsExport;
use App\Filament\Pages\ScheduleImportIssues;
use App\Models\AcademicTerm;
use App\Models\AppSetting;
use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\Lecturer;
use App\Models\LectureSession;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportIssueAction;
use App\Models\ScheduleImportRow;
use App\Models\ScheduleImportRowTimeOverride;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Services\BlockedWeeklySlotReconciliationService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;

function blockedSlotActor(): User
{
    Role::findOrCreate('course_lecturer', 'web');
    Role::findOrCreate('super-admin', 'web');

    $actor = User::factory()->create([
        'login_username' => 'blocked-slot-admin',
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);

    $actor->assignRole('super-admin');

    return $actor;
}

/** @return array{term: AcademicTerm, batch: ImportBatch, subject: Subject, section: SubjectSection} */
function blockedSlotCatalog(string $suffix = ''): array
{
    $term = AcademicTerm::query()->create([
        'display_name' => 'الفصل الصيفي 2025/2026 '.$suffix,
        'canonical_name' => 'summer-2025-2026-'.$suffix.uniqid(),
        'teaching_start_date' => '2026-07-04',
        'teaching_end_date' => '2026-08-15',
    ]);
    $batch = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'blocked-bulk-'.$suffix.uniqid('', true)),
        'import_type' => ImportBatch::TYPE_WEEKLY_SCHEDULE,
        'source_filename' => 'schedule.xlsx',
        'source_fingerprint' => hash('sha256', uniqid('', true)),
        'status' => ImportBatch::STATUS_COMPLETED_WITH_ERRORS,
        'total_rows' => 1,
        'imported_rows' => 1,
    ]);
    $batch->academicTerms()->attach($term->id, ['row_count' => 1]);
    $subject = Subject::query()->create([
        'code' => 'BLK'.$suffix.uniqid(),
        'name' => 'مادة محجوبة',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'is_active' => true,
    ]);
    $section = SubjectSection::query()->create([
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'section_type' => Subject::TYPE_THEORETICAL,
        'code' => 'T1',
        'raw_section_number' => '1',
    ]);

    return compact('term', 'batch', 'subject', 'section');
}

/** @return array{row: ScheduleImportRow, slot: SubjectSectionScheduleSlot} */
function blockedSlotFixture(array $overrides = []): array
{
    $catalog = $overrides['catalog'] ?? blockedSlotCatalog(substr(hash('sha1', uniqid('', true)), 0, 6));
    $rowNumber = $overrides['row_number'] ?? random_int(100, 999);
    $sourceLecturer = $overrides['source_lecturer'] ?? '';
    $sourceHall = $overrides['source_hall'] ?? '';
    $weekday = $overrides['weekday'] ?? 6;
    $start = $overrides['start_time'] ?? '08:30:00';
    $end = $overrides['end_time'] ?? '10:30:00';
    $lecturerId = $overrides['lecturer_id'] ?? null;
    $hallId = $overrides['hall_id'] ?? null;
    $issues = $overrides['issues'] ?? [ScheduleImportIssue::TYPE_LECTURER_MISSING, ScheduleImportIssue::TYPE_HALL_MISSING];

    $slot = SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $catalog['batch']->id,
        'academic_term_id' => $catalog['term']->id,
        'subject_id' => $catalog['subject']->id,
        'subject_section_id' => $catalog['section']->id,
        'lecturer_id' => $lecturerId,
        'hall_id' => $hallId,
        'weekday' => $weekday,
        'start_time' => $start,
        'end_time' => $end,
        'raw_teacher_name' => $sourceLecturer,
        'raw_hall_name' => $sourceHall,
    ]);
    $row = ScheduleImportRow::query()->create([
        'import_batch_id' => $catalog['batch']->id,
        'academic_term_id' => $catalog['term']->id,
        'source_sheet_name' => 'Schedule',
        'source_row_number' => $rowNumber,
        'row_fingerprint' => hash('sha256', 'row-'.$rowNumber.uniqid('', true)),
        'source_payload' => [
            'lecturer' => $sourceLecturer,
            'hall' => $sourceHall,
            'time' => substr($start, 0, 5).' - '.substr($end, 0, 5),
        ],
        'normalized_payload' => [
            'lecturer_name' => $sourceLecturer,
            'hall_name' => $sourceHall,
            'section_type' => 'T',
            'section_code' => 'T1',
        ],
        'original_import_status' => ScheduleImportRow::ORIGINAL_PARTIALLY_IMPORTED,
        'current_reconciliation_status' => ScheduleImportRow::STATUS_UNRESOLVED,
        'resolved_subject_id' => $catalog['subject']->id,
        'resolved_subject_section_id' => $catalog['section']->id,
        'import_result' => ['slot_ids' => [$slot->id]],
    ]);

    foreach ($issues as $issueType) {
        ScheduleImportIssue::query()->create([
            'schedule_import_row_id' => $row->id,
            'deduplication_key' => hash('sha256', 'blocked-'.$row->id.'-'.$issueType),
            'issue_type' => $issueType,
            'severity' => ScheduleImportIssue::SEVERITY_WARNING,
            'reason_ar' => $issueType,
            'resolution_status' => ScheduleImportIssue::STATUS_UNRESOLVED,
        ]);
    }

    return compact('row', 'slot');
}

function readyLecturer(string $name = 'مدرس جاهز'): Lecturer
{
    $user = User::factory()->create([
        'name' => $name,
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
        'email' => null,
        'is_active' => true,
    ]);
}

function blockedHall(string $code = 'H-101'): Hall
{
    return Hall::query()->create(['code' => $code, 'name' => 'قاعة '.$code, 'floor' => '1', 'is_active' => true]);
}

it('previews bulk reconciliation without writes and validates one term and batch', function (): void {
    $actor = blockedSlotActor();
    $first = blockedSlotFixture(['row_number' => 10]);
    $second = blockedSlotFixture(['row_number' => 11]);
    $lecturer = readyLecturer();
    $hall = blockedHall();
    $before = [
        'rows' => ScheduleImportRow::query()->count(),
        'slots' => SubjectSectionScheduleSlot::query()->count(),
        'actions' => ScheduleImportIssueAction::query()->count(),
    ];

    $preview = app(BlockedWeeklySlotReconciliationService::class)->preview(
        [$first['slot']->id],
        ['action' => BlockedWeeklySlotReconciliationService::ACTION_ASSIGN_LECTURER_AND_HALL, 'lecturer_id' => $lecturer->id, 'hall_id' => $hall->id],
        $actor,
    );
    $mixedPreview = app(BlockedWeeklySlotReconciliationService::class)->preview(
        [$first['slot']->id, $second['slot']->id],
        ['action' => BlockedWeeklySlotReconciliationService::ACTION_ASSIGN_LECTURER, 'lecturer_id' => $lecturer->id],
        $actor,
    );

    expect($preview['writes_performed'])->toBeFalse()
        ->and($preview['confirm_enabled'])->toBeTrue()
        ->and($preview['readiness']['generation_remains_separate_action'])->toBeTrue()
        ->and($mixedPreview['confirm_enabled'])->toBeFalse()
        ->and(implode("\n", $mixedPreview['blocking_errors']))->toContain('دفعة استيراد')
        ->and(ScheduleImportRow::query()->count())->toBe($before['rows'])
        ->and(SubjectSectionScheduleSlot::query()->count())->toBe($before['slots'])
        ->and(ScheduleImportIssueAction::query()->count())->toBe($before['actions']);
});

it('stores canonical lecturer and hall overrides while preserving raw source values', function (): void {
    $actor = blockedSlotActor();
    $fixture = blockedSlotFixture(['source_lecturer' => 'مدرس خام', 'source_hall' => 'قاعة خام']);
    $lecturer = readyLecturer('مدرس معتمد');
    $hall = blockedHall('H-202');

    $result = app(BlockedWeeklySlotReconciliationService::class)->apply(
        [$fixture['slot']->id],
        ['action' => BlockedWeeklySlotReconciliationService::ACTION_ASSIGN_LECTURER_AND_HALL, 'lecturer_id' => $lecturer->id, 'hall_id' => $hall->id, 'note' => 'معالجة اختبارية'],
        $actor,
    );

    $row = $fixture['row']->fresh();
    $slot = $fixture['slot']->fresh();

    expect($result['status'])->toBe('completed')
        ->and($row->source_payload['lecturer'])->toBe('مدرس خام')
        ->and($row->source_payload['hall'])->toBe('قاعة خام')
        ->and($row->resolved_lecturer_id)->toBe($lecturer->id)
        ->and($row->resolved_hall_id)->toBe($hall->id)
        ->and($slot->lecturer_id)->toBe($lecturer->id)
        ->and($slot->hall_id)->toBe($hall->id)
        ->and(ScheduleImportIssueAction::query()->count())->toBeGreaterThan(0);
});

it('blocks direct reconciliation for slots that already own generated sessions', function (): void {
    $actor = blockedSlotActor();
    $fixture = blockedSlotFixture();
    $lecturer = readyLecturer();
    $hall = blockedHall();
    LectureSession::query()->create([
        'academic_term_id' => $fixture['slot']->academic_term_id,
        'subject_id' => $fixture['slot']->subject_id,
        'subject_section_id' => $fixture['slot']->subject_section_id,
        'subject_section_schedule_slot_id' => $fixture['slot']->id,
        'lecturer_id' => $lecturer->user_id,
        'hall_id' => $hall->id,
        'session_date' => '2026-07-04',
        'start_time' => '08:30:00',
        'end_time' => '10:30:00',
    ]);

    $preview = app(BlockedWeeklySlotReconciliationService::class)->preview(
        [$fixture['slot']->id],
        ['action' => BlockedWeeklySlotReconciliationService::ACTION_ASSIGN_LECTURER, 'lecturer_id' => $lecturer->id],
        $actor,
    );

    expect($preview['confirm_enabled'])->toBeFalse()
        ->and(implode("\n", $preview['blocking_errors']))->toContain('جلسات مولدة');
});

it('creates one exact lecturer identity from source for two slots and does not create a user or email', function (): void {
    $actor = blockedSlotActor();
    $catalog = blockedSlotCatalog('shared');
    $first = blockedSlotFixture(['catalog' => $catalog, 'row_number' => 891, 'source_lecturer' => 'نتالي محمد موسى', 'issues' => [ScheduleImportIssue::TYPE_LECTURER_MISSING], 'hall_id' => blockedHall('F-02A')->id]);
    $second = blockedSlotFixture(['catalog' => $catalog, 'row_number' => 892, 'source_lecturer' => 'نتالي محمد موسى', 'issues' => [ScheduleImportIssue::TYPE_LECTURER_MISSING], 'hall_id' => blockedHall('F-03A')->id, 'start_time' => '10:30:00', 'end_time' => '12:30:00']);
    $userCount = User::query()->count();

    $result = app(BlockedWeeklySlotReconciliationService::class)->apply(
        [$first['slot']->id, $second['slot']->id],
        ['action' => BlockedWeeklySlotReconciliationService::ACTION_CREATE_LECTURER_FROM_SOURCE, 'note' => 'تأكيد الاسم من المصدر'],
        $actor,
    );

    $lecturers = Lecturer::query()->where('name', 'نتالي محمد موسى')->get();

    expect($result['status'])->toBe('completed')
        ->and($lecturers)->toHaveCount(1)
        ->and($lecturers->first()->email)->toBeNull()
        ->and($lecturers->first()->user_id)->toBeNull()
        ->and(User::query()->count())->toBe($userCount)
        ->and($first['slot']->fresh()->lecturer_id)->toBe($lecturers->first()->id)
        ->and($second['slot']->fresh()->lecturer_id)->toBe($lecturers->first()->id);
});

it('accepts the real weekly importer teacher_name source key for lecturer identity creation', function (): void {
    $actor = blockedSlotActor();
    $fixture = blockedSlotFixture(['source_lecturer' => '', 'issues' => [ScheduleImportIssue::TYPE_LECTURER_MISSING]]);
    $source = $fixture['row']->source_payload;
    $normalized = $fixture['row']->normalized_payload;
    unset($source['lecturer'], $normalized['lecturer_name']);
    $source['teacher_name'] = 'نتالي محمد موسى';
    $normalized['teacher_name'] = 'نتالي محمد موسى';
    $normalized['teacher_name_source'] = 'نتالي محمد موسى';
    $fixture['row']->update(['source_payload' => $source, 'normalized_payload' => $normalized]);

    $preview = app(BlockedWeeklySlotReconciliationService::class)->preview(
        [$fixture['slot']->id],
        ['action' => BlockedWeeklySlotReconciliationService::ACTION_CREATE_LECTURER_FROM_SOURCE],
        $actor,
    );

    expect($preview['confirm_enabled'])->toBeTrue()
        ->and($preview['source_lecturer_name'])->toBe('نتالي محمد موسى')
        ->and($preview['rows'][0]['raw_lecturer_value'])->toBe('نتالي محمد موسى');
});

it('resolves only corrected missing issues and leaves unrelated issues open', function (): void {
    $actor = blockedSlotActor();
    $fixture = blockedSlotFixture();
    $lecturer = readyLecturer();

    app(BlockedWeeklySlotReconciliationService::class)->apply(
        [$fixture['slot']->id],
        ['action' => BlockedWeeklySlotReconciliationService::ACTION_ASSIGN_LECTURER, 'lecturer_id' => $lecturer->id],
        $actor,
    );

    expect($fixture['row']->issues()->where('issue_type', ScheduleImportIssue::TYPE_LECTURER_MISSING)->sole()->resolution_status)->toBe(ScheduleImportIssue::STATUS_RESOLVED)
        ->and($fixture['row']->issues()->where('issue_type', ScheduleImportIssue::TYPE_HALL_MISSING)->sole()->resolution_status)->toBe(ScheduleImportIssue::STATUS_UNRESOLVED);
});

it('uses a per-slot safe unit and keeps successful slots when another slot fails', function (): void {
    $actor = blockedSlotActor();
    $catalog = blockedSlotCatalog('partial');
    $successful = blockedSlotFixture(['catalog' => $catalog, 'row_number' => 20, 'issues' => [ScheduleImportIssue::TYPE_LECTURER_MISSING], 'start_time' => '08:30:00']);
    $failed = blockedSlotFixture(['catalog' => $catalog, 'row_number' => 21, 'issues' => [], 'start_time' => '10:30:00', 'end_time' => '12:30:00']);
    $lecturer = readyLecturer();

    $result = app(BlockedWeeklySlotReconciliationService::class)->apply(
        [$successful['slot']->id, $failed['slot']->id],
        ['action' => BlockedWeeklySlotReconciliationService::ACTION_ASSIGN_LECTURER, 'lecturer_id' => $lecturer->id],
        $actor,
    );

    expect($result['status'])->toBe('completed_with_errors')
        ->and($successful['slot']->fresh()->lecturer_id)->toBe($lecturer->id)
        ->and($failed['slot']->fresh()->lecturer_id)->toBeNull();
});

it('rolls back partial records inside one selected slot when the combined action fails', function (): void {
    $actor = blockedSlotActor();
    $fixture = blockedSlotFixture(['issues' => [ScheduleImportIssue::TYPE_LECTURER_MISSING]]);
    $lecturer = readyLecturer();
    $hall = blockedHall();

    $result = app(BlockedWeeklySlotReconciliationService::class)->apply(
        [$fixture['slot']->id],
        ['action' => BlockedWeeklySlotReconciliationService::ACTION_ASSIGN_LECTURER_AND_HALL, 'lecturer_id' => $lecturer->id, 'hall_id' => $hall->id],
        $actor,
    );

    expect($result['status'])->toBe('completed_with_errors')
        ->and($fixture['slot']->fresh()->lecturer_id)->toBeNull()
        ->and($fixture['row']->fresh()->resolved_lecturer_id)->toBeNull();
});

it('detects new lecturer hall and section overlaps in preview', function (string $dimension): void {
    $actor = blockedSlotActor();
    $catalog = blockedSlotCatalog($dimension);
    $lecturer = readyLecturer('مدرس تعارض '.$dimension);
    $hall = blockedHall('C-'.$dimension);
    $fixture = blockedSlotFixture(['catalog' => $catalog, 'issues' => [ScheduleImportIssue::TYPE_LECTURER_MISSING, ScheduleImportIssue::TYPE_HALL_MISSING]]);
    $otherSection = SubjectSection::query()->create([
        'academic_term_id' => $catalog['term']->id,
        'subject_id' => $catalog['subject']->id,
        'section_type' => Subject::TYPE_THEORETICAL,
        'code' => 'T2',
        'raw_section_number' => '2',
    ]);
    SubjectSectionScheduleSlot::query()->create([
        'import_batch_id' => $catalog['batch']->id,
        'academic_term_id' => $catalog['term']->id,
        'subject_id' => $catalog['subject']->id,
        'subject_section_id' => $dimension === 'section' ? $catalog['section']->id : $otherSection->id,
        'lecturer_id' => $dimension === 'lecturer' ? $lecturer->id : null,
        'hall_id' => $dimension === 'hall' ? $hall->id : null,
        'weekday' => 6,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
    ]);

    $preview = app(BlockedWeeklySlotReconciliationService::class)->preview(
        [$fixture['slot']->id],
        ['action' => BlockedWeeklySlotReconciliationService::ACTION_ASSIGN_LECTURER_AND_HALL, 'lecturer_id' => $lecturer->id, 'hall_id' => $hall->id],
        $actor,
    );

    expect($preview['confirm_enabled'])->toBeFalse()
        ->and(collect($preview['new_conflicts'])->pluck('dimension'))->toContain($dimension);
})->with(['lecturer', 'hall', 'section']);

it('stores manual time override and preserves original imported time text', function (): void {
    $actor = blockedSlotActor();
    $lecturer = readyLecturer();
    $hall = blockedHall();
    $fixture = blockedSlotFixture([
        'lecturer_id' => $lecturer->id,
        'hall_id' => $hall->id,
        'source_lecturer' => 'مدرس أصلي',
        'source_hall' => 'قاعة أصلية',
        'issues' => [ScheduleImportIssue::TYPE_DUPLICATE_CONFLICT],
    ]);

    app(BlockedWeeklySlotReconciliationService::class)->apply(
        [$fixture['slot']->id],
        ['action' => BlockedWeeklySlotReconciliationService::ACTION_CHANGE_TIME, 'weekday' => 6, 'start_time' => '12:30', 'end_time' => '14:30', 'note' => 'تعديل تعارض'],
        $actor,
    );

    expect(ScheduleImportRowTimeOverride::query()->where('schedule_import_row_id', $fixture['row']->id)->count())->toBe(1)
        ->and($fixture['row']->fresh()->source_payload['time'])->toBe('08:30 - 10:30')
        ->and($fixture['slot']->fresh()->start_time)->toBe('12:30:00');
});

it('batch-scoped exclusion requires a reason, writes audit, and does not delete the slot', function (): void {
    $actor = blockedSlotActor();
    $fixture = blockedSlotFixture();

    $blocked = app(BlockedWeeklySlotReconciliationService::class)->preview(
        [$fixture['slot']->id],
        ['action' => BlockedWeeklySlotReconciliationService::ACTION_EXCLUDE_FROM_CURRENT_BATCH, 'reason' => ''],
        $actor,
    );
    $result = app(BlockedWeeklySlotReconciliationService::class)->apply(
        [$fixture['slot']->id],
        ['action' => BlockedWeeklySlotReconciliationService::ACTION_EXCLUDE_FROM_CURRENT_BATCH, 'reason' => 'قرار إداري مؤقت'],
        $actor,
    );

    expect($blocked['confirm_enabled'])->toBeFalse()
        ->and($result['status'])->toBe('completed')
        ->and(SubjectSectionScheduleSlot::query()->whereKey($fixture['slot']->id)->exists())->toBeTrue()
        ->and($fixture['row']->fresh()->current_reconciliation_status)->toBe(ScheduleImportRow::STATUS_EXCLUDED_FROM_BATCH_SCHEDULE)
        ->and(ScheduleImportIssueAction::query()->where('action', ScheduleImportIssueAction::ACTION_EXCLUDE_FROM_BATCH_SCHEDULE)->exists())->toBeTrue();
});

it('conflict comparison is read-only and does not choose a winner automatically', function (): void {
    $service = app(BlockedWeeklySlotReconciliationService::class);
    $before = SubjectSectionScheduleSlot::query()->count();

    $comparison = $service->conflictComparison(844);

    expect($comparison['automatic_winner_selected'])->toBeFalse()
        ->and($comparison['writes_performed'])->toBeFalse()
        ->and($comparison['available_actions'])->toContain('تغيير المدرس', 'تغيير القاعة', 'تغيير وقت البداية والنهاية', 'استبعاد أحد الموعدين من برنامج هذا الفصل')
        ->and(SubjectSectionScheduleSlot::query()->count())->toBe($before);
});

it('exports blocked-slot report as rtl xlsx with the proposed treatments worksheet', function (): void {
    $rows = [[
        'رقم الموعد الأسبوعي' => 891,
        'رقم صف Excel' => 379,
        'المادة' => 'مصطلحات علمية باللغة الإنكليزية للصيدلة',
        'الشعبة' => 'T1',
        'المدرس' => 'غير محدد',
        'القاعة' => 'F-02A',
        'اليوم' => 'السبت',
        'وقت البداية' => '08:30',
        'وقت النهاية' => '10:30',
        'المشكلات' => 'لم يتم تحديد المدرس',
        'رموز المشكلات' => ['missing_lecturer_identity'],
        'عدد الجلسات المتأثرة' => 6,
        'الإجراء المقترح' => 'إنشاء هوية مدرس من قيمة المصدر',
    ]];
    $treatments = app(BlockedWeeklySlotReconciliationService::class)->suggestedTreatmentRows($rows);
    $bytes = Excel::raw(new BlockedWeeklySlotsExport($rows, [], $treatments), ExcelWriter::XLSX);
    $path = tempnam(sys_get_temp_dir(), 'blocked-slots-export-');
    file_put_contents($path, $bytes);
    $spreadsheet = IOFactory::load($path);
    @unlink($path);
    $values = [];
    foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
        foreach ($worksheet->toArray() as $row) {
            foreach ($row as $value) {
                if ($value !== null && $value !== '') {
                    $values[] = (string) $value;
                }
            }
        }
    }

    expect(substr($bytes, 0, 2))->toBe('PK')
        ->and($spreadsheet->getSheetNames())->toContain('الخانات المحجوبة', 'تفاصيل التعارضات', 'المعالجات المقترحة')
        ->and($spreadsheet->getSheetByName('المعالجات المقترحة')->getRightToLeft())->toBeTrue()
        ->and($values)->toContain('رقم الموعد الأسبوعي', 'القرار المطلوب')
        ->and(trim(shell_exec('git status --short -- "*.xlsx"') ?? ''))->toBe('');
});

it('keeps generation separate and leaves protected structural counts untouched in sqlite', function (): void {
    $actor = blockedSlotActor();
    $fixture = blockedSlotFixture();
    $lecturer = readyLecturer();
    $before = [
        'sessions' => LectureSession::query()->count(),
        'slots' => SubjectSectionScheduleSlot::query()->count(),
        'attendances' => DB::table('attendances')->count(),
    ];

    $preview = app(BlockedWeeklySlotReconciliationService::class)->preview(
        [$fixture['slot']->id],
        ['action' => BlockedWeeklySlotReconciliationService::ACTION_ASSIGN_LECTURER, 'lecturer_id' => $lecturer->id],
        $actor,
    );

    expect(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:')
        ->and($preview['readiness']['generation_remains_separate_action'])->toBeTrue()
        ->and(LectureSession::query()->count())->toBe($before['sessions'])
        ->and(SubjectSectionScheduleSlot::query()->count())->toBe($before['slots'])
        ->and(DB::table('attendances')->count())->toBe($before['attendances']);
});

it('rejects a conflicting lecturer assignment with structured details and no write', function (): void {
    $actor = blockedSlotActor();
    $catalog = blockedSlotCatalog('assignment-conflict');
    $target = blockedSlotFixture(['catalog' => $catalog, 'start_time' => '08:30:00', 'end_time' => '10:30:00']);
    $lecturer = readyLecturer();
    blockedSlotFixture([
        'catalog' => $catalog,
        'row_number' => 1001,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'lecturer_id' => $lecturer->id,
        'issues' => [],
    ]);

    expect(fn () => app(BlockedWeeklySlotReconciliationService::class)->apply(
        [$target['slot']->id],
        ['action' => BlockedWeeklySlotReconciliationService::ACTION_ASSIGN_LECTURER, 'lecturer_id' => $lecturer->id],
        $actor,
    ))->toThrow(ScheduleAssignmentConflictException::class);

    try {
        app(BlockedWeeklySlotReconciliationService::class)->apply(
            [$target['slot']->id],
            ['action' => BlockedWeeklySlotReconciliationService::ACTION_ASSIGN_LECTURER, 'lecturer_id' => $lecturer->id],
            $actor,
        );
    } catch (ScheduleAssignmentConflictException $exception) {
        expect($exception->conflictType)->toBe('lecturer')
            ->and($exception->selectedResourceId)->toBe($lecturer->id)
            ->and($exception->conflicts[0])->toMatchArray([
                'conflictType' => 'lecturer', 'selectedResourceId' => $lecturer->id,
                'weekday' => 6, 'startTime' => '09:00', 'endTime' => '11:00',
            ]);
    }

    expect($target['slot']->fresh()->lecturer_id)->toBeNull();
});

it('keeps the lecturer modal actionable after a conflict and accepts a replacement', function (): void {
    $actor = blockedSlotActor();
    $catalog = blockedSlotCatalog('livewire-assignment-conflict');
    AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, (string) $catalog['term']->id);
    $target = blockedSlotFixture(['catalog' => $catalog, 'start_time' => '08:30:00', 'end_time' => '10:30:00']);
    $conflictingLecturer = readyLecturer();
    $availableLecturer = readyLecturer();
    $conflictingSection = SubjectSection::query()->create([
        'academic_term_id' => $catalog['term']->id,
        'subject_id' => $catalog['subject']->id,
        'section_type' => Subject::TYPE_THEORETICAL,
        'code' => 'T2',
        'raw_section_number' => '2',
    ]);
    blockedSlotFixture([
        'catalog' => [...$catalog, 'section' => $conflictingSection],
        'row_number' => 1002,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'lecturer_id' => $conflictingLecturer->id,
        'issues' => [],
    ]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $component = Livewire::actingAs($actor)
        ->test(ScheduleImportIssues::class)
        ->call('openResolution', $target['slot']->id, 'lecturer')
        ->set('selectedLecturerId', $conflictingLecturer->id)
        ->call('applyResolution')
        ->assertHasErrors('selectedLecturerId')
        ->assertSet('selectedSlotId', $target['slot']->id)
        ->assertSet('resolutionType', 'lecturer')
        ->assertSee('حفظ وإعادة التحقق')
        ->set('selectedLecturerId', $availableLecturer->id)
        ->assertHasNoErrors('selectedLecturerId')
        ->assertSet('assignmentConflict', null)
        ->call('applyResolution')
        ->assertHasNoErrors()
        ->assertSet('selectedSlotId', null)
        ->assertSet('resolutionType', null);

    expect($target['slot']->fresh()->lecturer_id)->toBe($availableLecturer->id);
});

it('keeps the hall modal actionable after a conflict and accepts a replacement', function (): void {
    $actor = blockedSlotActor();
    $catalog = blockedSlotCatalog('livewire-hall-conflict');
    AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, (string) $catalog['term']->id);
    $target = blockedSlotFixture(['catalog' => $catalog, 'start_time' => '08:30:00', 'end_time' => '10:30:00']);
    $conflictingHall = blockedHall('H-CONFLICT');
    $availableHall = blockedHall('H-AVAILABLE');
    $conflictingSection = SubjectSection::query()->create([
        'academic_term_id' => $catalog['term']->id,
        'subject_id' => $catalog['subject']->id,
        'section_type' => Subject::TYPE_THEORETICAL,
        'code' => 'T2',
        'raw_section_number' => '2',
    ]);
    blockedSlotFixture([
        'catalog' => [...$catalog, 'section' => $conflictingSection],
        'row_number' => 1003,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'hall_id' => $conflictingHall->id,
        'issues' => [],
    ]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($actor)
        ->test(ScheduleImportIssues::class)
        ->call('openResolution', $target['slot']->id, 'hall')
        ->set('selectedHallId', $conflictingHall->id)
        ->call('applyResolution')
        ->assertHasErrors('selectedHallId')
        ->assertSet('selectedSlotId', $target['slot']->id)
        ->assertSet('resolutionType', 'hall')
        ->assertSee('حفظ وإعادة التحقق')
        ->set('selectedHallId', $availableHall->id)
        ->assertHasNoErrors('selectedHallId')
        ->assertSet('assignmentConflict', null)
        ->call('applyResolution')
        ->assertHasNoErrors()
        ->assertSet('selectedSlotId', null)
        ->assertSet('resolutionType', null);

    expect($target['slot']->fresh()->hall_id)->toBe($availableHall->id);
});
