<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class ManaraEnrollmentRowNormalizer
{
    /** @var array<int, string> */
    private const REQUIRED_HEADINGS = [
        'الرقم الجامعي',
        'اسم الطالب',
        'الكلية',
        'الاختصاص',
        'اسم المقرر',
        'رمز المقرر',
        'تاريخ التسجيل',
        'الفصل الدراسي',
        'رمز الفئة النظرية',
        'رمز الفئة العملية',
    ];

    /** @var array<string, int> */
    private array $headingIndexes = [];

    /** @var array<string, string> */
    private array $columnMap = [
        'الرقم الجامعي' => 'student_number',
        'اسم الطالب' => 'student_name',
        'الكلية' => 'faculty_name',
        'الاختصاص' => 'department_name',
        'اسم المقرر' => 'subject_name',
        'رمز المقرر' => 'subject_code',
        'تاريخ التسجيل' => 'registration_date',
        'الفصل الدراسي' => 'academic_term_name',
        'رمز الفئة النظرية' => 'theoretical_section_number',
        'رمز الفئة العملية' => 'practical_section_number',
        'مستوى المقرر' => 'course_level',
    ];

    public function __construct(
        private readonly AcademicTermNormalizer $academicTermNormalizer,
    ) {}

    /** @return array<int, string> */
    public function captureHeadings(array $headings): array
    {
        $this->headingIndexes = [];

        foreach ($headings as $index => $heading) {
            $mapped = $this->columnMap[$this->normalizeHeading($heading)] ?? null;

            if ($mapped) {
                $this->headingIndexes[$mapped] = $index;
            }
        }

        return array_values(array_filter(
            self::REQUIRED_HEADINGS,
            fn (string $heading): bool => ! array_key_exists($this->columnMap[$heading], $this->headingIndexes),
        ));
    }

    public function hasAcademicTermHeading(): bool
    {
        return array_key_exists('academic_term_name', $this->headingIndexes);
    }

    public function mapRow(array $values): array
    {
        $row = [];

        foreach ($this->columnMap as $target) {
            $row[$target] = array_key_exists($target, $this->headingIndexes)
                ? ($values[$this->headingIndexes[$target]] ?? null)
                : null;
        }

        return $row;
    }

    public function normalizeRow(array $row): array
    {
        $row['theoretical_section_source_value'] = $row['theoretical_section_number'] ?? null;
        $row['practical_section_source_value'] = $row['practical_section_number'] ?? null;

        foreach ([
            'student_number',
            'student_name',
            'faculty_name',
            'department_name',
            'subject_name',
            'subject_code',
        ] as $field) {
            $row[$field] = $this->normalizeValue($row[$field] ?? null);
        }

        $row['_zero_section_values'] = (int) $this->isZeroSectionValue($row['theoretical_section_number'] ?? null)
            + (int) $this->isZeroSectionValue($row['practical_section_number'] ?? null);
        $row['theoretical_section_number'] = $this->normalizeSectionNumber($row['theoretical_section_number'] ?? null);
        $row['practical_section_number'] = $this->normalizeSectionNumber($row['practical_section_number'] ?? null);
        $row['registration_date'] = $this->parseRegistrationDate($row['registration_date'] ?? null);
        $row['course_level'] = $this->normalizeCourseLevel($row['course_level'] ?? null);
        $row['academic_term_display_name'] = $this->academicTermNormalizer->displayName($row['academic_term_name'] ?? null);
        $row['academic_term_canonical_name'] = $this->academicTermNormalizer->canonicalName($row['academic_term_name'] ?? null);

        return $row;
    }

    /** @return array<int, string> */
    public function validateRow(array $row): array
    {
        $messages = [];

        $required = [
            'student_number' => 'الرقم الجامعي مطلوب / University number is required.',
            'student_name' => 'اسم الطالب مطلوب / Student name is required.',
            'faculty_name' => 'الكلية مطلوبة / College is required.',
            'department_name' => 'الاختصاص مطلوب / Specialization is required.',
            'subject_name' => 'اسم المقرر مطلوب / Subject name is required.',
            'subject_code' => 'رمز المقرر مطلوب / Subject code is required.',
            'academic_term_canonical_name' => 'الفصل الدراسي مطلوب / Academic term is required.',
        ];

        foreach ($required as $field => $message) {
            if (($row[$field] ?? null) === null) {
                $messages[] = $message;
            }
        }

        if (mb_strlen((string) ($row['academic_term_display_name'] ?? '')) > 255) {
            $messages[] = 'اسم الفصل الدراسي أطول من الحد المسموح / Academic term name is too long.';
        }

        if (($row['registration_date'] ?? null) === null) {
            $messages[] = 'تاريخ التسجيل غير صالح / Registration date is invalid.';
        }

        if (($row['course_level'] ?? null) !== null && ((int) $row['course_level'] < 1 || (int) $row['course_level'] > 6)) {
            $messages[] = 'مستوى المقرر غير صالح / Course level is invalid.';
        }

        if (($row['theoretical_section_number'] ?? null) === null && ($row['practical_section_number'] ?? null) === null) {
            $messages[] = 'يجب إدخال رمز الفئة النظرية أو رمز الفئة العملية على الأقل / At least one theoretical or practical section code is required.';
        }

        return $messages;
    }

    public function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->normalizeValue($value) !== null) {
                return false;
            }
        }

        return true;
    }

    public function normalizeKey(mixed $value): string
    {
        return Str::lower($this->normalizeValue($value) ?? '');
    }

    public function integerSectionNumber(mixed $value): ?int
    {
        $value = $this->normalizeSectionNumber($value);

        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }

    private function normalizeValue(mixed $value): ?string
    {
        if ($this->isEmptyValue($value)) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        $value = $this->convertDigits((string) $value);
        $value = preg_replace('/[\p{Z}\s]+/u', ' ', $value) ?? $value;
        $value = trim($value);

        return $this->isEmptyValue($value) ? null : $value;
    }

    private function normalizeSectionNumber(mixed $value): ?string
    {
        if ($this->isZeroSectionValue($value)) {
            return null;
        }

        $value = $this->normalizeValue($value);

        if ($value === null) {
            return null;
        }

        $value = preg_replace('/^[TP]\s*/i', '', Str::upper($value)) ?? $value;

        if (preg_match('/^\d+\.0+$/', $value)) {
            $value = (string) ((int) $value);
        }

        $value = trim($value);

        return $this->isZeroSectionValue($value) ? null : ($value === '' ? null : $value);
    }

    private function isZeroSectionValue(mixed $value): bool
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value === 0.0;
        }

        if ($value === null) {
            return false;
        }

        $value = $this->convertDigits(trim((string) $value));
        $value = preg_replace('/^[TP]\s*/i', '', Str::upper($value)) ?? $value;

        return preg_match('/^0+(?:\.0+)?$/', trim($value)) === 1;
    }

    private function normalizeCourseLevel(mixed $value): ?int
    {
        $value = $this->normalizeValue($value);

        if ($value === null) {
            return null;
        }

        $value = Str::lower($value);
        $value = str_replace(['السنة', 'سنة', 'المستوى', 'مستوى', 'year', 'level'], '', $value);
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        $words = [
            'اولى' => 1, 'الأولى' => 1, 'الاولى' => 1, 'اول' => 1, 'الأول' => 1, 'الاول' => 1,
            'ثانية' => 2, 'الثانية' => 2, 'ثاني' => 2, 'الثاني' => 2,
            'ثالثة' => 3, 'الثالثة' => 3, 'ثالث' => 3, 'الثالث' => 3,
            'رابعة' => 4, 'الرابعة' => 4, 'رابع' => 4, 'الرابع' => 4,
            'خامسة' => 5, 'الخامسة' => 5, 'خامس' => 5, 'الخامس' => 5,
            'سادسة' => 6, 'السادسة' => 6, 'سادس' => 6, 'السادس' => 6,
        ];

        if (array_key_exists($value, $words)) {
            return $words[$value];
        }

        if (preg_match('/^\d+\.0+$/', $value)) {
            $value = (string) ((int) $value);
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        if (preg_match('/\d+/', $value, $matches)) {
            return (int) $matches[0];
        }

        return null;
    }

    private function parseRegistrationDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        if ($this->isEmptyValue($value)) {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }

        $value = $this->normalizeValue($value);

        if ($value === null) {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
                $errors = Carbon::getLastErrors();

                if ($date && ($errors === false || ((int) $errors['warning_count'] === 0 && (int) $errors['error_count'] === 0))) {
                    return $date->toDateString();
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function isEmptyValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        $value = trim((string) $value);

        return $value === '' || Str::lower($value) === 'nan' || $value === '-';
    }

    private function normalizeHeading(mixed $value): string
    {
        return $this->normalizeValue($value) ?? '';
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
