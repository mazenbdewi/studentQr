<?php

use App\Filament\Pages\ManaraScheduleImport;
use App\Filament\Pages\WeeklySchedule;
use App\Models\AcademicTerm;
use App\Models\AppSetting;
use App\Models\ImportBatch;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportRow;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function weeklyScheduleUiAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create([
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
    ]);
    $admin->assignRole('admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    return $admin;
}

/** @return array{AcademicTerm, ImportBatch} */
function weeklyScheduleUiBatch(string $status, array $summary = []): array
{
    $term = AcademicTerm::query()->create([
        'display_name' => 'الفصل الصيفي 2025/2026',
        'canonical_name' => 'الفصل الصيفي 2025/2026',
    ]);
    AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, (string) $term->id);
    $batch = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', "weekly-ui-{$status}"),
        'import_type' => ImportBatch::TYPE_WEEKLY_SCHEDULE,
        'source_filename' => 'summer2026_schedule.xlsx',
        'status' => $status,
        'total_rows' => $summary['total_rows'] ?? 10,
        'imported_rows' => $summary['imported_rows'] ?? 8,
        'rejected_rows' => $summary['rejected_rows'] ?? 2,
        'summary' => [
            'total_rows' => $summary['total_rows'] ?? 10,
            'imported_rows' => $summary['imported_rows'] ?? 8,
            'rejected_rows' => $summary['rejected_rows'] ?? 2,
        ],
        'error_file_path' => $status === ImportBatch::STATUS_COMPLETED_WITH_ERRORS
            ? 'import-errors/manara-schedule-errors-20260719-120000-00000000-0000-4000-8000-000000000000.xlsx'
            : null,
    ]);
    $batch->academicTerms()->attach($term->id, ['row_count' => $batch->imported_rows]);

    return [$term, $batch];
}

function latestScheduleNotification(): array
{
    return collect(session('filament.notifications', []))->last();
}

it('shows a success notification for a completed schedule batch', function (): void {
    [, $batch] = weeklyScheduleUiBatch(ImportBatch::STATUS_COMPLETED);

    Livewire::withQueryParams(['batch' => $batch->uuid])
        ->actingAs(weeklyScheduleUiAdmin())
        ->test(ManaraScheduleImport::class)
        ->assertSee(__('manara-schedule-import.status.completed'));

    $notification = latestScheduleNotification();

    expect($notification['title'])->toBe(__('manara-schedule-import.status.completed'))
        ->and($notification['status'])->toBe('success');
});

it('shows a warning notification with result links for completed with errors', function (): void {
    [$term, $batch] = weeklyScheduleUiBatch(ImportBatch::STATUS_COMPLETED_WITH_ERRORS, [
        'total_rows' => 935,
        'imported_rows' => 873,
        'rejected_rows' => 62,
    ]);
    $subject = Subject::query()->create([
        'code' => 'UI101',
        'name' => 'مقرر واجهة الجدول',
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

    foreach ([1, 2] as $weekday) {
        SubjectSectionScheduleSlot::query()->create([
            'import_batch_id' => $batch->id,
            'academic_term_id' => $term->id,
            'subject_id' => $subject->id,
            'subject_section_id' => $section->id,
            'weekday' => $weekday,
            'start_time' => '08:30:00',
            'end_time' => '10:30:00',
        ]);
    }

    $component = Livewire::withQueryParams(['batch' => $batch->uuid])
        ->actingAs(weeklyScheduleUiAdmin())
        ->test(ManaraScheduleImport::class)
        ->assertSee(__('manara-schedule-import.status.completed_with_errors'))
        ->assertSee(__('manara-schedule-import.open_weekly_schedule'))
        ->assertSee(__('manara-schedule-import.open_reconciliation'))
        ->assertSee(__('manara-schedule-import.weekly_status_imported'))
        ->assertSee(__('manara-schedule-import.dated_status_pending'));

    $notification = latestScheduleNotification();
    $actionLabels = collect($notification['actions'])->pluck('label')->all();

    expect($notification['status'])->toBe('warning')
        ->and($notification['body'])->toContain('2')->toContain('873')->toContain('62')
        ->and($actionLabels)->toContain(
            __('manara-schedule-import.open_weekly_schedule'),
            __('manara-schedule-import.open_reconciliation'),
            __('manara-schedule-import.download_errors'),
        )
        ->and($component->get('weeklyScheduleUrl'))->toContain('weekly-schedule')->toContain($batch->uuid)
        ->and($component->get('reconciliationUrl'))->toContain('schedule-import-reconciliation')->toContain($batch->uuid);
});

it('shows a danger notification only for a failed batch without an imported timetable', function (): void {
    [, $batch] = weeklyScheduleUiBatch(ImportBatch::STATUS_FAILED);

    $component = Livewire::withQueryParams(['batch' => $batch->uuid])
        ->actingAs(weeklyScheduleUiAdmin())
        ->test(ManaraScheduleImport::class)
        ->assertSee(__('manara-schedule-import.status.failed'))
        ->assertDontSee(__('manara-schedule-import.weekly_status_imported'));

    $notification = latestScheduleNotification();

    expect($notification['title'])->toBe(__('manara-schedule-import.status.failed'))
        ->and($notification['status'])->toBe('danger')
        ->and($component->get('weeklyScheduleUrl'))->toBeNull()
        ->and($component->get('reconciliationUrl'))->toBeNull();
});

it('displays recurring weekly slots rather than dated lecture sessions and scopes summary cards', function (): void {
    [$term, $batch] = weeklyScheduleUiBatch(ImportBatch::STATUS_COMPLETED_WITH_ERRORS);
    $subject = Subject::query()->create([
        'code' => 'TIM101',
        'name' => 'مقرر الجدول الأسبوعي',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'is_active' => true,
    ]);
    $theoretical = SubjectSection::query()->create([
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'section_type' => Subject::TYPE_THEORETICAL,
        'code' => 'T1',
        'raw_section_number' => '1',
    ]);
    $practical = SubjectSection::query()->create([
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'section_type' => Subject::TYPE_PRACTICAL,
        'code' => 'P1',
        'raw_section_number' => '1',
    ]);

    foreach ([[1, $theoretical], [6, $practical]] as [$weekday, $section]) {
        SubjectSectionScheduleSlot::query()->create([
            'import_batch_id' => $batch->id,
            'academic_term_id' => $term->id,
            'subject_id' => $subject->id,
            'subject_section_id' => $section->id,
            'weekday' => $weekday,
            'start_time' => '08:30:00',
            'end_time' => '10:30:00',
            'section_capacity' => 25,
            'expected_student_count' => 20,
        ]);
    }

    $row = ScheduleImportRow::query()->create([
        'import_batch_id' => $batch->id,
        'academic_term_id' => $term->id,
        'source_sheet_name' => 'Schedule',
        'source_row_number' => 2,
        'row_fingerprint' => hash('sha256', 'weekly-page-row'),
        'source_payload' => [],
        'normalized_payload' => [],
        'original_import_status' => ScheduleImportRow::ORIGINAL_WARNING_ONLY,
        'current_reconciliation_status' => ScheduleImportRow::STATUS_UNRESOLVED,
    ]);
    ScheduleImportIssue::query()->create([
        'schedule_import_row_id' => $row->id,
        'deduplication_key' => hash('sha256', 'weekly-page-issue'),
        'issue_type' => ScheduleImportIssue::TYPE_HALL_MISSING,
        'severity' => ScheduleImportIssue::SEVERITY_WARNING,
        'reason_ar' => 'القاعة مفقودة',
        'resolution_status' => ScheduleImportIssue::STATUS_UNRESOLVED,
    ]);

    $component = Livewire::withQueryParams(['batch' => $batch->uuid])
        ->actingAs(weeklyScheduleUiAdmin())
        ->test(WeeklySchedule::class)
        ->assertSee('TIM101')
        ->assertSee('T1')
        ->assertSee('P1')
        ->assertSee('الاثنين')
        ->assertSee('السبت')
        ->assertSee(__('weekly-schedule.weekly_status_imported'))
        ->assertSee(__('weekly-schedule.dated_status_pending'));

    expect($component->instance()->summaryCounts())->toMatchArray([
        'total' => 2,
        'subjects' => 1,
        'theoretical_sections' => 1,
        'practical_sections' => 1,
        'needs_review' => 1,
    ])->and($component->instance()->getTable()->getModel())->toBe(SubjectSectionScheduleSlot::class)
        ->and(array_keys($component->instance()->getTable()->getFilters()))->toBe([
            'faculty_id',
            'department_id',
            'subject_id',
            'section_type',
            'lecturer_id',
            'hall_id',
            'weekday',
            'import_batch_id',
        ]);

    $this->assertDatabaseCount('lecture_sessions', 0);
});
