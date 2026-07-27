<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ScheduleImportReconciliationExport;
use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\ScheduleImportRow;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ScheduleImportReconciliationExportController extends Controller
{
    public function __invoke(string $batch): BinaryFileResponse
    {
        Gate::authorize('export', ScheduleImportRow::class);
        $importBatch = ImportBatch::query()
            ->where('uuid', $batch)
            ->where('import_type', ImportBatch::TYPE_WEEKLY_SCHEDULE)
            ->firstOrFail();

        return Excel::download(
            new ScheduleImportReconciliationExport($importBatch),
            "schedule-import-reconciliation-{$importBatch->uuid}.xlsx",
        );
    }
}
