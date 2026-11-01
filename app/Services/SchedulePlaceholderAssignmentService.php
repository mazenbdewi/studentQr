<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\AuditLog;
use App\Models\ImportBatch;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SchedulePlaceholderAssignmentService
{
    public function __construct(private readonly SchedulePlaceholderService $placeholders) {}

    /** @return array{academic_term_id: int, import_batch_id: int, theoretical_lecturer_count: int, practical_lecturer_count: int, hall_count: int, affected_slot_count: int, missing_both_count: int} */
    public function preview(AcademicTerm $term, ImportBatch $batch): array
    {
        $this->ensureBatchBelongsToTerm($term, $batch);

        return $this->summary($this->slots($term, $batch)->get(), $term, $batch);
    }

    /** @return array{academic_term_id: int, import_batch_id: int, theoretical_lecturer_count: int, practical_lecturer_count: int, hall_count: int, affected_slot_count: int, missing_both_count: int} */
    public function apply(AcademicTerm $term, ImportBatch $batch, User $actor): array
    {
        return DB::transaction(function () use ($term, $batch, $actor): array {
            $lockedBatch = ImportBatch::query()
                ->whereKey($batch->id)
                ->where('import_type', ImportBatch::TYPE_WEEKLY_SCHEDULE)
                ->whereHas('academicTerms', fn (Builder $query): Builder => $query->whereKey($term->id))
                ->lockForUpdate()
                ->firstOrFail();

            $slots = $this->slots($term, $lockedBatch)
                ->lockForUpdate()
                ->get();
            $summary = $this->summary($slots, $term, $lockedBatch);

            $theoreticalIds = $this->missingLecturerIds($slots, Subject::TYPE_THEORETICAL);
            $practicalIds = $this->missingLecturerIds($slots, Subject::TYPE_PRACTICAL);
            $hallIds = $slots->whereNull('hall_id')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

            if ($theoreticalIds !== []) {
                SubjectSectionScheduleSlot::query()
                    ->whereIn('id', $theoreticalIds)
                    ->whereNull('lecturer_id')
                    ->update(['lecturer_id' => $this->placeholders->theoreticalLecturer()->id]);
            }

            if ($practicalIds !== []) {
                SubjectSectionScheduleSlot::query()
                    ->whereIn('id', $practicalIds)
                    ->whereNull('lecturer_id')
                    ->update(['lecturer_id' => $this->placeholders->practicalLecturer()->id]);
            }

            if ($hallIds !== []) {
                SubjectSectionScheduleSlot::query()
                    ->whereIn('id', $hallIds)
                    ->whereNull('hall_id')
                    ->update(['hall_id' => $this->placeholders->unassignedHall()->id]);
            }

            if ($summary['affected_slot_count'] > 0) {
                AuditLog::query()->create([
                    'user_id' => $actor->id,
                    'category' => 'schedule_import',
                    'action' => 'auto_fill_missing_assignments',
                    'model_type' => ImportBatch::class,
                    'model_id' => $lockedBatch->id,
                    'description' => 'تمت معالجة تكليفات البرنامج الأسبوعي الناقصة باستخدام القيم الافتراضية.',
                    'new_values' => $summary,
                    'metadata' => [
                        ...$summary,
                        'user_id' => $actor->id,
                    ],
                    'severity' => 'info',
                ]);
            }

            return $summary;
        });
    }

    /** @return Builder<SubjectSectionScheduleSlot> */
    private function slots(AcademicTerm $term, ImportBatch $batch): Builder
    {
        return SubjectSectionScheduleSlot::query()
            ->with('subjectSection:id,subject_id,section_type')
            ->where('academic_term_id', $term->id)
            ->where('import_batch_id', $batch->id);
    }

    private function ensureBatchBelongsToTerm(AcademicTerm $term, ImportBatch $batch): void
    {
        ImportBatch::query()
            ->whereKey($batch->id)
            ->where('import_type', ImportBatch::TYPE_WEEKLY_SCHEDULE)
            ->whereHas('academicTerms', fn (Builder $query): Builder => $query->whereKey($term->id))
            ->firstOrFail();
    }

    /** @param Collection<int, SubjectSectionScheduleSlot> $slots @return array{academic_term_id: int, import_batch_id: int, theoretical_lecturer_count: int, practical_lecturer_count: int, hall_count: int, affected_slot_count: int, missing_both_count: int} */
    private function summary(Collection $slots, AcademicTerm $term, ImportBatch $batch): array
    {
        $theoreticalIds = $this->missingLecturerIds($slots, Subject::TYPE_THEORETICAL);
        $practicalIds = $this->missingLecturerIds($slots, Subject::TYPE_PRACTICAL);
        $hallIds = $slots->whereNull('hall_id')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        return [
            'academic_term_id' => (int) $term->id,
            'import_batch_id' => (int) $batch->id,
            'theoretical_lecturer_count' => count($theoreticalIds),
            'practical_lecturer_count' => count($practicalIds),
            'hall_count' => count($hallIds),
            'affected_slot_count' => count(array_unique([...$theoreticalIds, ...$practicalIds, ...$hallIds])),
            'missing_both_count' => $slots
                ->filter(fn (SubjectSectionScheduleSlot $slot): bool => $slot->lecturer_id === null
                    && $slot->hall_id === null
                    && in_array($this->sectionType($slot), [Subject::TYPE_THEORETICAL, Subject::TYPE_PRACTICAL], true))
                ->count(),
        ];
    }

    /** @param Collection<int, SubjectSectionScheduleSlot> $slots @return array<int, int> */
    private function missingLecturerIds(Collection $slots, string $sectionType): array
    {
        return $slots
            ->filter(fn (SubjectSectionScheduleSlot $slot): bool => $slot->lecturer_id === null
                && $this->sectionType($slot) === $sectionType)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function sectionType(SubjectSectionScheduleSlot $slot): ?string
    {
        $section = $slot->getRelation('subjectSection');

        return $section instanceof SubjectSection ? $section->section_type : null;
    }
}
