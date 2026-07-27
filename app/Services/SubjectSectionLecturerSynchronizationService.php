<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Models\User;

/**
 * Makes subject_sections.lecturer_id reflect weekly-schedule lecturer identities.
 * The slots remain the import trace; this service never edits them.
 */
class SubjectSectionLecturerSynchronizationService
{
    /** @return array<string, mixed> */
    public function previewForBatch(ImportBatch $batch): array
    {
        $sectionIds = SubjectSectionScheduleSlot::query()
            ->where('import_batch_id', $batch->id)
            ->whereNotNull('subject_section_id')
            ->distinct()
            ->pluck('subject_section_id');

        return $this->previewSections($sectionIds);
    }

    /** @param iterable<int, int|string> $sectionIds
     * @return array<string, mixed>
     */
    public function previewSections(iterable $sectionIds): array
    {
        $sections = SubjectSection::query()
            ->whereIn('id', collect($sectionIds)->filter()->unique()->values())
            ->with('lecturer')
            ->orderBy('id')
            ->get();

        $rows = $sections->map(fn (SubjectSection $section): array => $this->previewSection($section))->values();

        return [
            'sections' => $rows->all(),
            'unique_lecturer_count' => $rows->where('result', 'unique_lecturer')->count(),
            'no_lecturer_count' => $rows->where('result', 'no_lecturer')->count(),
            'multiple_lecturers_count' => $rows->where('result', 'multiple_lecturers')->count(),
            'would_set_count' => $rows->where('change', 'set')->count(),
            'would_clear_count' => $rows->where('change', 'clear')->count(),
            'unchanged_count' => $rows->where('change', 'unchanged')->count(),
            'unresolved_lecturer_identity_ids' => $rows->pluck('unresolved_lecturer_identities')->flatten()->unique()->values()->all(),
        ];
    }

    /** @param iterable<int, int|string> $sectionIds
     * @return array<string, mixed>
     */
    public function synchronizeSections(iterable $sectionIds): array
    {
        $preview = $this->previewSections($sectionIds);

        foreach ($preview['sections'] as $row) {
            if ($row['change'] === 'unchanged') {
                continue;
            }

            SubjectSection::query()->whereKey($row['section_id'])->update([
                'lecturer_id' => $row['resolved_lecturer_user_id'],
                'updated_at' => now(),
            ]);
        }

        return $preview;
    }

    /** @return array<string, mixed> */
    public function synchronizeBatch(ImportBatch $batch): array
    {
        $sectionIds = SubjectSectionScheduleSlot::query()
            ->where('import_batch_id', $batch->id)
            ->whereNotNull('subject_section_id')
            ->distinct()
            ->pluck('subject_section_id');

        return $this->synchronizeSections($sectionIds);
    }

    /** @return array<string, mixed> */
    private function previewSection(SubjectSection $section): array
    {
        $slots = SubjectSectionScheduleSlot::query()
            ->where('subject_section_id', $section->id)
            ->where('academic_term_id', $section->academic_term_id)
            ->with('lecturer.user')
            ->orderBy('id')
            ->get();

        $resolved = $slots->map(function (SubjectSectionScheduleSlot $slot): ?User {
            /** @var User|null $user */
            $user = $slot->lecturer?->user;

            return $user;
        })
            ->filter()
            ->unique('id')
            ->values();
        $unresolved = $slots->filter(fn (SubjectSectionScheduleSlot $slot): bool => $slot->lecturer_id !== null && $slot->lecturer?->user_id === null);
        $result = match ($resolved->count()) {
            0 => 'no_lecturer',
            1 => 'unique_lecturer',
            default => 'multiple_lecturers',
        };
        $targetUserId = $resolved->count() === 1 ? (int) $resolved->first()->id : null;
        $change = (int) ($section->lecturer_id ?? 0) === (int) ($targetUserId ?? 0)
            ? 'unchanged'
            : ($targetUserId === null ? 'clear' : 'set');

        return [
            'section_id' => (int) $section->id,
            'academic_term_id' => (int) $section->academic_term_id,
            'subject_id' => (int) $section->subject_id,
            'section_code' => (string) $section->code,
            'result' => $result,
            'current_lecturer_user_id' => $section->lecturer_id ? (int) $section->lecturer_id : null,
            'resolved_lecturer_user_id' => $targetUserId,
            'resolved_lecturers' => $resolved->map(fn (User $user): array => ['id' => (int) $user->id, 'name' => (string) $user->name])->all(),
            'source_slot_ids' => $slots->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'unresolved_lecturer_identities' => $unresolved->map(fn (SubjectSectionScheduleSlot $slot): array => [
                'id' => (int) $slot->lecturer_id,
                'name' => (string) ($slot->lecturer->name ?? ''),
                'source_slot_id' => (int) $slot->id,
            ])->all(),
            'warning' => $result === 'multiple_lecturers'
                ? 'يوجد أكثر من محاضر مرتبط بهذه الشعبة في البرنامج الأسبوعي، ويجب تحديد المحاضر المعتمد.'
                : null,
            'change' => $change,
        ];
    }
}
