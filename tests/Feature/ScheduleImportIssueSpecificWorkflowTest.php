<?php

use App\Filament\Pages\ScheduleImportReconciliationReport;
use App\Models\AcademicTerm;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportRow;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\User;
use App\Policies\ScheduleImportIssuePolicy;
use App\Services\ScheduleImportReconciliationService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/../Support/ScheduleImportReconciliationFixtures.php';

function issueSpecificRow(array $issueTypes): array
{
    $path = reconciliationWorkbook([]);
    [$term, , $batch, $subject, $section] = reconciliationSource($path);
    $row = ScheduleImportRow::query()->create([
        'import_batch_id' => $batch->id,
        'academic_term_id' => $term->id,
        'source_sheet_name' => 'Schedule',
        'source_row_number' => 2,
        'row_fingerprint' => hash('sha256', uniqid('issue-row-', true)),
        'source_payload' => ['subject_code' => 'UNKNOWN', 'section_type' => 'T', 'section_number' => 1],
        'normalized_payload' => ['subject_code_key' => 'unknown', 'section_type' => 'T', 'section_code' => 'T1'],
        'original_import_status' => ScheduleImportRow::ORIGINAL_REJECTED,
        'current_reconciliation_status' => ScheduleImportRow::STATUS_UNRESOLVED,
    ]);

    foreach ($issueTypes as $index => $type) {
        ScheduleImportIssue::query()->create([
            'schedule_import_row_id' => $row->id,
            'deduplication_key' => hash('sha256', $row->id.'|'.$type.'|'.$index),
            'issue_type' => $type,
            'severity' => ScheduleImportIssue::SEVERITY_ERROR,
            'reason_ar' => $type,
            'resolution_status' => ScheduleImportIssue::STATUS_UNRESOLVED,
        ]);
    }

    return [$path, $term, $batch, $subject, $section, $row];
}

it('stores one canonical catalog resolution for every issue on a row', function (): void {
    [$path, $term, , $subject, $section, $row] = issueSpecificRow([
        ScheduleImportIssue::TYPE_SUBJECT_NOT_FOUND,
        ScheduleImportIssue::TYPE_SECTION_NOT_FOUND,
    ]);

    try {
        $super = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
        $service = app(ScheduleImportReconciliationService::class);
        $service->mapSubject($row, $subject->id, $section->id, $super);
        $service->mapSection($row->fresh(), $section->id, $super);

        expect($row->fresh()->resolved_subject_id)->toBe($subject->id)
            ->and($row->fresh()->resolved_subject_section_id)->toBe($section->id)
            ->and($row->issues()->whereNotNull('resolved_subject_id')->count())->toBe(0)
            ->and($row->issues()->whereNotNull('resolved_subject_section_id')->count())->toBe(0)
            ->and($row->issues()->pluck('resolution_status')->unique()->all())->toBe([ScheduleImportIssue::STATUS_RESOLVED]);

        $otherSubject = Subject::query()->create(['code' => 'OTHER', 'name' => 'مادة أخرى', 'subject_type' => Subject::TYPE_THEORETICAL, 'is_active' => true]);
        $otherSection = SubjectSection::query()->create(['academic_term_id' => $term->id, 'subject_id' => $otherSubject->id, 'section_type' => Subject::TYPE_THEORETICAL, 'code' => 'T1', 'raw_section_number' => '1']);
        $row->issues()->where('issue_type', ScheduleImportIssue::TYPE_SECTION_NOT_FOUND)->update(['resolution_status' => ScheduleImportIssue::STATUS_UNRESOLVED]);
        expect(fn () => $service->mapSection($row->fresh(), $otherSection->id, $super))->toThrow(RuntimeException::class)
            ->and($row->fresh()->resolved_subject_id)->toBe($subject->id)
            ->and($row->fresh()->resolved_subject_section_id)->toBe($section->id);

        $otherTerm = AcademicTerm::query()->create(['display_name' => 'فصل آخر', 'canonical_name' => 'فصل آخر']);
        $crossTerm = SubjectSection::query()->create(['academic_term_id' => $otherTerm->id, 'subject_id' => $subject->id, 'section_type' => Subject::TYPE_THEORETICAL, 'code' => 'T2', 'raw_section_number' => '2']);
        expect(fn () => $service->mapSection($row->fresh(), $crossTerm->id, $super))->toThrow(RuntimeException::class);
    } finally {
        @unlink($path);
    }
});

it('declares exact issue-specific permissions for admin and uses the super-admin bypass', function (): void {
    $permissions = [
        'view schedule-import reconciliation',
        'resolve schedule-import subject mapping',
        'resolve schedule-import section mapping',
        'assign schedule-import weekly time',
        'assign schedule-import lecturer',
        'create schedule-import lecturer identity',
        'assign schedule-import hall',
        'create schedule-import hall',
        'resolve schedule-import conflict',
        'ignore schedule-import issues',
        'retry schedule-import rows',
        'export schedule-import reconciliation',
    ];

    $seeder = file_get_contents(database_path('seeders/RolesAndPermissionsSeeder.php'));

    foreach ($permissions as $permission) {
        expect($seeder)->toContain("'{$permission}'");
    }

    expect($seeder)->toContain("Role::findByName('admin', 'web')->givePermissionTo(\$reconciliationPermissions)")
        ->not->toContain("'resolve schedule-import issues'")
        ->not->toContain("Role::findByName('super-admin', 'web')->givePermissionTo(\$reconciliationPermissions)")
        ->not->toContain("Role::findByName('manager', 'web')->givePermissionTo(\$reconciliationPermissions)")
        ->not->toContain("Role::findByName('course_lecturer', 'web')->givePermissionTo(\$reconciliationPermissions)");

    $superAdmin = User::factory()->create(['role' => 'super_admin', 'type' => 'admin']);
    $admin = User::factory()->create(['role' => 'attendance_monitor', 'type' => 'admin']);
    $manager = User::factory()->create(['role' => 'attendance_monitor', 'type' => 'admin']);
    $courseLecturer = User::factory()->create(['role' => 'course_lecturer', 'type' => 'lecturer']);
    $admin->givePermissionTo(Permission::firstOrCreate([
        'name' => ScheduleImportIssuePolicy::RESOLVE_SUBJECT_MAPPING,
        'guard_name' => 'web',
    ]));
    $issue = new ScheduleImportIssue;
    $policy = new ScheduleImportIssuePolicy;

    expect($policy->before($superAdmin))->toBeTrue()
        ->and($policy->resolveSubjectMapping($admin, $issue))->toBeTrue()
        ->and($policy->resolveSubjectMapping($manager, $issue))->toBeFalse()
        ->and($policy->resolveSubjectMapping($courseLecturer, $issue))->toBeFalse();
});

it('renders only actions relevant to the row issue type', function (): void {
    [$path, , $batch, , , $row] = issueSpecificRow([ScheduleImportIssue::TYPE_SUBJECT_NOT_FOUND]);

    try {
        $user = User::factory()->create(['role' => 'attendance_monitor', 'type' => 'admin']);
        foreach (['view schedule-import reconciliation', 'resolve schedule-import subject mapping'] as $name) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($user)
            ->test(ScheduleImportReconciliationReport::class, ['batch' => $batch->uuid])
            ->assertSee(__('schedule-import-reconciliation.actions.link_subject'))
            ->assertDontSee(__('schedule-import-reconciliation.actions.link_section'))
            ->assertDontSee(__('schedule-import-reconciliation.actions.assign_weekly_time'))
            ->assertDontSee(__('schedule-import-reconciliation.actions.assign_lecturer'))
            ->assertDontSee(__('schedule-import-reconciliation.actions.assign_hall'));

        expect($row->fresh()->resolved_subject_id)->toBeNull();
    } finally {
        @unlink($path);
    }
});
