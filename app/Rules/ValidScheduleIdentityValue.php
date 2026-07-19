<?php

namespace App\Rules;

use App\Support\WeeklyScheduleRowNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidScheduleIdentityValue implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (app(WeeklyScheduleRowNormalizer::class)->isMissingValue($value)) {
            $fail('schedule-import-reconciliation.validation.invalid_identity')->translate();
        }
    }
}
