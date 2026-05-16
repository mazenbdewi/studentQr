<?php

namespace App\Rules;

use App\Models\Subject;
use App\Models\SubjectSection;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SubjectSectionCodeMatchesSubjectType implements ValidationRule
{
    public function __construct(
        private readonly Subject $subject,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! SubjectSection::codeMatchesSubjectType($value, $this->subject)) {
            $fail(SubjectSection::validationMessageForSubject($this->subject));
        }
    }
}
