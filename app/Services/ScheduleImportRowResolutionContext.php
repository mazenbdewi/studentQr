<?php

namespace App\Services;

use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportRow;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Support\WeeklyScheduleRowNormalizer;

class ScheduleImportRowResolutionContext
{
    private const SUBJECT_ISSUES = [
        ScheduleImportIssue::TYPE_SUBJECT_NOT_FOUND,
        ScheduleImportIssue::TYPE_SUBJECT_NOT_UNIQUE,
        ScheduleImportIssue::TYPE_NON_AUTHORITATIVE_SUBJECT_CODE,
        ScheduleImportIssue::TYPE_ZERO_STUDENT_SUBJECT_MISSING,
    ];

    private const SECTION_ISSUES = [
        ScheduleImportIssue::TYPE_SECTION_NOT_FOUND,
        ScheduleImportIssue::TYPE_ZERO_STUDENT_SECTION_MISSING,
    ];

    /** @var array<string, array<int, int>> */
    private array $subjectMatches = [];

    /** @var array<string, array<int, int>> */
    private array $sectionMatches = [];

    public function __construct(private readonly WeeklyScheduleRowNormalizer $normalizer) {}

    public function effectiveSubjectId(ScheduleImportRow $row): ?int
    {
        return $this->positiveId($row->resolved_subject_id)
            ?? $this->uniqueLegacyIssueId($row, 'resolved_subject_id')
            ?? $this->originalMatchedSubjectId($row);
    }

    public function effectiveSubjectSectionId(ScheduleImportRow $row): ?int
    {
        $sectionId = $this->positiveId($row->resolved_subject_section_id)
            ?? $this->uniqueLegacyIssueId($row, 'resolved_subject_section_id')
            ?? $this->originalMatchedSubjectSectionId($row);

        if (! $sectionId) {
            return null;
        }

        $section = SubjectSection::query()->find($sectionId);
        $subjectId = $this->effectiveSubjectId($row);

        return $section
            && $subjectId
            && (int) $section->subject_id === $subjectId
            && (int) $section->academic_term_id === (int) $row->academic_term_id
            && $this->sectionTypeMatches($row, $section)
                ? (int) $section->id
                : null;
    }

    public function effectiveSubject(ScheduleImportRow $row): ?Subject
    {
        $subjectId = $this->effectiveSubjectId($row);

        return $subjectId ? Subject::withTrashed()->find($subjectId) : null;
    }

    public function effectiveSubjectSection(ScheduleImportRow $row): ?SubjectSection
    {
        $sectionId = $this->effectiveSubjectSectionId($row);

        return $sectionId ? SubjectSection::query()->find($sectionId) : null;
    }

    public function originalMatchedSubjectId(ScheduleImportRow $row): ?int
    {
        if ($this->hasIssue($row, self::SUBJECT_ISSUES)) {
            return null;
        }

        $slotIds = $this->originalSlotIds($row);
        $slotSubjectIds = SubjectSectionScheduleSlot::query()
            ->whereIn('id', $slotIds)
            ->pluck('subject_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $slotSubjectId = $slotSubjectIds->count() === 1 ? $slotSubjectIds->sole() : null;
        $sourceKey = (string) ($row->normalized_payload['subject_code_key'] ?? '');
        $sourceSubjectIds = $this->subjectIdsForKey($sourceKey);
        $sourceSubjectId = count($sourceSubjectIds) === 1 ? $sourceSubjectIds[0] : null;

        if ($slotSubjectId && $sourceSubjectId && $slotSubjectId !== $sourceSubjectId) {
            return null;
        }

        return $slotSubjectId ?? $sourceSubjectId;
    }

    public function originalMatchedSubjectSectionId(ScheduleImportRow $row): ?int
    {
        if ($this->hasIssue($row, self::SECTION_ISSUES)) {
            return null;
        }

        $subjectId = $this->originalMatchedSubjectId($row);

        if (! $subjectId) {
            return null;
        }

        $slotIds = $this->originalSlotIds($row);
        $slotSectionIds = SubjectSectionScheduleSlot::query()
            ->whereIn('id', $slotIds)
            ->pluck('subject_section_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $slotSectionId = $slotSectionIds->count() === 1 ? $slotSectionIds->sole() : null;
        $sectionCode = SubjectSection::normalizeCode((string) ($row->normalized_payload['section_code'] ?? ''));
        $sourceSectionIds = $this->sectionIdsForScope($row, $subjectId, $sectionCode);
        $sourceSectionId = count($sourceSectionIds) === 1 ? $sourceSectionIds[0] : null;

        if ($slotSectionId && $sourceSectionId && $slotSectionId !== $sourceSectionId) {
            return null;
        }

        return $slotSectionId ?? $sourceSectionId;
    }

    /** @return array<int, int> */
    private function subjectIdsForKey(string $sourceKey): array
    {
        if ($sourceKey === '') {
            return [];
        }

        return $this->subjectMatches[$sourceKey] ??= Subject::query()
            ->withoutTrashed()
            ->get(['id', 'code'])
            ->filter(fn (Subject $subject): bool => $this->normalizer->normalizeKey($subject->code) === $sourceKey)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /** @return array<int, int> */
    private function sectionIdsForScope(ScheduleImportRow $row, int $subjectId, string $sectionCode): array
    {
        if ($sectionCode === '') {
            return [];
        }

        $cacheKey = implode('|', [$row->academic_term_id, $subjectId, $sectionCode, $row->normalized_payload['section_type'] ?? '']);

        return $this->sectionMatches[$cacheKey] ??= SubjectSection::query()
            ->where('academic_term_id', $row->academic_term_id)
            ->where('subject_id', $subjectId)
            ->get()
            ->filter(fn (SubjectSection $section): bool => SubjectSection::normalizeCode($section->code) === $sectionCode
                && $this->sectionTypeMatches($row, $section))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    private function sectionTypeMatches(ScheduleImportRow $row, SubjectSection $section): bool
    {
        return match ($row->normalized_payload['section_type'] ?? null) {
            'T' => $section->section_type === Subject::TYPE_THEORETICAL,
            'P' => $section->section_type === Subject::TYPE_PRACTICAL,
            default => true,
        };
    }

    private function uniqueLegacyIssueId(ScheduleImportRow $row, string $column): ?int
    {
        $row->loadMissing('issues');
        $ids = $row->issues
            ->pluck($column)
            ->filter(fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        return $ids->count() === 1 ? $ids->sole() : null;
    }

    /** @param array<int, string> $types */
    private function hasIssue(ScheduleImportRow $row, array $types): bool
    {
        $row->loadMissing('issues');

        return $row->issues->whereIn('issue_type', $types)->isNotEmpty();
    }

    /** @return array<int, int> */
    private function originalSlotIds(ScheduleImportRow $row): array
    {
        return collect($row->import_result['slot_ids'] ?? [])
            ->filter(fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function positiveId(mixed $id): ?int
    {
        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }
}
