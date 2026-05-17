<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubjectSection extends Model
{
    protected $fillable = [
        'subject_id',
        'lecturer_id',
        'section_type',
        'code',
        'section_number',
        'raw_section_number',
        'name',
        'capacity',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'lecturer_id' => 'integer',
        'section_number' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $section): void {
            $section->section_type = self::normalizeSectionType($section->section_type)
                ?? self::inferSectionTypeFromCode($section->code)
                ?? Subject::TYPE_THEORETICAL;
            $section->code = self::normalizeCodeForType($section->code, $section->section_type);
            $section->raw_section_number = self::normalizeRawSectionNumber($section->raw_section_number)
                ?? self::rawSectionNumberFromCode($section->code);
            $section->section_number = $section->section_number
                ?? self::integerSectionNumber($section->raw_section_number);

            if (! self::codeMatchesSectionType($section->code, $section->section_type)) {
                throw ValidationException::withMessages([
                    'code' => self::validationMessageForSectionType($section->section_type),
                ]);
            }
        });
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id')->withTrashed();
    }

    public function getSectionTypeLabelAttribute(): string
    {
        return Subject::subjectTypeOptions()[$this->section_type] ?? __('subjects.not_available');
    }

    public static function normalizeCode(mixed $code): string
    {
        return Str::upper(trim(self::convertDigits((string) $code)));
    }

    public static function normalizeCodeForType(mixed $code, ?string $sectionType): string
    {
        $normalized = self::normalizeCode($code);
        $sectionType = self::normalizeSectionType($sectionType);

        if (! $sectionType) {
            return $normalized;
        }

        $rawNumber = self::normalizeRawSectionNumber($normalized);

        if ($rawNumber !== null) {
            return self::prefixForSectionType($sectionType).$rawNumber;
        }

        return $normalized;
    }

    public static function normalizeSectionType(mixed $sectionType): ?string
    {
        if (blank($sectionType)) {
            return null;
        }

        return match (mb_strtolower(trim((string) $sectionType))) {
            Subject::TYPE_THEORETICAL, 'theory', 't', 'نظري' => Subject::TYPE_THEORETICAL,
            Subject::TYPE_PRACTICAL, 'practical', 'p', 'عملي' => Subject::TYPE_PRACTICAL,
            default => trim((string) $sectionType),
        };
    }

    public static function prefixForSectionType(?string $sectionType): string
    {
        return self::normalizeSectionType($sectionType) === Subject::TYPE_PRACTICAL ? 'P' : 'T';
    }

    public static function inferSectionTypeFromCode(mixed $code): ?string
    {
        $code = self::normalizeCode($code);

        if (Str::startsWith($code, 'P')) {
            return Subject::TYPE_PRACTICAL;
        }

        if (Str::startsWith($code, 'T')) {
            return Subject::TYPE_THEORETICAL;
        }

        return null;
    }

    public static function normalizeRawSectionNumber(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value) || is_int($value)) {
            return ((float) $value == (int) $value) ? (string) ((int) $value) : trim((string) $value);
        }

        $value = self::normalizeCode($value);
        $value = preg_replace('/^[TP]\s*/i', '', $value) ?? $value;

        if (preg_match('/^\d+\.0+$/', $value)) {
            $value = (string) ((int) $value);
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public static function rawSectionNumberFromCode(mixed $code): ?string
    {
        return self::normalizeRawSectionNumber($code);
    }

    public static function integerSectionNumber(mixed $value): ?int
    {
        $value = self::normalizeRawSectionNumber($value);

        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }

    public static function codeMatchesSectionType(mixed $code, ?string $sectionType): bool
    {
        $prefix = self::prefixForSectionType($sectionType);

        return filled($prefix) && Str::startsWith(self::normalizeCode($code), $prefix);
    }

    public static function codeMatchesSubjectType(mixed $code, Subject $subject): bool
    {
        $sectionType = self::inferSectionTypeFromCode($code) ?? $subject->subject_type;

        return self::codeMatchesSectionType($code, $sectionType);
    }

    public static function validationMessageForSubject(Subject $subject): string
    {
        return self::validationMessageForSectionType($subject->subject_type);
    }

    public static function validationMessageForSectionType(?string $sectionType): string
    {
        return self::normalizeSectionType($sectionType) === Subject::TYPE_PRACTICAL
            ? __('subjects.practical_section_code_must_start_with_p')
            : __('subjects.theory_section_code_must_start_with_t');
    }

    public static function nextCodeForSubject(Subject $subject, ?string $sectionType = null): string
    {
        $sectionType = self::normalizeSectionType($sectionType) ?? $subject->subject_type;
        $prefix = self::prefixForSectionType($sectionType);

        $maxNumber = $subject->sections()
            ->where('section_type', $sectionType)
            ->where('code', 'like', $prefix.'%')
            ->pluck('code')
            ->map(function (string $code) use ($prefix): int {
                $suffix = Str::after(self::normalizeCode($code), $prefix);

                return ctype_digit($suffix) ? (int) $suffix : 0;
            })
            ->max() ?? 0;

        return $prefix.($maxNumber + 1);
    }

    private static function convertDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
        ]);
    }
}
