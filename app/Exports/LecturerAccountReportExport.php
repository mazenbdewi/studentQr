<?php

namespace App\Exports;

class LecturerAccountReportExport extends ArabicArrayWorkbookExport
{
    /** @param array<int, array<string, mixed>> $rows */
    public static function success(array $rows): self
    {
        return new self('العمليات الناجحة', [
            'اسم المدرس',
            'اسم الدخول',
            'النتيجة',
            'الحساب المنشأ أو المعاد استخدامه',
            'الدور',
            'الملاحظة',
        ], $rows);
    }

    /** @param array<int, array<string, mixed>> $rows */
    public static function errors(array $rows): self
    {
        return new self('الأخطاء والحالات المستبعدة', [
            'اسم المدرس',
            'اسم الدخول المقترح',
            'رمز الخطأ',
            'السبب بالعربية',
            'الإجراء المقترح',
        ], $rows);
    }

    /** @param array<int, string> $headings */
    private function __construct(string $title, array $headings, array $rows)
    {
        parent::__construct([[
            'title' => $title,
            'headings' => $headings,
            'rows' => $rows,
        ]]);
    }
}
