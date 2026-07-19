<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubjectSectionScheduleSlot;
use App\Services\WeeklyScheduleReportService;
use App\Support\WeeklyScheduleReportPdfExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WeeklyScheduleReportPdfController extends Controller
{
    public function __invoke(Request $request, string $type): StreamedResponse
    {
        Gate::authorize('export', SubjectSectionScheduleSlot::class);
        abort_unless(WeeklyScheduleReportService::isSupportedType($type), 404);
        $filters = app(WeeklyScheduleReportService::class)->normalizeFilters($request->only($this->filterKeys()));

        return app(WeeklyScheduleReportPdfExporter::class)->download($type, $filters);
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
