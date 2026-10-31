<?php

namespace App\Support;

use App\Models\AcademicTerm;
use App\Models\AppSetting;

/** The sole source of truth for the daily operational academic term. */
class AcademicTermContext
{
    private bool $resolved = false;

    private ?int $resolvedId = null;

    private bool $termResolved = false;

    private ?AcademicTerm $resolvedTerm = null;

    public function current(): ?AcademicTerm
    {
        return $this->currentOrNull();
    }

    /**
     * Resolves the configured term for UI checks without relaxing required-term operations.
     */
    public function currentOrNull(): ?AcademicTerm
    {
        if ($this->termResolved) {
            return $this->resolvedTerm;
        }

        $id = $this->currentId();

        $this->resolvedTerm = $id === null ? null : AcademicTerm::query()->find($id);
        $this->termResolved = true;

        return $this->resolvedTerm;
    }

    public function currentId(): ?int
    {
        if ($this->resolved) {
            return $this->resolvedId;
        }

        $id = AppSetting::integer(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY);

        $this->resolvedId = $id > 0 ? $id : null;
        $this->resolved = true;

        return $this->resolvedId;
    }

    public function hasCurrent(): bool
    {
        return $this->currentId() !== null;
    }

    public function requireCurrent(): AcademicTerm
    {
        return $this->currentOrNull() ?? throw new \RuntimeException(__('academic-term.no_current'));
    }

    public function isCurrent(AcademicTerm $term): bool
    {
        return $this->currentId() === (int) $term->id;
    }

    public function forget(): void
    {
        $this->resolved = false;
        $this->resolvedId = null;
        $this->termResolved = false;
        $this->resolvedTerm = null;
    }
}
