<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BlockedWeeklySlotsExport implements WithMultipleSheets
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $conflicts
     */
    public function __construct(
        private readonly array $rows,
        private readonly array $conflicts,
    ) {}

    public function sheets(): array
    {
        return (new ArabicArrayWorkbookExport([
            [
                'title' => 'الخانات المحجوبة',
                'headings' => [
                    'رقم الموعد الأسبوعي',
                    'المادة',
                    'الشعبة',
                    'المدرس',
                    'القاعة',
                    'اليوم',
                    'وقت البداية',
                    'وقت النهاية',
                    'المشكلات',
                    'عدد الجلسات المتأثرة',
                    'الإجراء المقترح',
                ],
                'rows' => $this->rows,
            ],
            [
                'title' => 'تفاصيل التعارضات',
                'headings' => [
                    'الموعد المصدر',
                    'الموعد المتعارض',
                    'مادة وشعبة المصدر',
                    'مادة وشعبة الموعد المتعارض',
                    'مدرس المصدر',
                    'مدرس الموعد المتعارض',
                    'قاعة المصدر',
                    'قاعة الموعد المتعارض',
                    'اليوم',
                    'تاريخ الجلسة',
                    'وقت بداية المصدر',
                    'وقت نهاية المصدر',
                    'وقت بداية المتعارض',
                    'وقت نهاية المتعارض',
                    'فترة التداخل الفعلية',
                    'بعد التعارض',
                ],
                'rows' => collect($this->conflicts)
                    ->map(fn (array $conflict): array => [
                        'الموعد المصدر' => $conflict['source_slot_id'] ?? '',
                        'الموعد المتعارض' => $conflict['conflicting_source_slot_id'] ?? '',
                        'مادة وشعبة المصدر' => $conflict['source_subject_section'] ?? '',
                        'مادة وشعبة الموعد المتعارض' => $conflict['conflicting_subject_section'] ?? '',
                        'مدرس المصدر' => $conflict['source_lecturer'] ?? '',
                        'مدرس الموعد المتعارض' => $conflict['conflicting_lecturer'] ?? '',
                        'قاعة المصدر' => $conflict['source_hall'] ?? '',
                        'قاعة الموعد المتعارض' => $conflict['conflicting_hall'] ?? '',
                        'اليوم' => $conflict['weekday'] ?? '',
                        'تاريخ الجلسة' => $conflict['session_date'] ?? '',
                        'وقت بداية المصدر' => $conflict['source_start_time'] ?? '',
                        'وقت نهاية المصدر' => $conflict['source_end_time'] ?? '',
                        'وقت بداية المتعارض' => $conflict['conflicting_start_time'] ?? '',
                        'وقت نهاية المتعارض' => $conflict['conflicting_end_time'] ?? '',
                        'فترة التداخل الفعلية' => $conflict['actual_overlap_interval'] ?? '',
                        'بعد التعارض' => $conflict['conflict_dimension'] ?? '',
                    ])
                    ->all(),
            ],
        ]))->sheets();
    }
}
