<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectSection;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class ManaraStudentEnrollmentsImport extends StringValueBinder implements OnEachRow, WithChunkReading, SkipsEmptyRows, WithCustomValueBinder
{
    /** @var array<string, int> */
    private array $headingIndexes = [];

    /** @var array<string, Faculty> */
    private array $faculties = [];

    /** @var array<string, Department> */
    private array $departments = [];

    /** @var array<string, Student> */
    private array $students = [];

    /** @var array<string, Subject> */
    private array $subjects = [];

    /** @var array<string, SubjectSection> */
    private array $sections = [];

    /** @var array<string, Enrollment> */
    private array $enrollments = [];

    /** @var array<string, string> */
    private array $columnMap = [
        'الرقم الجامعي' => 'student_number',
        'اسم الطالب' => 'student_name',
        'الكلية' => 'faculty_name',
        'الاختصاص' => 'department_name',
        'اسم المقرر' => 'subject_name',
        'رمز المقرر' => 'subject_code',
        'تاريخ التسجيل' => 'registration_date',
        'رمز الفئة النظرية' => 'theoretical_section_number',
        'رمز الفئة العملية' => 'practical_section_number',
        'مستوى المقرر' => 'course_level',
    ];

    private array $summary = [
        'total_rows' => 0,
        'imported_rows' => 0,
        'skipped_rows' => 0,
        'created_students' => 0,
        'updated_students' => 0,
        'created_colleges' => 0,
        'created_specializations' => 0,
        'created_subjects' => 0,
        'updated_subjects' => 0,
        'created_theoretical_sections' => 0,
        'created_practical_sections' => 0,
        'created_enrollments' => 0,
        'updated_enrollments' => 0,
        'failed_rows' => 0,
    ];

    private array $errors = [];

    public function __construct()
    {
        $this->warmCaches();
    }

    public function onRow(Row $row): void
    {
        $rowNumber = $row->getIndex();
        $values = array_values($row->toArray());

        if ($rowNumber === 1) {
            $this->captureHeadings($values);

            return;
        }

        $this->summary['total_rows']++;
        $data = $this->mapRow($values);

        if ($this->rowIsEmpty($data)) {
            $this->summary['total_rows']--;

            return;
        }

        $data = $this->normalizeRow($data);
        $messages = $this->validateRow($data);

        if ($messages !== []) {
            $this->addError($rowNumber, $data, $messages);

            return;
        }

        try {
            DB::transaction(function () use ($data): void {
                $faculty = $this->resolveFaculty($data['faculty_name']);
                $department = $this->resolveDepartment($faculty, $data['department_name']);
                $student = $this->resolveStudent($data, $faculty, $department);
                $subject = $this->resolveSubject($data, $department);

                $theoreticalSection = $data['theoretical_section_number'] !== null
                    ? $this->resolveSection($subject, Subject::TYPE_THEORETICAL, $data['theoretical_section_number'])
                    : null;

                $practicalSection = $data['practical_section_number'] !== null
                    ? $this->resolveSection($subject, Subject::TYPE_PRACTICAL, $data['practical_section_number'])
                    : null;

                $this->resolveEnrollment($student, $subject, $theoreticalSection, $practicalSection, $data);
            });

            $this->summary['imported_rows']++;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError($rowNumber, $data, [
                'تعذر استيراد السطر. / Row could not be imported.',
                $exception->getMessage(),
            ]);
        }
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function getSummary(): array
    {
        return $this->summary;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function warmCaches(): void
    {
        Faculty::withTrashed()
            ->get(['id', 'name', 'is_active', 'deleted_at'])
            ->each(fn (Faculty $faculty): Faculty => $this->faculties[$this->normalizeKey($faculty->name)] = $faculty);

        Department::withTrashed()
            ->get(['id', 'faculty_id', 'name', 'is_active', 'deleted_at'])
            ->each(function (Department $department): void {
                $this->departments[$this->departmentKey($department->faculty_id, $department->name)] = $department;
            });

        Student::withTrashed()
            ->get(['id', 'student_number', 'name', 'faculty_id', 'department_id', 'type', 'status', 'is_active', 'deleted_at'])
            ->each(function (Student $student): void {
                if (filled($student->student_number)) {
                    $this->students[$this->normalizeKey($student->student_number)] = $student;
                }
            });

        Subject::withTrashed()
            ->get(['id', 'code', 'name', 'department_id', 'subject_type', 'is_active', 'deleted_at'])
            ->each(function (Subject $subject): void {
                if (filled($subject->code)) {
                    $this->subjects[$this->normalizeKey($subject->code)] = $subject;
                }
            });

        SubjectSection::query()
            ->get(['id', 'subject_id', 'section_type', 'code', 'raw_section_number', 'section_number'])
            ->each(function (SubjectSection $section): void {
                $this->sections[$this->sectionKey($section->subject_id, $section->section_type, $section->code)] = $section;
            });

        Enrollment::query()
            ->get(['id', 'student_id', 'subject_id', 'theoretical_section_id', 'practical_section_id', 'registration_date', 'semester', 'year', 'status'])
            ->each(function (Enrollment $enrollment): void {
                $this->enrollments[$this->enrollmentKey($enrollment->student_id, $enrollment->subject_id)] = $enrollment;
            });
    }

    private function captureHeadings(array $headings): void
    {
        foreach ($headings as $index => $heading) {
            $mapped = $this->columnMap[$this->normalizeHeading($heading)] ?? null;

            if ($mapped) {
                $this->headingIndexes[$mapped] = $index;
            }
        }
    }

    private function mapRow(array $values): array
    {
        $row = [];

        foreach ($this->columnMap as $target) {
            $row[$target] = array_key_exists($target, $this->headingIndexes)
                ? ($values[$this->headingIndexes[$target]] ?? null)
                : null;
        }

        return $row;
    }

    private function normalizeRow(array $row): array
    {
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

        $row['theoretical_section_number'] = $this->normalizeSectionNumber($row['theoretical_section_number'] ?? null);
        $row['practical_section_number'] = $this->normalizeSectionNumber($row['practical_section_number'] ?? null);
        $row['registration_date'] = $this->parseRegistrationDate($row['registration_date'] ?? null);
        $row['course_level'] = $this->normalizeCourseLevel($row['course_level'] ?? null);

        return $row;
    }

    private function validateRow(array $row): array
    {
        $messages = [];

        $required = [
            'student_number' => 'الرقم الجامعي مطلوب / University number is required.',
            'student_name' => 'اسم الطالب مطلوب / Student name is required.',
            'faculty_name' => 'الكلية مطلوبة / College is required.',
            'department_name' => 'الاختصاص مطلوب / Specialization is required.',
            'subject_name' => 'اسم المقرر مطلوب / Subject name is required.',
            'subject_code' => 'رمز المقرر مطلوب / Subject code is required.',
        ];

        foreach ($required as $field => $message) {
            if ($row[$field] === null) {
                $messages[] = $message;
            }
        }

        if ($row['registration_date'] === null) {
            $messages[] = 'تاريخ التسجيل غير صالح / Registration date is invalid.';
        }

        if (($row['course_level'] ?? null) !== null && ((int) $row['course_level'] < 1 || (int) $row['course_level'] > 6)) {
            $messages[] = 'مستوى المقرر غير صالح / Course level is invalid.';
        }

        if ($row['theoretical_section_number'] === null && $row['practical_section_number'] === null) {
            $messages[] = 'يجب إدخال رمز الفئة النظرية أو رمز الفئة العملية على الأقل / At least one theoretical or practical section code is required.';
        }

        return $messages;
    }

    private function resolveFaculty(string $name): Faculty
    {
        $key = $this->normalizeKey($name);
        $faculty = $this->faculties[$key] ?? null;

        if (! $faculty) {
            $faculty = Faculty::query()->create([
                'name' => $name,
                'is_active' => true,
            ]);

            $this->summary['created_colleges']++;
            $this->faculties[$key] = $faculty;

            return $faculty;
        }

        $this->restoreIfTrashed($faculty);
        $this->updateIfChanged($faculty, ['name' => $name, 'is_active' => true]);

        return $faculty;
    }

    private function resolveDepartment(Faculty $faculty, string $name): Department
    {
        $key = $this->departmentKey($faculty->id, $name);
        $department = $this->departments[$key] ?? null;

        if (! $department) {
            $department = Department::query()->create([
                'faculty_id' => $faculty->id,
                'name' => $name,
                'is_active' => true,
            ]);

            $this->summary['created_specializations']++;
            $this->departments[$key] = $department;

            return $department;
        }

        $this->restoreIfTrashed($department);
        $this->updateIfChanged($department, [
            'faculty_id' => $faculty->id,
            'name' => $name,
            'is_active' => true,
        ]);

        return $department;
    }

    private function resolveStudent(array $data, Faculty $faculty, Department $department): Student
    {
        $key = $this->normalizeKey($data['student_number']);
        $student = $this->students[$key] ?? null;

        $attributes = [
            'student_number' => $data['student_number'],
            'name' => $data['student_name'],
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'type' => 'student',
            'status' => 'active',
            'is_active' => true,
        ];

        if (! $student) {
            $student = Student::query()->create($attributes);
            $this->summary['created_students']++;
            $this->students[$key] = $student;

            return $student;
        }

        $this->restoreIfTrashed($student);

        if ($this->updateIfChanged($student, $attributes)) {
            $this->summary['updated_students']++;
        }

        return $student;
    }

    private function resolveSubject(array $data, Department $department): Subject
    {
        $key = $this->normalizeKey($data['subject_code']);
        $subject = $this->subjects[$key] ?? null;

        $defaultType = $data['theoretical_section_number'] !== null
            ? Subject::TYPE_THEORETICAL
            : Subject::TYPE_PRACTICAL;

        $attributes = [
            'code' => $data['subject_code'],
            'name' => $data['subject_name'],
            'department_id' => $department->id,
            'subject_type' => $defaultType,
            'is_active' => true,
        ];

        if (! $subject) {
            $subject = Subject::query()->create($attributes);
            $this->summary['created_subjects']++;
            $this->subjects[$key] = $subject;

            return $subject;
        }

        $this->restoreIfTrashed($subject);

        unset($attributes['subject_type']);

        if ($this->updateIfChanged($subject, $attributes)) {
            $this->summary['updated_subjects']++;
        }

        return $subject;
    }

    private function resolveSection(Subject $subject, string $sectionType, string $rawNumber): SubjectSection
    {
        $code = SubjectSection::normalizeCodeForType($rawNumber, $sectionType);
        $key = $this->sectionKey($subject->id, $sectionType, $code);
        $section = $this->sections[$key] ?? null;

        $attributes = [
            'subject_id' => $subject->id,
            'section_type' => $sectionType,
            'code' => $code,
            'raw_section_number' => SubjectSection::normalizeRawSectionNumber($rawNumber),
            'section_number' => $this->integerSectionNumber($rawNumber),
        ];

        if (! $section) {
            $section = SubjectSection::query()->create($attributes);

            if ($sectionType === Subject::TYPE_PRACTICAL) {
                $this->summary['created_practical_sections']++;
            } else {
                $this->summary['created_theoretical_sections']++;
            }

            $this->sections[$key] = $section;

            return $section;
        }

        $this->updateIfChanged($section, $attributes);

        return $section;
    }

    private function resolveEnrollment(
        Student $student,
        Subject $subject,
        ?SubjectSection $theoreticalSection,
        ?SubjectSection $practicalSection,
        array $data,
    ): Enrollment {
        $key = $this->enrollmentKey($student->id, $subject->id);
        $enrollment = $this->enrollments[$key] ?? null;

        $attributes = [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'theoretical_section_id' => $theoreticalSection?->id,
            'practical_section_id' => $practicalSection?->id,
            'registration_date' => $data['registration_date'],
            'semester' => null,
            'year' => $data['course_level'],
            'status' => Enrollment::STATUS_ENROLLED,
        ];

        if (! $enrollment) {
            $enrollment = Enrollment::query()->create($attributes);
            $this->summary['created_enrollments']++;
            $this->enrollments[$key] = $enrollment;

            return $enrollment;
        }

        if ($this->updateIfChanged($enrollment, $attributes)) {
            $this->summary['updated_enrollments']++;
        }

        return $enrollment;
    }

    private function addError(int $rowNumber, array $data, array $messages): void
    {
        $this->summary['skipped_rows']++;
        $this->summary['failed_rows']++;
        $this->errors[] = [
            'row_number' => $rowNumber,
            'student_university_number' => $data['student_number'] ?? null,
            'subject_code' => $data['subject_code'] ?? null,
            'error_message' => implode(' | ', array_filter($messages)),
        ];
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->normalizeValue($value) !== null) {
                return false;
            }
        }

        return true;
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
        $value = str_replace("\xc2\xa0", ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value);

        return $this->isEmptyValue($value) ? null : $value;
    }

    private function normalizeSectionNumber(mixed $value): ?string
    {
        $value = $this->normalizeValue($value);

        if ($value === null) {
            return null;
        }

        $value = preg_replace('/^[TP]\s*/i', '', Str::upper($value)) ?? $value;

        if (preg_match('/^\d+\.0+$/', $value)) {
            $value = (string) ((int) $value);
        }

        return trim($value) === '' ? null : trim($value);
    }

    private function integerSectionNumber(mixed $value): ?int
    {
        $value = $this->normalizeSectionNumber($value);

        return $value !== null && ctype_digit($value) ? (int) $value : null;
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
            'اولى' => 1,
            'الأولى' => 1,
            'الاولى' => 1,
            'اول' => 1,
            'الأول' => 1,
            'الاول' => 1,
            'ثانية' => 2,
            'الثانية' => 2,
            'ثاني' => 2,
            'الثاني' => 2,
            'ثالثة' => 3,
            'الثالثة' => 3,
            'ثالث' => 3,
            'الثالث' => 3,
            'رابعة' => 4,
            'الرابعة' => 4,
            'رابع' => 4,
            'الرابع' => 4,
            'خامسة' => 5,
            'الخامسة' => 5,
            'خامس' => 5,
            'الخامس' => 5,
            'سادسة' => 6,
            'السادسة' => 6,
            'سادس' => 6,
            'السادس' => 6,
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

        return $value === ''
            || Str::lower($value) === 'nan'
            || $value === '-';
    }

    private function normalizeHeading(mixed $value): string
    {
        return $this->normalizeValue($value) ?? '';
    }

    private function normalizeKey(mixed $value): string
    {
        return Str::lower($this->normalizeValue($value) ?? '');
    }

    private function departmentKey(int|string|null $facultyId, mixed $name): string
    {
        return ((string) $facultyId).'|'.$this->normalizeKey($name);
    }

    private function sectionKey(int|string|null $subjectId, mixed $sectionType, mixed $code): string
    {
        return ((string) $subjectId).'|'.SubjectSection::normalizeSectionType($sectionType).'|'.$this->normalizeKey($code);
    }

    private function enrollmentKey(int|string|null $studentId, int|string|null $subjectId): string
    {
        return ((string) $studentId).'|'.((string) $subjectId);
    }

    private function restoreIfTrashed(Model $model): void
    {
        if (method_exists($model, 'trashed') && $model->trashed()) {
            $model->restore();
        }
    }

    private function updateIfChanged(Model $model, array $attributes): bool
    {
        $hasChanges = false;

        foreach ($attributes as $key => $value) {
            if (! $this->valuesAreEqual($model->getAttribute($key), $value)) {
                $hasChanges = true;

                break;
            }
        }

        if (! $hasChanges) {
            return false;
        }

        $model->fill($attributes);

        if (! $model->isDirty()) {
            return false;
        }

        $model->save();

        return true;
    }

    private function valuesAreEqual(mixed $current, mixed $incoming): bool
    {
        if ($current instanceof DateTimeInterface) {
            $current = $current->format('Y-m-d');
        }

        if ($incoming instanceof DateTimeInterface) {
            $incoming = $incoming->format('Y-m-d');
        }

        if (is_bool($current) || is_bool($incoming)) {
            return (bool) $current === (bool) $incoming;
        }

        if ($current === null || $incoming === null) {
            return $current === $incoming;
        }

        return (string) $current === (string) $incoming;
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
