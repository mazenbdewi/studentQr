<?php

use App\Filament\Pages\ManaraScheduleImport;
use App\Filament\Pages\ScheduleImportReconciliationReport;
use App\Models\AcademicTerm;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportRow;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use App\Services\ScheduleImportReconciliationBuilder;
use App\Services\ScheduleImportReconciliationService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

require_once __DIR__.'/../Support/ScheduleImportReconciliationFixtures.php';

beforeEach(function (): void {
    Role::findOrCreate('super-admin', 'web');
});

function catalogReviewFixture(): array
{
    $path = reconciliationWorkbook([
        ['UNKNOWN101', 'T', 1, null, null, 30, 20, 'مقرر غير مطابق', null, null, null, '08:30AM-10:30AM', '-', '-'],
        ['SCH101', 'T', 9, null, null, 30, 20, 'مقرر الجدولة', null, null, null, '-', '10:30AM-12:30PM', '-'],
    ]);
    [$term, , $batch, $subject, $section] = reconciliationSource($path);
    app(ScheduleImportReconciliationBuilder::class)->build($batch, $path);
    $subjectRow = ScheduleImportRow::query()->where('source_row_number', 2)->sole();
    $sectionRow = ScheduleImportRow::query()->where('source_row_number', 3)->sole();
    $admin = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
    $admin->assignRole('super-admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    return compact('path', 'term', 'batch', 'subject', 'section', 'subjectRow', 'sectionRow', 'admin');
}

it('shows raw missing subject and section rows with batch-scoped filters and direct result links', function (): void {
    $fixture = catalogReviewFixture();

    try {
        $component = Livewire::withQueryParams(['issue' => 'missing_subject'])
            ->actingAs($fixture['admin'])
            ->test(ScheduleImportReconciliationReport::class, ['batch' => $fixture['batch']->uuid])
            ->assertSee('UNKNOWN101')
            ->assertSee('مقرر غير مطابق')
            ->assertSee('08:30AM-10:30AM')
            ->assertSee('لم يتم العثور على مقرر مطابق للرمز UNKNOWN101')
            ->assertSee(__('schedule-import-reconciliation.actions.link_subject'))
            ->assertSee(__('schedule-import-reconciliation.actions.exclude_from_batch_schedule'));

        expect($component->get('issueFilter'))->toBe('missing_subject')
            ->and($component->instance()->issueFilterCounts())->toMatchArray([
                'missing_subject' => 1,
                'missing_section' => 1,
            ]);

        $importPage = Livewire::withQueryParams(['batch' => $fixture['batch']->uuid])
            ->actingAs($fixture['admin'])
            ->test(ManaraScheduleImport::class);
        expect($importPage->instance()->reconciliationFilterUrl('missing_subject'))
            ->toContain($fixture['batch']->uuid)
            ->toContain('issue=missing_subject');
    } finally {
        @unlink($fixture['path']);
    }
});

it('re-evaluates the exact section after subject mapping and safely creates one weekly slot', function (): void {
    $fixture = catalogReviewFixture();

    try {
        $service = app(ScheduleImportReconciliationService::class);
        $mapped = $service->mapSubjectOnly($fixture['subjectRow'], $fixture['subject']->id, $fixture['admin']);

        expect($mapped->resolved_subject_id)->toBe($fixture['subject']->id)
            ->and($mapped->resolved_subject_section_id)->toBe($fixture['section']->id)
            ->and($mapped->issues()->whereIn('issue_type', [
                ScheduleImportIssue::TYPE_SUBJECT_NOT_FOUND,
                ScheduleImportIssue::TYPE_SUBJECT_NOT_UNIQUE,
            ])->where('resolution_status', ScheduleImportIssue::STATUS_UNRESOLVED)->count())->toBe(0);

        $first = $service->retryRow($mapped, $fixture['admin']);
        $second = $service->retryRow($mapped->fresh(), $fixture['admin']);

        expect($first['created_slot_ids'])->toHaveCount(1)
            ->and($second['created_slot_ids'])->toBe([])
            ->and($second['already_existing_slot_ids'])->toHaveCount(1)
            ->and(SubjectSectionScheduleSlot::query()->where('import_batch_id', $fixture['batch']->id)->count())->toBe(1);

        $report = Livewire::actingAs($fixture['admin'])
            ->test(ScheduleImportReconciliationReport::class, ['batch' => $fixture['batch']->uuid]);
        expect($report->instance()->issueFilterCounts())->toMatchArray([
            'missing_subject' => 0,
            'missing_section' => 1,
            'missing_lecturer' => 2,
            'missing_hall' => 2,
        ]);
    } finally {
        @unlink($fixture['path']);
    }
});

it('moves a subject-mapped row to section review when no exact section exists', function (): void {
    $fixture = catalogReviewFixture();

    try {
        $otherSubject = Subject::query()->create([
            'code' => 'OTHER101',
            'name' => 'مقرر بلا شعبة مطابقة',
            'subject_type' => Subject::TYPE_THEORETICAL,
            'is_active' => true,
        ]);
        $mapped = app(ScheduleImportReconciliationService::class)
            ->mapSubjectOnly($fixture['subjectRow'], $otherSubject->id, $fixture['admin']);

        expect($mapped->resolved_subject_id)->toBe($otherSubject->id)
            ->and($mapped->resolved_subject_section_id)->toBeNull()
            ->and($mapped->issues()->where('issue_type', ScheduleImportIssue::TYPE_SECTION_NOT_FOUND)
                ->where('resolution_status', ScheduleImportIssue::STATUS_UNRESOLVED)->count())->toBe(1);
    } finally {
        @unlink($fixture['path']);
    }
});

it('accepts only sections from the resolved subject and explicit batch academic term', function (): void {
    $fixture = catalogReviewFixture();

    try {
        $otherSubject = Subject::query()->create([
            'code' => 'OTHER202', 'name' => 'مقرر آخر', 'subject_type' => Subject::TYPE_THEORETICAL, 'is_active' => true,
        ]);
        $wrongSubjectSection = SubjectSection::query()->create([
            'academic_term_id' => $fixture['term']->id, 'subject_id' => $otherSubject->id,
            'section_type' => Subject::TYPE_THEORETICAL, 'code' => 'T9', 'raw_section_number' => '9',
        ]);
        $otherTerm = AcademicTerm::query()->create(['display_name' => 'فصل تاريخي', 'canonical_name' => 'historical-catalog-review']);
        $wrongTermSection = SubjectSection::query()->create([
            'academic_term_id' => $otherTerm->id, 'subject_id' => $fixture['subject']->id,
            'section_type' => Subject::TYPE_THEORETICAL, 'code' => 'T9', 'raw_section_number' => '9',
        ]);
        $service = app(ScheduleImportReconciliationService::class);

        expect(fn () => $service->mapSection($fixture['sectionRow'], $wrongSubjectSection->id, $fixture['admin']))
            ->toThrow(RuntimeException::class, __('schedule-import-reconciliation.validation.section_scope'))
            ->and(fn () => $service->mapSection($fixture['sectionRow']->fresh(), $wrongTermSection->id, $fixture['admin']))
            ->toThrow(RuntimeException::class, __('schedule-import-reconciliation.validation.section_scope'));
    } finally {
        @unlink($fixture['path']);
    }
});

it('excludes an unresolved raw catalog row without creating academic data or weekly slots', function (): void {
    $fixture = catalogReviewFixture();

    try {
        $issue = $fixture['subjectRow']->issues()->where('issue_type', ScheduleImportIssue::TYPE_SUBJECT_NOT_FOUND)->sole();
        $result = app(ScheduleImportReconciliationService::class)->excludeFromBatchSchedule(
            $issue,
            $fixture['admin'],
            'هذا الصف غير مطلوب في برنامج دوام هذه الدفعة.',
        );

        expect($result['status'])->toBe('applied')
            ->and($result['subject'])->toBeNull()
            ->and($result['section'])->toBeNull()
            ->and($fixture['subjectRow']->fresh()->current_reconciliation_status)->toBe(ScheduleImportRow::STATUS_EXCLUDED_FROM_BATCH_SCHEDULE)
            ->and($fixture['subjectRow']->fresh()->issues()->where('resolution_status', ScheduleImportIssue::STATUS_UNRESOLVED)->count())->toBe(0)
            ->and(SubjectSectionScheduleSlot::query()->count())->toBe(0);
    } finally {
        @unlink($fixture['path']);
    }
});

it('uses remote subject search and does not preload the catalog selectors', function (): void {
    $fixture = catalogReviewFixture();

    try {
        $component = Livewire::actingAs($fixture['admin'])
            ->test(ScheduleImportReconciliationReport::class, ['batch' => $fixture['batch']->uuid]);
        $instance = $component->instance();
        $instance->mountTableAction('link-subject', (string) $fixture['subjectRow']->id);
        $action = $instance->getMountedTableAction();
        $subjectField = collect($instance->getMountedTableActionForm($action)?->getFlatComponents(withHidden: true) ?? [])
            ->first(fn (object $field): bool => method_exists($field, 'getName') && $field->getName() === 'subject_id');

        expect($subjectField)->not->toBeNull()
            ->and($subjectField->isSearchable())->toBeTrue()
            ->and($subjectField->isPreloaded())->toBeFalse();

        $sectionComponent = Livewire::actingAs($fixture['admin'])
            ->test(ScheduleImportReconciliationReport::class, ['batch' => $fixture['batch']->uuid]);
        $sectionInstance = $sectionComponent->instance();
        $sectionInstance->mountTableAction('link-section', (string) $fixture['sectionRow']->id);
        $sectionAction = $sectionInstance->getMountedTableAction();
        $sectionField = collect($sectionInstance->getMountedTableActionForm($sectionAction)?->getFlatComponents(withHidden: true) ?? [])
            ->first(fn (object $field): bool => method_exists($field, 'getName') && $field->getName() === 'section_id');
        expect($sectionField)->not->toBeNull()
            ->and($sectionField->isSearchable())->toBeTrue()
            ->and($sectionField->isPreloaded())->toBeFalse();
    } finally {
        @unlink($fixture['path']);
    }
});

it('keeps an explicit historical batch isolated from rows in another batch and term', function (): void {
    $fixture = catalogReviewFixture();
    $otherPath = reconciliationWorkbook([
        ['OTHER-MISSING', 'T', 1, null, null, 20, 10, 'مقرر تاريخي', null, null, null, '08:30AM-10:30AM', '-', '-'],
    ]);

    try {
        [, , $otherBatch] = reconciliationSource($otherPath, suffix: '-other-batch');
        app(ScheduleImportReconciliationBuilder::class)->build($otherBatch, $otherPath);
        $component = Livewire::withQueryParams(['issue' => 'missing_subject'])
            ->actingAs($fixture['admin'])
            ->test(ScheduleImportReconciliationReport::class, ['batch' => $fixture['batch']->uuid])
            ->assertSee('UNKNOWN101')
            ->assertDontSee('OTHER-MISSING');

        expect($component->instance()->issueFilterCounts()['missing_subject'])->toBe(1)
            ->and($component->get('batchRecord')->id)->toBe($fixture['batch']->id);
    } finally {
        @unlink($fixture['path']);
        @unlink($otherPath);
    }
});

it('provides Arabic and English catalog review labels', function (): void {
    foreach (['ar', 'en'] as $locale) {
        app()->setLocale($locale);
        expect(__('schedule-import-reconciliation.filters.missing_subject'))->not->toBe('schedule-import-reconciliation.filters.missing_subject')
            ->and(__('schedule-import-reconciliation.filters.missing_section'))->not->toBe('schedule-import-reconciliation.filters.missing_section')
            ->and(__('manara-schedule-import.review_missing_subject', ['count' => 43]))->toContain('43');
    }
});
