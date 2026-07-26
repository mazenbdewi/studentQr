<?php

namespace App\Support;

use App\Models\AcademicTerm;
use App\Models\AppSetting;

/** The sole source of truth for the daily operational academic term. */
class AcademicTermContext
{
    public function current(): ?AcademicTerm
    {
        $id = $this->currentId();

        return $id === null ? null : AcademicTerm::query()->find($id);
    }

    public function currentId(): ?int
    {
        $id = AppSetting::integer(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY);

        return $id > 0 ? $id : null;
    }

    public function requireCurrent(): AcademicTerm
    {
        return $this->current() ?? throw new \RuntimeException('لا يوجد فصل دراسي حالي محدد. يرجى تعيين الفصل الدراسي الحالي من إدارة الفصل الدراسي الحالي.');
    }

    public function isCurrent(AcademicTerm $term): bool
    {
        return $this->currentId() === (int) $term->id;
    }
}
