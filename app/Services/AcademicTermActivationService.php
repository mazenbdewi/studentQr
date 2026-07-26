<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AcademicTermContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AcademicTermActivationService
{
    public function activate(AcademicTerm|int $term, User $actor): AcademicTerm
    {
        Gate::forUser($actor)->authorize('manageAcademicTerms');

        $activated = DB::transaction(function () use ($term, $actor): AcademicTerm {
            $target = $term instanceof AcademicTerm
                ? AcademicTerm::query()->lockForUpdate()->findOrFail($term->id)
                : AcademicTerm::query()->lockForUpdate()->findOrFail($term);
            $previousId = app(AcademicTermContext::class)->currentId();
            $previous = $previousId ? AcademicTerm::query()->lockForUpdate()->find($previousId) : null;

            if ($previous && $previous->is($target)) {
                return $target;
            }

            if ($previous && array_key_exists('is_archived', $previous->getAttributes())) {
                $previous->forceFill(['is_archived' => true])->save();
            }

            if (array_key_exists('is_archived', $target->getAttributes())) {
                $target->forceFill(['is_archived' => false])->save();
            }

            AppSetting::put(AppSetting::CURRENT_ACADEMIC_TERM_ID_KEY, (string) $target->id);

            AuditLog::query()->create([
                'user_id' => $actor->id,
                'category' => 'academic_term',
                'action' => 'activated',
                'model_type' => AcademicTerm::class,
                'model_id' => $target->id,
                'description' => 'تم تعيين الفصل الدراسي الحالي.',
                'old_values' => ['current_academic_term_id' => $previous?->id],
                'new_values' => ['current_academic_term_id' => $target->id],
                'severity' => 'info',
            ]);

            return $target;
        });

        $this->clearCaches();

        return $activated;
    }

    public function clearCaches(): void
    {
        Cache::forget('academic-term-context');
    }
}
