<?php

namespace App\Support;

use App\Models\AppSetting;

class QrUrlGenerator
{
    public function attendanceVerificationUrl(string $token): string
    {
        return $this->route('student.attendance.verify.token', ['token' => $token]);
    }

    /**
     * Build a QR-safe absolute URL from a configured base URL and a relative route path.
     */
    public function route(string $name, array $parameters = []): string
    {
        $relativePath = route($name, $parameters, false);

        return $this->join($this->baseUrl(), $relativePath);
    }

    public function baseUrl(): string
    {
        $configuredUrl = AppSetting::value('qr_base_url');

        return rtrim((string) ($configuredUrl ?: config('app.url')), '/');
    }

    private function join(string $baseUrl, string $relativePath): string
    {
        return $baseUrl.'/'.ltrim($relativePath, '/');
    }
}
