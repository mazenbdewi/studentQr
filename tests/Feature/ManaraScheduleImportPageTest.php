<?php

use App\Filament\Pages\ManaraScheduleImport;
use App\Models\AcademicTerm;
use App\Models\ImportBatch;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\User;
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
