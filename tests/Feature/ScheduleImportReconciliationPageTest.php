<?php

use App\Filament\Pages\ScheduleImportReconciliationReport;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportRow;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/../Support/ScheduleImportReconciliationFixtures.php';

it('shows four mutually exclusive counted tabs and excludes intentionally unscheduled rows from success', function (): void {
    $path = reconciliationWorkbook([]);

    try {
        [$term, , $batch] = reconciliationSource($path);
        $statuses = [
            [ScheduleImportRow::STATUS_UNRESOLVED, ScheduleImportIssue::SEVERITY_ERROR, ScheduleImportIssue::STATUS_UNRESOLVED],
            [ScheduleImportRow::STATUS_UNRESOLVED, ScheduleImportIssue::SEVERITY_WARNING, ScheduleImportIssue::STATUS_UNRESOLVED],
            [ScheduleImportRow::STATUS_INTENTIONALLY_UNSCHEDULED, ScheduleImportIssue::SEVERITY_WARNING, ScheduleImportIssue::STATUS_INTENTIONALLY_UNSCHEDULED],
            [ScheduleImportRow::STATUS_RESOLVED, null, null],
        ];

        foreach ($statuses as $index => [$rowStatus, $severity, $issueStatus]) {
            $row = ScheduleImportRow::query()->create([
                'import_batch_id' => $batch->id, 'academic_term_id' => $term->id, 'source_sheet_name' => 'Schedule',
                'source_row_number' => $index + 2, 'row_fingerprint' => hash('sha256', "page-row-{$index}"), 'source_payload' => [], 'normalized_payload' => [],
                'original_import_status' => $index === 3 ? ScheduleImportRow::ORIGINAL_IMPORTED : ScheduleImportRow::ORIGINAL_REJECTED,
                'current_reconciliation_status' => $rowStatus,
            ]);

            if ($severity) {
                ScheduleImportIssue::query()->create([
                    'schedule_import_row_id' => $row->id, 'deduplication_key' => hash('sha256', "page-issue-{$index}"),
                    'issue_type' => $index === 2 ? ScheduleImportIssue::TYPE_NO_WEEKLY_TIME : ScheduleImportIssue::TYPE_SECTION_NOT_FOUND,
                    'severity' => $severity, 'reason_ar' => 'اختبار', 'resolution_status' => $issueStatus,
                ]);
            }
        }

        $permission = Permission::firstOrCreate(['name' => 'view schedule-import reconciliation', 'guard_name' => 'web']);
        $admin = User::factory()->create(['role' => 'attendance_monitor', 'type' => 'admin']);
        $admin->givePermissionTo($permission);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $component = Livewire::actingAs($admin)->test(ScheduleImportReconciliationReport::class, ['batch' => $batch->uuid]);

        expect($component->instance()->tabCounts())->toBe([
            'needs_attention' => 1,
            'warnings' => 1,
            'excluded' => 1,
            'successful' => 1,
        ]);
        $component->assertSee('يحتاج معالجة')->assertSee('بلا موعد أو مستبعد')->assertSee('مستورد بنجاح');
    } finally {
        @unlink($path);
    }
});
