<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;
use Normalizer;

class WeeklyScheduleRowNormalizer
{
    /** @var array<string, string> */
    private const REQUIRED_COLUMN_MAP = [
        'رمز الشعبة' => 'subject_code',
        'نوع الفئة' => 'section_type',
        'رمز الفئة' => 'section_number',
        'اسم المدرس' => 'teacher_name',
        'اسم القاعة' => 'hall_name',
        'سعة الفئة' => 'section_capacity',
        'عدد الطلاب' => 'expected_student_count',
    ];

    /** @var array<string, string> */
    private const OPTIONAL_COLUMN_MAP = [
        'اسم المقرر الأساسي' => 'subject_name',
        'كلية المقرر الأساسي' => 'subject_faculty',
        'محصور بالكليات' => 'restricted_faculties',
        'محصور بالاختصاصات' => 'restricted_departments',
    ];

    /** @var array<string, int> */
    public const WEEKDAYS = [
        'الاثنين' => 1,
        'الثلاثاء' => 2,
        'الأربعاء' => 3,
        'الخميس' => 4,
        'الجمعة' => 5,
        'السبت' => 6,
        'الأحد' => 7,
    ];

    /** @var array<string, int> */
    private array $columnIndexes = [];

    /** @var array<int, int> */
    private array $weekdayIndexes = [];

    /** @return array<int, string> */
    public function captureHeadings(array $headings): array
    {
        $this->columnIndexes = [];
        $this->weekdayIndexes = [];

        foreach (array_values($headings) as $index => $heading) {
            $normalized = $this->normalizeHeading($heading);

            $columnMap = self::REQUIRED_COLUMN_MAP + self::OPTIONAL_COLUMN_MAP;

            if (isset($columnMap[$normalized])) {
                $this->columnIndexes[$columnMap[$normalized]] = $index;
            }

            if (isset(self::WEEKDAYS[$normalized])) {
                $this->weekdayIndexes[self::WEEKDAYS[$normalized]] = $index;
            }
        }

        $missing = [];

        foreach (self::REQUIRED_COLUMN_MAP as $heading => $field) {
            if (! array_key_exists($field, $this->columnIndexes)) {
                $missing[] = $heading;
            }
        }

        if ($this->weekdayIndexes === []) {
            $missing[] = 'أحد أعمدة أيام الأسبوع';
        }

        return $missing;
    }

    public function mapRow(array $values, int $rowNumber): array
    {
        $values = array_values($values);
        $row = ['row_number' => $rowNumber, 'weekday_values' => []];

        foreach ($this->columnIndexes as $field => $index) {
            $row[$field] = $values[$index] ?? null;
        }

        foreach ($this->weekdayIndexes as $weekday => $index) {
            $row['weekday_values'][$weekday] = $values[$index] ?? null;
        }

        return $row;
    }

    public function normalizeRow(array $row): array
    {
        $row['subject_code_source'] = $row['subject_code'] ?? null;
        $row['section_type_source'] = $row['section_type'] ?? null;
        $row['section_number_source'] = $row['section_number'] ?? null;
        $row['teacher_name_source'] = $row['teacher_name'] ?? null;
        $row['hall_name_source'] = $row['hall_name'] ?? null;
        $row['subject_name_source'] = $row['subject_name'] ?? null;
        $row['subject_faculty_source'] = $row['subject_faculty'] ?? null;
        $row['restricted_faculties_source'] = $row['restricted_faculties'] ?? null;
        $row['restricted_departments_source'] = $row['restricted_departments'] ?? null;

        $row['subject_code'] = $this->normalizeText($row['subject_code'] ?? null);
        $row['subject_code_key'] = $this->normalizeKey($row['subject_code']);
        $row['section_type'] = $this->normalizeSectionType($row['section_type'] ?? null);
        $row['section_number'] = $this->normalizeSectionNumber($row['section_number'] ?? null);
        $row['section_code'] = $row['section_type'] && $row['section_number']
            ? Str::upper($row['section_type']).$row['section_number']
            : null;
        $row['teacher_name'] = $this->normalizeIdentityText($row['teacher_name'] ?? null);
        $row['teacher_name_key'] = $this->normalizeKey($row['teacher_name']);
        $row['hall_name'] = $this->normalizeIdentityText($row['hall_name'] ?? null);
        $row['hall_name_key'] = $this->normalizeKey($row['hall_name']);
        $row['section_capacity'] = $this->normalizeNonNegativeInteger($row['section_capacity'] ?? null);
        $row['expected_student_count'] = $this->normalizeNonNegativeInteger($row['expected_student_count'] ?? null);
        $row['subject_name'] = $this->normalizeText($row['subject_name'] ?? null);
        $row['subject_name_key'] = $this->normalizeKey($row['subject_name']);
        $row['subject_faculty'] = $this->normalizeText($row['subject_faculty'] ?? null);
        $row['restricted_faculties'] = $this->normalizeText($row['restricted_faculties'] ?? null);
        $row['restricted_departments'] = $this->normalizeText($row['restricted_departments'] ?? null);

        return $row;
    }

    /** @return array<int, string> */
    public function validateCore(array $row): array
    {
        $messages = [];

        if (($row['subject_code'] ?? null) === null) {
            $messages[] = 'رمز الشعبة مطلوب.';
        }

        if (! in_array($row['section_type'] ?? null, ['T', 'P'], true)) {
            $messages[] = 'نوع الفئة يجب أن يكون T أو P.';
        }

        if (($row['section_number'] ?? null) === null) {
            $messages[] = 'رمز الفئة مطلوب ويجب ألا يكون صفراً.';
        }

        if (in_array($row['section_code'] ?? null, ['T0', 'P0'], true)) {
            $messages[] = 'لا يمكن استخدام T0 أو P0.';
        }

        return $messages;
    }

    /** @return array{start_time: string, end_time: string}|null */
    public function parseTimeRange(mixed $value): ?array
    {
        if ($this->isMissingValue($value)) {
            return null;
        }

        $value = $this->normalizeText($value);

        if ($value === null) {
            return null;
        }

        $parts = preg_split('/\s*[-–—]\s*/u', $value);

        if (! is_array($parts) || count($parts) !== 2) {
            throw new \InvalidArgumentException("نطاق الوقت غير صالح: {$value}");
        }

        $start = $this->parseTime($parts[0]);
        $end = $this->parseTime($parts[1]);

        if ($start === null || $end === null || $end <= $start) {
            throw new \InvalidArgumentException("نطاق الوقت غير صالح: {$value}");
        }

        return [
            'start_time' => $start->format('H:i:s'),
            'end_time' => $end->format('H:i:s'),
        ];
    }

    public function rowIsEmpty(array $row): bool
    {
        foreach ($row as $key => $value) {
            if ($key === 'row_number') {
                continue;
            }

            if (is_array($value)) {
                if (! $this->rowIsEmpty($value)) {
                    return false;
                }

                continue;
            }

            if (! $this->isMissingValue($value)) {
                return false;
            }
        }

        return true;
    }

    public function normalizeKey(mixed $value): string
    {
        return Str::lower($this->normalizeText($value) ?? '');
    }

    public function weekdayName(int $weekday): string
    {
        return array_search($weekday, self::WEEKDAYS, true) ?: (string) $weekday;
    }

    public function isMissingValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value === 0.0;
        }

        if ($value instanceof DateTimeInterface) {
            return false;
        }

        $value = trim($this->convertDigits((string) $value));

        return $value === ''
            || $value === '-'
            || mb_strtolower($value) === 'nan'
            || preg_match('/^0+(?:\.0+)?$/', $value) === 1;
    }

    private function normalizeHeading(mixed $value): string
    {
        return $this->normalizeText($value) ?? '';
    }

    private function normalizeIdentityText(mixed $value): ?string
    {
        return $this->isMissingValue($value) ? null : $this->normalizeText($value);
    }

    private function normalizeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('H:i:s');
        }

        $value = (string) $value;

        if (class_exists(Normalizer::class)) {
            $value = Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;
        }

        $value = $this->convertDigits($value);
        $value = preg_replace('/[\p{Z}\s]+/u', ' ', $value) ?? $value;
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function normalizeSectionType(mixed $value): ?string
    {
        $value = Str::upper($this->normalizeText($value) ?? '');

        return match ($value) {
            'T', 'THEORY', 'THEORETICAL', 'نظري' => 'T',
            'P', 'PRACTICAL', 'عملي' => 'P',
            default => null,
        };
    }

    private function normalizeSectionNumber(mixed $value): ?string
    {
        if ($this->isMissingValue($value)) {
            return null;
        }

        $value = Str::upper($this->normalizeText($value) ?? '');
        $value = preg_replace('/^[TP]\s*/', '', $value) ?? $value;

        if (preg_match('/^\d+\.0+$/', $value)) {
            $value = (string) ((int) $value);
        }

        return preg_match('/^\d+$/', $value) === 1 && (int) $value > 0 ? $value : null;
    }

    private function normalizeNonNegativeInteger(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '' || mb_strtolower(trim((string) $value)) === 'nan') {
            return null;
        }

        $value = $this->convertDigits(trim((string) $value));

        if (! is_numeric($value) || (float) $value < 0 || floor((float) $value) !== (float) $value) {
            return null;
        }

        return (int) $value;
    }

    private function parseTime(string $value): ?DateTimeImmutable
    {
        $value = Str::upper(preg_replace('/\s+/', '', trim($value)) ?? trim($value));

        foreach (['!g:iA', '!h:iA', '!G:i', '!H:i', '!G:i:s', '!H:i:s'] as $format) {
            $time = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            if ($time !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $time;
            }
        }

        return null;
    }

    private function convertDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }
}
