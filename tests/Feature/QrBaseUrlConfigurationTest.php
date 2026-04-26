<?php

use App\Models\AppSetting;
use App\Support\QrUrlGenerator;

it('builds QR verification links from the configured database base URL', function () {
    AppSetting::put('qr_base_url', 'http://192.168.1.103:8089/');

    $url = app(QrUrlGenerator::class)->attendanceVerificationUrl('demo-token');

    expect($url)->toBe('http://192.168.1.103:8089/student/attendance/verify/demo-token');
});

it('falls back to APP_URL when the database QR base URL is empty', function () {
    config()->set('app.url', 'http://fallback.test');

    AppSetting::put('qr_base_url', null);

    $url = app(QrUrlGenerator::class)->attendanceVerificationUrl('demo-token');

    expect($url)->toBe('http://fallback.test/student/attendance/verify/demo-token');
});
