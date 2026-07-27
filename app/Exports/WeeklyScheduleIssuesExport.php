<?php

namespace App\Exports;

use App\Services\WeeklyScheduleIssueResult;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class WeeklyScheduleIssuesExport implements WithMultipleSheets
{
    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(
        private readonly WeeklyScheduleIssueResult $result,
        private readonly array $rows,
        private readonly string $termName,
        private readonly ?string $batchName,
    ) {}

    public function sheets(): array
    {
        $headings = ['الفصل الدراسي', 'دفعة الاستيراد', 'معرف خانة الجدول', 'الكلية', 'القسم', 'المادة', 'الشعبة', 'نوع الفئة', 'رمز الفئة', 'اليوم', 'وقت البداية', 'وقت النهاية', 'المدرّس', 'القاعة', 'نوع المشكلة', 'وصف المشكلة بالعربية', 'الحالة', 'سبب الاستبعاد', 'تاريخ الحل', 'آخر تعديل بواسطة'];
        $rows = [];

        foreach ($this->rows as $row) {
            $reasons = $row['reasons'] ?: [null];
            foreach ($reasons as $reason) {
                $rows[] = [
                    'الفصل الدراسي' => $this->termName,
                    'دفعة الاستيراد' => $this->batchName ?? '—',
                    'معرف خانة الجدول' => $row['slot_id'],
                    'الكلية' => $row['faculty'],
                    'القسم' => $row['department'],
                    'المادة' => $row['subject'],
                    'الشعبة' => $row['section'],
                    'نوع الفئة' => $row['section_type'],
                    'رمز الفئة' => $row['subject_code'],
                    'اليوم' => $row['weekday'],
                    'وقت البداية' => $row['start_time'],
                    'وقت النهاية' => $row['end_time'],
                    'المدرّس' => $row['lecturer'],
                    'القاعة' => $row['hall'],
                    'نوع المشكلة' => $reason ? $this->reasonKeyLabel($reason) : '—',
                    'وصف المشكلة بالعربية' => $reason ? $this->reasonLabel($reason) : '—',
                    'الحالة' => $this->statusLabel($row['status']),
                    'سبب الاستبعاد' => $row['exclusion_note'] ?? '—',
                    'تاريخ الحل' => $row['resolved_at'] ?? '—',
                    'آخر تعديل بواسطة' => $row['updated_by'] ?? '—',
                ];
            }
        }

        return (new ArabicArrayWorkbookExport([
            ['title' => 'مشكلات الجدول', 'headings' => $headings, 'rows' => $rows],
            [
                'title' => 'الملخص',
                'headings' => ['البند', 'العدد'],
                'rows' => [
                    ['البند' => 'الخانات المختلفة التي تحتاج معالجة', 'العدد' => $this->result->uniqueAffectedSlots],
                    ['البند' => 'الخانات المستبعدة', 'العدد' => $this->result->excludedSlots],
                    ['البند' => 'الخانات الجاهزة', 'العدد' => $this->result->readySlots],
                    ...collect($this->result->issueCountsByKey)->map(fn (int $count, string $key): array => ['البند' => $this->reasonLabel($key), 'العدد' => $count])->values()->all(),
                ],
            ],
        ]))->sheets();
    }

    private function reasonLabel(string $reason): string
    {
        $key = 'lecture-session.lecture_generation.reasons.'.$reason;
        $label = __($key);

        return $label === $key ? __('lecture-session.lecture_generation.reasons.unknown') : $label;
    }

    private function reasonKeyLabel(string $reason): string
    {
        // Exports deliberately expose the human classification only, never a raw key.
        return $this->reasonLabel($reason);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'needs_attention' => 'تحتاج معالجة',
            'excluded' => 'مستبعدة بقرار مستخدم',
            default => 'عولجت',
        };
    }
}
