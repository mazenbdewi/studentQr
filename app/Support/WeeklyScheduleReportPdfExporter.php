<?php

namespace App\Support;

use App\Services\WeeklyScheduleReportService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WeeklyScheduleReportPdfExporter
{
    public function download(string $type, array $filters): StreamedResponse
    {
        $service = app(WeeklyScheduleReportService::class);
        $title = WeeklyScheduleReportService::reportTypes()[$type];
        $tempDir = storage_path('app/mpdf-temp');
        File::ensureDirectoryExists($tempDir);

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'tempDir' => $tempDir,
            'default_font' => 'dejavusans',
            'default_font_size' => 8,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'margin_top' => 10,
            'margin_right' => 8,
            'margin_bottom' => 13,
            'margin_left' => 8,
        ]);
        $pdf->SetTitle($title);
        $pdf->SetAuthor(config('app.name'));
        $pdf->SetDirectionality('rtl');
        $pdf->SetDisplayMode('fullpage');
        $pdf->SetHTMLFooter('<div style="text-align:center;font-family:dejavusans;font-size:8pt;color:#64748b">{PAGENO} / {nbpg}</div>');
        $pdf->WriteHTML(view('exports.weekly-schedule-report-pdf', [
            'title' => $title,
            'headings' => $service->headings($type),
            'rows' => $service->rows($type, $filters),
            'filterLabels' => $service->activeFilterLabels($filters),
            'generatedAt' => now(),
            'logoDataUri' => $this->logoDataUri(),
        ])->render());

        return response()->streamDownload(
            fn () => print ($pdf->Output('', Destination::STRING_RETURN)),
            'weekly-schedule-'.$type.'-'.now()->format('Ymd-His').'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function logoDataUri(): ?string
    {
        $path = public_path('images/logo.png');

        if (! File::exists($path)) {
            return null;
        }

        try {
            return 'data:'.(File::mimeType($path) ?: 'image/png').';base64,'.base64_encode(File::get($path));
        } catch (\Throwable $exception) {
            Log::warning('Unable to embed the logo in a weekly schedule report.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
