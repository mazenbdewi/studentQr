<?php

namespace App\Services;

/**
 * Immutable, term-scoped result shared by the generation preview, issue page,
 * counters, and exports.  Counts always describe the full source result; the
 * caller may filter the rows without accidentally changing those counts.
 */
final readonly class WeeklyScheduleIssueResult
{
    /**
     * @param  array<string, mixed>  $preview
     * @param  array<int, array<string, mixed>>  $issues
     * @param  array<string, int>  $issueCountsByKey
     */
    public function __construct(
        public int $academicTermId,
        public ?int $importBatchId,
        public array $preview,
        public array $issues,
        public int $uniqueAffectedSlots,
        public array $issueCountsByKey,
        public int $readySlots,
        public int $excludedSlots,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'academic_term_id' => $this->academicTermId,
            'import_batch_id' => $this->importBatchId,
            'preview' => $this->preview,
            // Backwards-compatible issue-only projection. The full, stateful
            // slot collection is intentionally exposed as `issues`.
            'rows' => array_values(array_filter($this->issues, fn (array $row): bool => $row['status'] !== 'resolved')),
            'issues' => $this->issues,
            'blocked_unique_count' => $this->uniqueAffectedSlots,
            'unique_affected_slots' => $this->uniqueAffectedSlots,
            'reason_counts' => $this->issueCountsByKey,
            'issue_counts_by_key' => $this->issueCountsByKey,
            'ready_slots' => $this->readySlots,
            'excluded_slots' => $this->excludedSlots,
        ];
    }
}
