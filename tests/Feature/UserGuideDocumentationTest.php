<?php

use Illuminate\Support\Facades\Route;

it('ships the current Arabic user-guide source, printable rtl HTML, and image plan', function (): void {
    $markdown = file_get_contents(base_path('docs/user-guide-ar.md'));
    $html = file_get_contents(base_path('docs/user-guide-ar.html'));
    $imagesReadme = file_get_contents(base_path('docs/user-guide-images/README.md'));
    $verification = file_get_contents(base_path('docs/user-guide-verification.md'));
    $pdf = file_get_contents(base_path('docs/user-guide-ar.pdf'));

    expect($markdown)->toContain('دليل استخدام نظام حضور الطلاب', 'تسجيل الدخول باسم المستخدم', 'محاضرات اليوم المنتهية')
        ->not->toContain('missing_hall', 'auth.current_password')
        ->and($html)->toContain('<html lang="ar" dir="rtl">', 'الفهرس', 'جلسات المحاضرات')
        ->and($imagesReadme)->toContain('lecture-sessions.png', 'بيانات يجب إخفاؤها')
        ->and($verification)->toContain('/admin/my-profile', '/admin/lecture-sessions', '/admin/seminars')
        ->and($pdf)->toStartWith('%PDF-')
        ->and(Route::has('filament.admin.pages.my-profile'))->toBeTrue()
        ->and(Route::has('filament.admin.resources.lecture-sessions.index'))->toBeTrue()
        ->and(Route::has('filament.admin.resources.seminars.index'))->toBeTrue();
});
