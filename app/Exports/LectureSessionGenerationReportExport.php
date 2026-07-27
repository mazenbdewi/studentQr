<?php

namespace App\Exports;

class LectureSessionGenerationReportExport extends ArabicArrayWorkbookExport
{
    /** @param array<int, array<string, mixed>> $rows */
    public static function success(array $rows): self
    {
        return new self('العمليات الناجحة', [
            'الرقم',
            'المادة',
            'رمز المادة',
            'الشعبة',
            'المدرس',
            'اسم دخول المدرس',
            'القاعة',
            'التاريخ',
            'اليوم',
            'وقت البداية',
            'وقت النهاية',
            'النتيجة',
            'رقم الموعد الأسبوعي المصدر',
        ], self::numberedRows($rows));
    }

    /** @param array<int, array<string, mixed>> $rows */
    public static function errors(array $rows): self
    {
        return new self('الأخطاء والحالات المستبعدة', [
            'الرقم',
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
        ], self::numberedRows($rows));
    }

    /** @param array<int, array<string, mixed>> $rows */
    private static function numberedRows(array $rows): array
    {
        return collect($rows)
            ->values()
            ->map(fn (array $row, int $index): array => [
                'الرقم' => $index + 1,
                ...$row,
            ])
            ->all();
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
