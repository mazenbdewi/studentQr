<?php

namespace App\Exports;

class LectureSessionGenerationReportExport extends ArabicArrayWorkbookExport
{
    /** @param array<int, array<string, mixed>> $rows */
    public static function success(array $rows): self
    {
        return new self('العمليات الناجحة', [
            'المادة',
            'الشعبة',
            'المدرس',
            'اسم الدخول',
            'القاعة',
            'التاريخ',
            'وقت البداية',
            'وقت النهاية',
            'النتيجة',
        ], $rows);
    }

    /** @param array<int, array<string, mixed>> $rows */
    public static function errors(array $rows): self
    {
        return new self('الأخطاء والحالات المستبعدة', [
            'الموعد الأسبوعي المصدر',
            'المادة',
            'الشعبة',
            'المدرس',
            'القاعة',
            'اليوم',
            'الوقت',
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
