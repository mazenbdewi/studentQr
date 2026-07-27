<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Pages\ScheduleImportIssues;
use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Support\AcademicTermContext;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BlockedWeeklySlotsCompatibilityRedirectController extends Controller
{
    public function __invoke(Request $request, AcademicTermContext $academicTermContext): RedirectResponse
    {
        abort_unless(
            $request->user()?->hasAnyRole(['super-admin', 'admin'])
                || $request->user()?->can('preview blocked weekly slot reconciliation'),
            403,
        );

        $term = $academicTermContext->current();
        $batches = $term === null ? collect() : ImportBatch::query()
            ->where('import_type', ImportBatch::TYPE_WEEKLY_SCHEDULE)
            ->whereIn('status', [ImportBatch::STATUS_COMPLETED, ImportBatch::STATUS_COMPLETED_WITH_ERRORS])
            ->whereHas('academicTerms', fn ($query) => $query->whereKey($term->id))
            ->whereHas('scheduleSlots', fn ($query) => $query->where('academic_term_id', $term->id))
            ->get();

        if ($batches->count() === 1) {
            return redirect()->to(ScheduleImportIssues::getUrl(['batch' => $batches->first()->id, 'term' => $term->id]));
        }

        Notification::make()
            ->title('يرجى اختيار عملية استيراد برنامج أسبوعي للفصل الدراسي الحالي.')
            ->warning()
            ->send();

        return redirect()->to(ScheduleImportIssues::getUrl(['term' => $term?->id]));
    }
}
