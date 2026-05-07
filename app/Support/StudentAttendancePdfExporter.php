<?php

namespace App\Support;

use App\Models\Student;
use App\Models\Subject;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentAttendancePdfExporter
{
    public function __construct(
        private readonly StudentAttendanceReport $report,
    ) {}

    public function download(Student $student, ?int $subjectId = null): StreamedResponse
    {
        $student->loadMissing(['department', 'faculty']);

        $selectedSubject = $this->report->resolveSubject($student, $subjectId);
        $rows = $this->report->rows($student, $selectedSubject?->id);
        $summary = $this->report->summaryFromRows($rows);
        $isRtl = app()->getLocale() === 'ar';
        $tempDir = storage_path('app/mpdf-temp');
        $generatedAt = now();
        $reportTitle = $selectedSubject
            ? __('student.subject_attendance_report_for', [
                'name' => $student->name,
                'subject' => $selectedSubject->name,
            ])
            : __('student.attendance_report_for', ['name' => $student->name]);

        File::ensureDirectoryExists($tempDir);

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'default_font' => 'dejavusans',
            'default_font_size' => 10,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'dpi' => 96,
            'img_dpi' => 96,
            'margin_top' => 12,
            'margin_right' => 10,
            'margin_bottom' => 12,
            'margin_left' => 10,
        ]);

        $pdf->SetTitle($reportTitle);
        $pdf->SetAuthor(config('app.name'));
        $pdf->SetCreator(config('app.name'));
        $pdf->SetDirectionality($isRtl ? 'rtl' : 'ltr');
        $pdf->SetDisplayMode('fullpage');
        $pdf->WriteHTML(view('exports.student-attendance-report-pdf', [
            'student' => $student,
            'selectedSubject' => $selectedSubject,
            'rows' => $rows,
            'summary' => $summary,
            'subjectLabels' => $selectedSubject
                ? [$selectedSubject->name]
                : array_values($this->report->subjectOptions($student)),
            'generatedAt' => $generatedAt,
            'isRtl' => $isRtl,
            'logoDataUri' => $this->logoDataUri(),
        ])->render());

        app(ActivityLogger::class)->logExport(
            'reports',
            'student_attendance_pdf',
            $this->fileName($student, $selectedSubject),
            $student,
            [
                'subject_id' => $selectedSubject?->id,
            ],
        );

        return response()->streamDownload(
            fn () => print ($pdf->Output('', Destination::STRING_RETURN)),
            $this->fileName($student, $selectedSubject),
            ['Content-Type' => 'application/pdf']
        );
    }

    private function fileName(Student $student, ?Subject $selectedSubject = null): string
    {
        $identifier = $student->student_number ?: (string) $student->id;
        $slug = Str::slug($identifier, '_');

        $fileName = 'student_attendance_report_'.($slug !== '' ? $slug : 'student_'.$student->id);

        if ($selectedSubject) {
            $subjectIdentifier = $selectedSubject->code ?: $selectedSubject->name ?: (string) $selectedSubject->id;
            $subjectSlug = Str::slug($subjectIdentifier, '_');

            if ($subjectSlug !== '') {
                $fileName .= '_'.$subjectSlug;
            }
        }

        return $fileName.'.pdf';
    }

    private function logoDataUri(): ?string
    {
        $path = $this->logoPath();

        if (! $path || ! File::exists($path)) {
            return null;
        }

        try {
            $mimeType = File::mimeType($path) ?: 'image/png';

            return 'data:'.$mimeType.';base64,'.base64_encode(File::get($path));
        } catch (\Throwable $exception) {
            Log::warning('Unable to embed university logo in student attendance PDF.', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function logoPath(): ?string
    {
        $candidatePaths = [
            public_path('images/logo.png'),
        ];

        foreach ($candidatePaths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
