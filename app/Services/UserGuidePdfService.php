<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserGuidePdfService
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function download(User $user): StreamedResponse
    {
        $tempDir = storage_path('app/mpdf-temp');
        $generatedAt = now();
        $branding = $this->branding();
        $sections = Lang::get('user-guide.pdf.sections', [], 'ar');

        File::ensureDirectoryExists($tempDir);

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'default_font' => 'dejavusans',
            'default_font_size' => 11,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'dpi' => 96,
            'img_dpi' => 96,
            'margin_top' => 14,
            'margin_right' => 12,
            'margin_bottom' => 16,
            'margin_left' => 12,
        ]);

        $pdf->SetTitle(Lang::get('user-guide.pdf.document_title', [], 'ar'));
        $pdf->SetAuthor($branding['system_name']);
        $pdf->SetCreator($branding['system_name']);
        $pdf->SetDirectionality('rtl');
        $pdf->SetDisplayMode('fullpage');
        $pdf->SetHTMLFooter(
            '<div style="direction: rtl; text-align: center; font-family: dejavusans, sans-serif; font-size: 10px; color: #64748b;">'
            .Lang::get('user-guide.pdf.footer', [], 'ar')
            .'</div>'
        );

        $pdf->WriteHTML(view('exports.user-guide-pdf', [
            'generatedAt' => Carbon::instance($generatedAt)->locale('ar'),
            'branding' => $branding,
            'sections' => is_array($sections) ? $sections : [],
        ])->render());

        $this->activityLogger->logExport(
            'reports',
            'user_guide_pdf',
            $this->fileName(),
            null,
            [
                'downloaded_by_user_id' => $user->id,
            ],
        );

        return response()->streamDownload(
            fn () => print ($pdf->Output('', Destination::STRING_RETURN)),
            $this->fileName(),
            ['Content-Type' => 'application/pdf']
        );
    }

    public function branding(): array
    {
        return [
            'organization_name' => $this->firstFilledSetting([
                'university_name',
                'organization_name',
                'agency_name',
                'institution_name',
            ]) ?: 'اسم الجهة',
            'system_name' => $this->firstFilledSetting([
                'system_name',
                'application_name',
                'portal_name',
            ]) ?: 'نظام تفقد الطلاب',
            'logo_data_uri' => $this->logoDataUri(),
        ];
    }

    private function fileName(): string
    {
        return 'user-guide-ar.pdf';
    }

    private function firstFilledSetting(array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = AppSetting::value($key);

            if (filled($value)) {
                return trim((string) $value);
            }
        }

        return null;
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
            Log::warning('Unable to embed logo in user guide PDF.', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function logoPath(): ?string
    {
        $configuredPath = $this->firstFilledSetting([
            'logo_path',
            'logo',
            'system_logo',
            'university_logo',
        ]);

        $candidatePaths = array_filter([
            $this->normalizeLogoPath($configuredPath),
            public_path('images/logo.png'),
        ]);

        foreach ($candidatePaths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function normalizeLogoPath(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $path = trim((string) $path);

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parsedPath = parse_url($path, PHP_URL_PATH);

            return $parsedPath ? public_path(ltrim($parsedPath, '/')) : null;
        }

        if (str_starts_with($path, '/')) {
            return public_path(ltrim($path, '/'));
        }

        if (File::exists($path)) {
            return $path;
        }

        return public_path(ltrim($path, '/'));
    }
}
