<?php

namespace App\Support;

use Normalizer;

class AcademicTermNormalizer
{
    public function displayName(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        if (class_exists(Normalizer::class)) {
            $value = Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;
        }

        $value = preg_replace('/[\p{Z}\s]+/u', ' ', $value) ?? $value;
        $value = trim($value);

        if ($value === '' || $value === '-' || mb_strtolower($value) === 'nan') {
            return null;
        }

        return $value;
    }

    public function canonicalName(mixed $value): ?string
    {
        $value = $this->displayName($value);

        if ($value === null) {
            return null;
        }

        return mb_strtolower($this->convertDigits($value));
    }

    private function convertDigits(string $value): string
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
