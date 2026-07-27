<?php

use App\Exports\WeeklyScheduleReportExport;
use App\Filament\Pages\AcademicTermManagement;
use App\Filament\Pages\LecturerAccountPreparation;
use App\Filament\Pages\ManaraEnrollmentImport;
use App\Filament\Pages\ManaraScheduleImport;
use App\Filament\Pages\ScheduleImportReconciliationIndex;
use App\Filament\Pages\WeeklySchedule;
use App\Filament\Pages\WeeklyScheduleReports;
use App\Models\AcademicTerm;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\ImportBatch;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Services\WeeklyScheduleReportService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;

function weeklyReportsAdmin(): User
{
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $user = User::factory()->create(['role' => 'super_admin', 'type' => 'admin', 'status' => 'active']);
    $user->assignRole('super-admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    return $user;
}

/** @return array{AcademicTerm, ImportBatch, Faculty, Department, Subject, SubjectSection} */
function weeklyReportsFixture(string $code = 'RPT101'): array
{
    $term = AcademicTerm::firstOrCreate(
        ['canonical_name' => 'الفصل الصيفي 2025/2026'],
        ['display_name' => 'الفصل الصيفي 2025/2026'],
    );
    $batch = ImportBatch::create([
        'deduplication_key' => hash('sha256', "reports-{$code}"),
        'import_type' => ImportBatch::TYPE_WEEKLY_SCHEDULE,
        'source_filename' => 'summer2026_schedule.xlsx',
        'status' => ImportBatch::STATUS_COMPLETED,
        'imported_rows' => 1,
    ]);
    $batch->academicTerms()->attach($term->id, ['row_count' => 1]);
    $faculty = Faculty::create(['name' => "كلية {$code}", 'is_active' => true]);
    $department = Department::create(['faculty_id' => $faculty->id, 'name' => "اختصاص {$code}", 'is_active' => true]);
    $subject = Subject::create(['department_id' => $department->id, 'code' => $code, 'name' => "مادة {$code}", 'subject_type' => Subject::TYPE_THEORETICAL, 'is_active' => true]);
    $section = SubjectSection::create(['academic_term_id' => $term->id, 'subject_id' => $subject->id, 'section_type' => Subject::TYPE_THEORETICAL, 'code' => 'T1', 'raw_section_number' => '1']);

    SubjectSectionScheduleSlot::create([
        'import_batch_id' => $batch->id, 'academic_term_id' => $term->id, 'subject_id' => $subject->id,
        'subject_section_id' => $section->id, 'weekday' => 1, 'start_time' => '08:30:00', 'end_time' => '10:30:00',
        'expected_student_count' => 18,
    ]);

    return [$term, $batch, $faculty, $department, $subject, $section];
}

it('renders the import processing panel only for the import target with readable light and dark styles', function (): void {
    $view = file_get_contents(resource_path('views/filament/pages/manara-schedule-import.blade.php'));

    expect($view)->toContain('wire:target="import"')
        ->toContain('wire:loading.delay.class.remove="hidden"')
        ->toContain('class="hidden items-start gap-3')
        ->toContain('bg-primary-50')
        ->toContain('text-primary-950')
        ->toContain('dark:bg-primary-950')
        ->toContain('dark:text-primary-100')
        ->toContain('role="status"')
        ->toContain('aria-live="polite"')
        ->and(__('manara-schedule-import.import_loading_wait'))->toBe('يرجى الانتظار وعدم إغلاق الصفحة حتى اكتمال العملية.');
});

it('organizes all weekly schedule pages in one navigation group without UUID labels', function (): void {
    app()->setLocale('ar');

    expect(WeeklySchedule::getNavigationGroup())->toBe('برنامج الدوام الأسبوعي')
        ->and(WeeklyScheduleReports::getNavigationGroup())->toBe('برنامج الدوام الأسبوعي')
        ->and(ScheduleImportReconciliationIndex::getNavigationGroup())->toBe('برنامج الدوام الأسبوعي')
        ->and([
            WeeklySchedule::getNavigationLabel(),
            WeeklyScheduleReports::getNavigationLabel(), ScheduleImportReconciliationIndex::getNavigationLabel(),
        ])->toBe([
            'عرض برنامج الدوام الأسبوعي',
            'تقارير برنامج الدوام الأسبوعي', 'تقرير مراجعة الاستيراد',
        ]);

    expect(ManaraEnrollmentImport::getNavigationGroup())->toBe('الإعداد والتهيئة الأولية')
        ->and(ManaraScheduleImport::getNavigationGroup())->toBe('الإعداد والتهيئة الأولية')
        ->and(AcademicTermManagement::getNavigationGroup())->toBe('الإعداد والتهيئة الأولية')
        ->and(LecturerAccountPreparation::getNavigationGroup())->toBe('الإعداد والتهيئة الأولية')
        ->and(ManaraEnrollmentImport::getNavigationLabel())->toBe('المرحلة الأولى: استيراد البيانات')
        ->and(ManaraScheduleImport::getNavigationLabel())->toBe('المرحلة الثانية: استيراد برنامج الدوام الأسبوعي')
        ->and(AcademicTermManagement::getNavigationSort())->toBe(1)
        ->and(ManaraEnrollmentImport::getNavigationSort())->toBe(2)
        ->and(ManaraScheduleImport::getNavigationSort())->toBe(3)
        ->and(LecturerAccountPreparation::getNavigationSort())->toBe(4)
        ->and(__('filament-dashboard.navigation.initial_setup', [], 'en'))->toBe('Initial Setup');
});

it('uses readable light and dark styles for selected weekly schedule report filters', function (): void {
    $view = file_get_contents(resource_path('views/filament/pages/weekly-schedule-reports.blade.php'));

    expect($view)
        ->toContain('border border-gray-300 bg-gray-100 text-gray-950')
        ->toContain('dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100')
        ->toContain('rounded-full border border-gray-300 bg-gray-100')
        ->toContain('text-xs font-medium text-gray-950');
});

it('scopes report previews and counts using schedule slot filters without touching lecture sessions', function (): void {
    [$term, $batch, $faculty, $department, $subject] = weeklyReportsFixture();
    weeklyReportsFixture('RPT202');
    $before = [SubjectSectionScheduleSlot::count(), DB::table('lecture_sessions')->count()];

    $service = app(WeeklyScheduleReportService::class);
    $query = $service->slotQuery(['academic_term_id' => $term->id, 'import_batch_id' => $batch->id, 'faculty_id' => $faculty->id, 'department_id' => $department->id, 'subject_id' => $subject->id]);

    expect($query->getModel())->toBeInstanceOf(SubjectSectionScheduleSlot::class)
        ->and($query->count())->toBe(1)
        ->and($service->summary(['import_batch_id' => $batch->id])['total'])->toBe(1)
        ->and([SubjectSectionScheduleSlot::count(), DB::table('lecture_sessions')->count()])->toBe($before);

    Livewire::withQueryParams(['batch' => $batch->uuid])
        ->actingAs(weeklyReportsAdmin())
        ->test(WeeklyScheduleReports::class)
        ->assertSet('importBatchId', (string) $batch->id)
        ->assertSee('RPT101')
        ->assertCountTableRecords(1);
});

it('defaults a single schedule batch but never guesses the newest when several batches exist', function (): void {
    [, $firstBatch] = weeklyReportsFixture();
    $admin = weeklyReportsAdmin();

    Livewire::actingAs($admin)
        ->test(WeeklyScheduleReports::class)
        ->assertSet('importBatchId', (string) $firstBatch->id);

    weeklyReportsFixture('RPT404');

    Livewire::actingAs($admin)
        ->test(WeeklyScheduleReports::class)
        ->assertSet('importBatchId', null);

    Livewire::actingAs($admin)
        ->test(ScheduleImportReconciliationIndex::class)
        ->assertSet('batchId', null)
        ->assertSee(__('weekly-schedule-reports.select_batch'));
});

it('exports only filtered slots with Arabic weekday labels and no database IDs', function (): void {
    [, $batch] = weeklyReportsFixture();
    weeklyReportsFixture('RPT303');
    app()->setLocale('ar');

    $binary = Excel::raw(new WeeklyScheduleReportExport(WeeklyScheduleReportService::COMPREHENSIVE, ['import_batch_id' => $batch->id]), \Maatwebsite\Excel\Excel::XLSX);
    $path = tempnam(sys_get_temp_dir(), 'weekly-report-').'.xlsx';
    file_put_contents($path, $binary);

    try {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $values = $sheet->toArray();
        expect($values[3])->toBe(__('weekly-schedule-reports.headings.comprehensive'))
            ->and($values[4][3])->toBe('RPT101')
            ->and($values[4][9])->toBe('الاثنين')
            ->and($values[4][10])->toBe('08:30')
            ->and(collect($values)->flatten()->contains('RPT303'))->toBeFalse();
    } finally {
        @unlink($path);
    }
});

it('passes current weekly table filters to report exports', function (): void {
    [$term, $batch, $faculty, $department, $subject] = weeklyReportsFixture();

    $component = Livewire::withQueryParams(['batch' => $batch->uuid])
        ->actingAs(weeklyReportsAdmin())
        ->test(WeeklySchedule::class)
        ->set('tableFilters.faculty_id.value', (string) $faculty->id)
        ->set('tableFilters.department_id.value', (string) $department->id)
        ->set('tableFilters.subject_id.value', (string) $subject->id);

    expect($component->instance()->currentReportFilters())->toMatchArray([
        'academic_term_id' => $term->id, 'import_batch_id' => $batch->id,
        'faculty_id' => $faculty->id, 'department_id' => $department->id, 'subject_id' => $subject->id,
    ]);
});

it('prevents users without report export permission from downloading reports', function (): void {
    weeklyReportsFixture();
    Role::firstOrCreate(['name' => 'course_lecturer', 'guard_name' => 'web']);
    $user = User::factory()->create(['role' => 'course_lecturer', 'type' => 'lecturer', 'status' => 'active']);
    $user->assignRole('course_lecturer');

    $this->actingAs($user)
        ->get(route('admin.weekly-schedule-reports.excel', ['type' => WeeklyScheduleReportService::COMPREHENSIVE]))
        ->assertForbidden();
});
