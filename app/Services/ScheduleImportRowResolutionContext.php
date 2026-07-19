<?php

namespace App\Services;

use App\Models\Hall;
use App\Models\Lecturer;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportRow;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Support\WeeklyScheduleRowNormalizer;

class ScheduleImportRowResolutionContext
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_ORIGINAL_EXACT_MATCH = 'original_exact_match';

    public const SOURCE_NONE = 'none';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_MISSING = 'missing';

    public const STATUS_AMBIGUOUS = 'ambiguous';

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

    /** @var array<string, array<int, int>> */
    private array $lecturerMatches = [];

    /** @var array<string, array<int, int>> */
    private array $hallMatches = [];

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

    /** @return array{id: ?int, source: string, status: string, match_ids: array<int, int>} */
    public function effectiveLecturerResolution(ScheduleImportRow $row): array
    {
        $manualId = $this->positiveId($row->resolved_lecturer_id);

        if ($manualId && Lecturer::query()->whereKey($manualId)->exists()) {
            return $this->resolvedIdentity($manualId, self::SOURCE_MANUAL);
        }

        $sourceKey = $this->sourceIdentityKey($row, 'teacher_name');

        if ($sourceKey === null) {
            return $this->unresolvedIdentity(self::STATUS_MISSING);
        }

        $ids = $this->lecturerMatches[$sourceKey] ??= Lecturer::query()
            ->get(['id', 'name', 'canonical_name'])
            ->filter(fn (Lecturer $lecturer): bool => $this->normalizer->normalizeKey($lecturer->canonical_name ?: $lecturer->name) === $sourceKey)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $this->identityFromExactMatches($ids);
    }

    /** @return array{id: ?int, source: string, status: string, match_ids: array<int, int>} */
    public function effectiveHallResolution(ScheduleImportRow $row): array
    {
        $manualId = $this->positiveId($row->resolved_hall_id);

        if ($manualId && Hall::query()->withoutTrashed()->whereKey($manualId)->exists()) {
            return $this->resolvedIdentity($manualId, self::SOURCE_MANUAL);
        }

        $sourceKey = $this->sourceIdentityKey($row, 'hall_name');

        if ($sourceKey === null) {
            return $this->unresolvedIdentity(self::STATUS_MISSING);
        }

        $ids = $this->hallMatches[$sourceKey] ??= Hall::query()
            ->withoutTrashed()
            ->get(['id', 'code', 'name'])
            ->filter(fn (Hall $hall): bool => in_array($sourceKey, array_filter([
                $this->normalizer->normalizeKey($hall->code),
                $this->normalizer->normalizeKey($hall->name),
            ]), true))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $this->identityFromExactMatches($ids);
    }

    public function effectiveLecturerId(ScheduleImportRow $row): ?int
    {
        return $this->effectiveLecturerResolution($row)['id'];
    }

    public function effectiveHallId(ScheduleImportRow $row): ?int
    {
        return $this->effectiveHallResolution($row)['id'];
    }

    public function effectiveLecturer(ScheduleImportRow $row): ?Lecturer
    {
        $id = $this->effectiveLecturerId($row);

        return $id ? Lecturer::query()->find($id) : null;
    }

    public function effectiveHall(ScheduleImportRow $row): ?Hall
    {
        $id = $this->effectiveHallId($row);

        return $id ? Hall::query()->withoutTrashed()->find($id) : null;
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

    private function sourceIdentityKey(ScheduleImportRow $row, string $field): ?string
    {
        $sourceField = $field.'_source';
        $value = array_key_exists($sourceField, $row->normalized_payload ?? [])
            ? $row->normalized_payload[$sourceField]
            : ($row->source_payload[$field] ?? null);

        return $this->normalizer->isMissingValue($value)
            ? null
            : $this->normalizer->normalizeKey($value);
    }

    /** @param array<int, int> $ids
     * @return array{id: ?int, source: string, status: string, match_ids: array<int, int>}
     */
    private function identityFromExactMatches(array $ids): array
    {
        return match (count($ids)) {
            1 => $this->resolvedIdentity($ids[0], self::SOURCE_ORIGINAL_EXACT_MATCH),
            0 => $this->unresolvedIdentity(self::STATUS_MISSING),
            default => $this->unresolvedIdentity(self::STATUS_AMBIGUOUS, $ids),
        };
    }

    /** @return array{id: int, source: string, status: string, match_ids: array<int, int>} */
    private function resolvedIdentity(int $id, string $source): array
    {
        return ['id' => $id, 'source' => $source, 'status' => self::STATUS_RESOLVED, 'match_ids' => [$id]];
    }

    /** @param array<int, int> $ids
     * @return array{id: null, source: string, status: string, match_ids: array<int, int>}
     */
    private function unresolvedIdentity(string $status, array $ids = []): array
    {
        return ['id' => null, 'source' => self::SOURCE_NONE, 'status' => $status, 'match_ids' => $ids];
    }
}
