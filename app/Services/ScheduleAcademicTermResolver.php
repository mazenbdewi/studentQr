<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ImportBatch;
use Illuminate\Support\Collection;
use RuntimeException;

class ScheduleAcademicTermResolver
{
    /**
     * @param  array<int, array{subject_code_key: string, section_code: string}>  $scheduleKeys
     * @return array{0: ImportBatch, 1: AcademicTerm}
     */
    public function resolve(array $scheduleKeys, ?string $sourceBatchUuid = null): array
    {
        // Subject and section keys are intentionally not used to resolve the term.
        // They are validated independently during row processing and reconciliation.
        unset($scheduleKeys);

        if (filled($sourceBatchUuid)) {
            $batch = ImportBatch::query()
                ->eligibleEnrollmentSource()
                ->with('academicTerms')
                ->where('uuid', $sourceBatchUuid)
                ->first();

            if (! $batch) {
                throw new RuntimeException('دفعة تسجيل الطلاب المرتبطة غير موجودة أو غير مكتملة.');
            }

            return [$batch, $this->soleUsableTerm($batch)];
        }

        $eligible = ImportBatch::query()
            ->eligibleEnrollmentSource()
            ->with('academicTerms')
            ->get()
            ->filter(fn (ImportBatch $batch): bool => $batch->academicTerms->count() === 1)
            ->values();

        if ($eligible->isEmpty()) {
            throw new RuntimeException('لا توجد دفعة تسجيل طلاب مكتملة ومؤهلة. استورد تسجيلات الطلاب أولاً ثم افتح استيراد الجدول من نتيجة الاستيراد.');
        }

        if ($eligible->count() > 1) {
            throw new RuntimeException('توجد أكثر من دفعة تسجيل طلاب مؤهلة. افتح استيراد الجدول من رابط المتابعة داخل نتيجة استيراد التسجيلات المطلوبة.');
        }

        $batch = $eligible->firstOrFail();

        return [$batch, $this->soleUsableTerm($batch)];
    }

    private function soleUsableTerm(ImportBatch $batch): AcademicTerm
    {
        /** @var Collection<int, AcademicTerm> $terms */
        $terms = $batch->academicTerms;

        if ($terms->count() !== 1) {
            throw new RuntimeException('دفعة تسجيل الطلاب لا ترتبط بفصل دراسي صالح ووحيد.');
        }

        return $terms->firstOrFail();
    }
}
