<?php

namespace App\Services;

use App\Models\Subject;
use App\Models\SubjectSection;
use App\Support\WeeklyScheduleRowNormalizer;
use Illuminate\Support\Collection;

class ScheduleImportSuggestionService
{
    public function __construct(private readonly WeeklyScheduleRowNormalizer $normalizer) {}

    public function suggest(array $row, int $academicTermId): array
    {
        $sourceCode = (string) ($row['subject_code'] ?? '');
        $codeKeys = array_values(array_unique(array_filter([
            $this->normalizer->normalizeKey($sourceCode),
            $this->normalizer->normalizeKey($this->removeOneOuterBracketPair($sourceCode)),
        ])));
        $nameKey = $this->normalizer->normalizeKey($row['subject_name'] ?? null);

        /** @var Collection<int, Subject> $subjects */
        $subjects = Subject::query()
            ->withoutTrashed()
            ->with(['department.faculty'])
            ->get(['id', 'code', 'name', 'department_id'])
            ->filter(function (Subject $subject) use ($codeKeys, $nameKey): bool {
                return in_array($this->normalizer->normalizeKey($subject->code), $codeKeys, true)
                    || ($nameKey !== '' && $this->normalizer->normalizeKey($subject->name) === $nameKey);
            })
            ->values();

        return $subjects->map(function (Subject $subject) use ($row, $academicTermId, $sourceCode): array {
            $sectionCode = SubjectSection::normalizeCode($row['section_code'] ?? '');
            $sections = SubjectSection::query()
                ->where('academic_term_id', $academicTermId)
                ->where('subject_id', $subject->id)
                ->when($sectionCode !== '', fn ($query) => $query->where('code', $sectionCode))
                ->get(['id', 'subject_id', 'academic_term_id', 'code', 'section_type'])
                ->map(fn (SubjectSection $section): array => [
                    'id' => $section->id,
                    'code' => $section->code,
                    'section_type' => $section->section_type,
                ])->all();

            return [
                'subject' => [
                    'id' => $subject->id,
                    'code' => $subject->code,
                    'name' => $subject->name,
                    'department' => $subject->department?->name,
                    'faculty' => $subject->department?->faculty?->name,
                ],
                'sections' => $sections,
                'match_reasons' => array_values(array_filter([
                    $this->normalizer->normalizeKey($subject->code) === $this->normalizer->normalizeKey($sourceCode)
                        ? 'exact_code' : null,
                    $this->normalizer->normalizeKey($subject->code) === $this->normalizer->normalizeKey($this->removeOneOuterBracketPair($sourceCode))
                        && $sourceCode !== $this->removeOneOuterBracketPair($sourceCode)
                        ? 'outer_brackets_removed' : null,
                    $this->normalizer->normalizeKey($subject->name) === $this->normalizer->normalizeKey($row['subject_name'] ?? null)
                        ? 'exact_name' : null,
                ])),
                'source_restrictions' => [
                    'faculty' => $row['subject_faculty'] ?? null,
                    'faculties' => $row['restricted_faculties'] ?? null,
                    'departments' => $row['restricted_departments'] ?? null,
                ],
            ];
        })->all();
    }

    public function removeOneOuterBracketPair(string $value): string
    {
        $value = trim($value);

        return preg_match('/^\[([^\[\]]+)\]$/u', $value, $matches) === 1
            ? trim($matches[1])
            : $value;
    }
}
