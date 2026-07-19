<?php

use App\Filament\Pages\ManaraScheduleImport;
use App\Models\AcademicTerm;
use App\Models\ImportBatch;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\User;
use App\Services\ScheduleAcademicTermResolver;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

require_once __DIR__.'/../Support/ScheduleImportReconciliationFixtures.php';

it('shows direct upload progress and no academic term selector', function (): void {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $user = User::factory()->create([
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
    ]);
    $user->assignRole('super-admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($user)
        ->test(ManaraScheduleImport::class)
        ->call('import')
        ->assertHasFormErrors(['file'])
        ->assertSee(__('manara-schedule-import.upload_loading'))
        ->assertSee(__('manara-schedule-import.import_loading'))
        ->assertDontSee('academic_term_id');
});

it('stores the original schedule client filename separately from the retained private path', function (): void {
    $path = reconciliationWorkbook([
        ['SCH101', 'T', 1, null, null, 20, 18, 'مقرر الجدولة', null, null, null, '08:30AM-10:30AM', '-', '-'],
    ]);

    try {
        $term = AcademicTerm::query()->create(['display_name' => 'الفصل الصيفي 2025/2026', 'canonical_name' => 'الفصل الصيفي 2025/2026']);
        $source = ImportBatch::query()->create([
            'deduplication_key' => hash('sha256', 'page-source'), 'import_type' => ImportBatch::TYPE_ENROLLMENTS,
            'source_filename' => 'enrollments.xlsx', 'status' => ImportBatch::STATUS_COMPLETED, 'imported_rows' => 1,
        ]);
        $source->academicTerms()->attach($term->id, ['row_count' => 1]);
        $subject = Subject::query()->create(['code' => 'SCH101', 'name' => 'مقرر الجدولة', 'subject_type' => Subject::TYPE_THEORETICAL, 'is_active' => true]);
        SubjectSection::query()->create(['academic_term_id' => $term->id, 'subject_id' => $subject->id, 'section_type' => Subject::TYPE_THEORETICAL, 'code' => 'T1', 'raw_section_number' => '1']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $user = User::factory()->create(['role' => 'super_admin', 'type' => 'admin', 'status' => 'active']);
        $user->assignRole('super-admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $content = file_get_contents($path);

        Livewire::withQueryParams(['source_batch' => $source->uuid])
            ->actingAs($user)
            ->test(ManaraScheduleImport::class)
            ->set('data.file', [UploadedFile::fake()->createWithContent('summer2026_schedule.xlsx', $content)])
            ->call('import');

        $batch = ImportBatch::query()->where('import_type', ImportBatch::TYPE_WEEKLY_SCHEDULE)->sole();
        expect($batch->source_filename)->toBe('summer2026_schedule.xlsx')
            ->and($batch->source_file_path)->not->toBeNull()
            ->and($batch->source_file_path)->not->toBe($batch->source_filename);
    } finally {
        @unlink($path);
    }
});

it('automatically resolves the only eligible enrollment batch on direct access', function (): void {
    $term = AcademicTerm::query()->create([
        'display_name' => 'الفصل الصيفي 2025/2026',
        'canonical_name' => 'الفصل الصيفي 2025/2026',
    ]);
    $source = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'direct-only-source'),
        'import_type' => ImportBatch::TYPE_ENROLLMENTS,
        'source_filename' => 'enrollments.xlsx',
        'status' => ImportBatch::STATUS_COMPLETED,
        'imported_rows' => 10,
    ]);
    $source->academicTerms()->attach($term->id, ['row_count' => 10]);

    Livewire::actingAs(manaraSchedulePageAdmin())
        ->test(ManaraScheduleImport::class)
        ->assertSet('sourceBatchReady', true)
        ->assertSet('sourceBatchUuid', $source->uuid)
        ->assertSet('resolvedAcademicTermName', $term->display_name)
        ->assertSee(__('manara-schedule-import.source_batch_resolved'))
        ->assertDontSee('لا توجد دفعة تسجيل مكتملة ومتوافقة مع جميع مقررات وشعب ملف الجدول');
});

it('blocks direct access safely when multiple eligible enrollment batches exist', function (): void {
    $term = AcademicTerm::query()->create([
        'display_name' => 'الفصل الصيفي 2025/2026',
        'canonical_name' => 'الفصل الصيفي 2025/2026',
    ]);

    foreach (['first', 'second'] as $identity) {
        $source = ImportBatch::query()->create([
            'deduplication_key' => hash('sha256', "direct-{$identity}-source"),
            'import_type' => ImportBatch::TYPE_ENROLLMENTS,
            'source_filename' => "{$identity}.xlsx",
            'status' => ImportBatch::STATUS_COMPLETED,
            'imported_rows' => 10,
        ]);
        $source->academicTerms()->attach($term->id, ['row_count' => 10]);
    }

    Livewire::actingAs(manaraSchedulePageAdmin())
        ->test(ManaraScheduleImport::class)
        ->assertSet('sourceBatchReady', false)
        ->assertSet('sourceBatchUuid', null)
        ->assertSee('توجد أكثر من دفعة تسجيل طلاب مؤهلة')
        ->assertSee(__('manara-schedule-import.source_batch_unavailable'));
});

it('blocks direct access clearly when no eligible enrollment batch exists', function (): void {
    Livewire::actingAs(manaraSchedulePageAdmin())
        ->test(ManaraScheduleImport::class)
        ->assertSet('sourceBatchReady', false)
        ->assertSet('sourceBatchUuid', null)
        ->assertSee('لا توجد دفعة تسجيل طلاب مكتملة ومؤهلة')
        ->assertSee(__('manara-schedule-import.source_batch_unavailable'));
});

it('reopens a persisted schedule result without invoking compatibility resolution', function (): void {
    $term = AcademicTerm::query()->create([
        'display_name' => 'الفصل الصيفي 2025/2026',
        'canonical_name' => 'الفصل الصيفي 2025/2026',
    ]);
    $source = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'reopen-source'),
        'import_type' => ImportBatch::TYPE_ENROLLMENTS,
        'source_filename' => 'enrollments.xlsx',
        'status' => ImportBatch::STATUS_COMPLETED,
        'imported_rows' => 10,
    ]);
    $source->academicTerms()->attach($term->id, ['row_count' => 10]);
    $schedule = ImportBatch::query()->create([
        'deduplication_key' => hash('sha256', 'reopen-schedule'),
        'import_type' => ImportBatch::TYPE_WEEKLY_SCHEDULE,
        'source_filename' => 'schedule.xlsx',
        'source_import_batch_id' => $source->id,
        'status' => ImportBatch::STATUS_COMPLETED_WITH_ERRORS,
        'imported_rows' => 8,
        'rejected_rows' => 2,
        'summary' => ['imported_rows' => 8, 'rejected_rows' => 2],
    ]);
    $schedule->academicTerms()->attach($term->id, ['row_count' => 8]);
    $resolver = Mockery::mock(ScheduleAcademicTermResolver::class);
    $resolver->shouldNotReceive('resolve');
    app()->instance(ScheduleAcademicTermResolver::class, $resolver);

    Livewire::withQueryParams(['batch' => $schedule->uuid])
        ->actingAs(manaraSchedulePageAdmin())
        ->test(ManaraScheduleImport::class)
        ->assertSet('sourceBatchUuid', $source->uuid)
        ->assertSet('resultStatus', ImportBatch::STATUS_COMPLETED_WITH_ERRORS)
        ->assertSet('resultHasPersistedSchedule', true)
        ->assertSet('sourceResolutionError', null)
        ->assertSee(__('manara-schedule-import.open_weekly_schedule'))
        ->assertSee(__('manara-schedule-import.open_reconciliation'));
});

function manaraSchedulePageAdmin(): User
{
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $user = User::factory()->create([
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
    ]);
    $user->assignRole('super-admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    return $user;
}
