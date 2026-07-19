<?php

namespace App\Http\Controllers\Admin;

use App\Exports\WeeklyScheduleReportExport;
use App\Http\Controllers\Controller;
use App\Models\SubjectSectionScheduleSlot;
use App\Services\WeeklyScheduleReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WeeklyScheduleReportExcelController extends Controller
{
    public function __invoke(Request $request, string $type): BinaryFileResponse
    {
        Gate::authorize('export', SubjectSectionScheduleSlot::class);
        abort_unless(WeeklyScheduleReportService::isSupportedType($type), 404);
        $filters = app(WeeklyScheduleReportService::class)->normalizeFilters($request->only($this->filterKeys()));

        return Excel::download(
            new WeeklyScheduleReportExport($type, $filters),
            'weekly-schedule-'.$type.'-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    /** @return array<int, string> */
    private function filterKeys(): array
    {
        return [
            'academic_term_id', 'import_batch_id', 'faculty_id', 'department_id', 'subject_id',
            'section_type', 'subject_section_id', 'lecturer_id', 'hall_id', 'weekday',
        ];
    }
}
