<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ImportBatch;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Support\WeeklyScheduleRowNormalizer;
use Illuminate\Support\Collection;
use RuntimeException;

class ScheduleAcademicTermResolver
{
    public function __construct(
        private readonly WeeklyScheduleRowNormalizer $normalizer,
    ) {}

    /**
     * @param  array<int, array{subject_code_key: string, section_code: string}>  $scheduleKeys
     * @return array{0: ImportBatch, 1: AcademicTerm}
     */
    public function resolve(array $scheduleKeys, ?string $sourceBatchUuid = null): array
    {
        $scheduleKeys = collect($scheduleKeys)
            ->filter(fn (array $key): bool => filled($key['subject_code_key']) && filled($key['section_code']))
            ->unique(fn (array $key): string => $key['subject_code_key'].'|'.$key['section_code'])
            ->values();

        if ($scheduleKeys->isEmpty()) {
            throw new RuntimeException('لا يحتوي الملف على مفاتيح مقررات وشعب صالحة لتحديد دفعة التسجيل المرتبطة.');
        }

        if (filled($sourceBatchUuid)) {
            $batch = ImportBatch::query()
                ->eligibleEnrollmentSource()
                ->with('academicTerms')
                ->where('uuid', $sourceBatchUuid)
                ->first();

            if (! $batch) {
                throw new RuntimeException('دفعة تسجيل الطلاب المرتبطة غير موجودة أو غير مكتملة.');
            }

            /** @var Collection<int, AcademicTerm> $batchTerms */
            $batchTerms = $batch->academicTerms;

            if ($batchTerms->count() === 1) {
                return [$batch, $batchTerms->firstOrFail()];
            }

            $terms = $this->compatibleTerms($batchTerms, $scheduleKeys);

            if ($terms->count() !== 1) {
                throw new RuntimeException('تعذر تحديد فصل دراسي وحيد متوافق داخل دفعة التسجيل المرتبطة.');
            }

            return [$batch, $terms->firstOrFail()];
        }

        $compatible = ImportBatch::query()
            ->eligibleEnrollmentSource()
            ->with('academicTerms')
            ->get()
            ->map(function (ImportBatch $batch) use ($scheduleKeys): ?array {
                /** @var Collection<int, AcademicTerm> $batchTerms */
                $batchTerms = $batch->academicTerms;
                $terms = $this->compatibleTerms($batchTerms, $scheduleKeys);

                return $terms->count() === 1 ? [$batch, $terms->firstOrFail()] : null;
            })
            ->filter()
            ->values();

        if ($compatible->count() === 0) {
            throw new RuntimeException('لا توجد دفعة تسجيل مكتملة ومتوافقة مع جميع مقررات وشعب ملف الجدول.');
        }

        if ($compatible->count() > 1) {
            throw new RuntimeException('توجد أكثر من دفعة تسجيل متوافقة. افتح استيراد الجدول من رابط المتابعة داخل نتيجة استيراد التسجيلات.');
        }

        /** @var array{0: ImportBatch, 1: AcademicTerm} $resolved */
        $resolved = $compatible->first();

        return $resolved;
    }

    /**
     * @param  Collection<int, AcademicTerm>  $terms
     * @param  Collection<int, array{subject_code_key: string, section_code: string}>  $scheduleKeys
     * @return Collection<int, AcademicTerm>
     */
    private function compatibleTerms(Collection $terms, Collection $scheduleKeys): Collection
    {
        return $terms->filter(function (AcademicTerm $term) use ($scheduleKeys): bool {
            $availableKeys = SubjectSection::query()
                ->where('academic_term_id', $term->id)
                ->with('subject:id,code')
                ->get(['id', 'academic_term_id', 'subject_id', 'code'])
                ->mapWithKeys(function (SubjectSection $section): array {
                    $subject = $section->subject;

                    return $subject instanceof Subject ? [
                        $this->normalizer->normalizeKey($subject->code).'|'.SubjectSection::normalizeCode($section->code) => true,
                    ] : [];
                });

            return $scheduleKeys->every(fn (array $key): bool => $availableKeys->has(
                $key['subject_code_key'].'|'.SubjectSection::normalizeCode($key['section_code']),
            ));
        })->values();
    }
}
