<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A rule-level rejection for a proposed lecturer or hall assignment.
 * The details come exclusively from the server-side conflict detector.
 */
class ScheduleAssignmentConflictException extends RuntimeException
{
    /**
     * @param  array<int, array{conflictType: string, selectedResourceId: int|null, conflictingSlotId: int, weekday: int|string, startTime: string, endTime: string, subjectName: string, sectionCode: string, lecturerName: string, hallLabel: string}>  $conflicts
     */
    public function __construct(
        public readonly string $conflictType,
        public readonly ?int $selectedResourceId,
        public readonly array $conflicts,
    ) {
        parent::__construct('The proposed schedule assignment conflicts with an existing weekly slot.');
    }
}
