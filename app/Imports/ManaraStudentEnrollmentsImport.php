<?php

namespace App\Imports;

use App\Models\AcademicTerm;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Support\AcademicTermNormalizer;
use App\Support\ManaraEnrollmentRowNormalizer;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use RuntimeException;
use Throwable;

class ManaraStudentEnrollmentsImport extends StringValueBinder implements OnEachRow, SkipsEmptyRows, WithChunkReading, WithCustomValueBinder
{
    private ManaraEnrollmentRowNormalizer $rowNormalizer;

    /** @var array<string, AcademicTerm> */
    private array $academicTerms = [];

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

    private array $summary = [
        'total_rows' => 0,
        'imported_rows' => 0,
        'skipped_rows' => 0,
        'created_terms' => 0,
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
        'zero_section_values' => 0,
        'zero_sections_created' => 0,
    ];

    private array $errors = [];

    /** @var array<int, int> */
    private array $importedAcademicTermRowCounts = [];

    public function __construct()
    {
        $academicTermNormalizer = new AcademicTermNormalizer;
        $this->rowNormalizer = new ManaraEnrollmentRowNormalizer($academicTermNormalizer);
        $this->warmCaches();
    }

    public function onRow(Row $row): void
    {
        $rowNumber = $row->getIndex();
        $values = array_values($row->toArray());

        if ($rowNumber === 1) {
            $missingHeadings = $this->rowNormalizer->captureHeadings($values);

            if ($missingHeadings !== []) {
                $formattedHeadings = implode('، ', array_map(
                    fn (string $heading): string => '«'.$heading.'»',
                    $missingHeadings,
                ));

                throw new RuntimeException('أعمدة مطلوبة مفقودة من ملف الاستيراد: '.$formattedHeadings);
            }

            return;
        }

        $data = $this->rowNormalizer->mapRow($values);

        if ($this->rowNormalizer->rowIsEmpty($data)) {
            return;
        }

        $this->summary['total_rows']++;
        $data = $this->rowNormalizer->normalizeRow($data);
        $this->summary['zero_section_values'] += (int) ($data['_zero_section_values'] ?? 0);
        $messages = $this->rowNormalizer->validateRow($data);

        if ($messages !== []) {
            $this->addError($rowNumber, $data, $messages);

            return;
        }

        try {
            $academicTerm = DB::transaction(function () use ($data): AcademicTerm {
                $academicTerm = $this->resolveAcademicTerm($data);
                $faculty = $this->resolveFaculty($data['faculty_name']);
                $department = $this->resolveDepartment($faculty, $data['department_name']);
                $student = $this->resolveStudent($data, $faculty, $department);
                $subject = $this->resolveSubject($data, $department);

                $theoreticalSection = $data['theoretical_section_number'] !== null
                    ? $this->resolveSection(
                        $academicTerm,
                        $subject,
                        Subject::TYPE_THEORETICAL,
                        $data['theoretical_section_number'],
                    )
                    : null;

                $practicalSection = $data['practical_section_number'] !== null
                    ? $this->resolveSection(
                        $academicTerm,
                        $subject,
                        Subject::TYPE_PRACTICAL,
                        $data['practical_section_number'],
                    )
                    : null;

                $this->resolveEnrollment(
                    $academicTerm,
                    $student,
                    $subject,
                    $theoreticalSection,
                    $practicalSection,
                    $data,
                );

                return $academicTerm;
            });

            $this->summary['imported_rows']++;
            $this->importedAcademicTermRowCounts[$academicTerm->id] =
                ($this->importedAcademicTermRowCounts[$academicTerm->id] ?? 0) + 1;
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

    /** @return array<int, int> */
    public function getImportedAcademicTermRowCounts(): array
    {
        return $this->importedAcademicTermRowCounts;
    }

    private function warmCaches(): void
    {
        AcademicTerm::query()
            ->get(['id', 'display_name', 'canonical_name'])
            ->each(function (AcademicTerm $term): void {
                $this->academicTerms[$term->canonical_name] = $term;
            });

        Faculty::withTrashed()
            ->get(['id', 'name', 'is_active', 'deleted_at'])
            ->each(function (Faculty $faculty): void {
                $this->faculties[$this->rowNormalizer->normalizeKey($faculty->name)] = $faculty;
            });

        Department::withTrashed()
            ->get(['id', 'faculty_id', 'name', 'is_active', 'deleted_at'])
            ->each(function (Department $department): void {
                $this->departments[$this->departmentKey($department->faculty_id, $department->name)] = $department;
            });

        Student::withTrashed()
            ->get(['id', 'student_number', 'name', 'faculty_id', 'department_id', 'type', 'status', 'is_active', 'deleted_at'])
            ->each(function (Student $student): void {
                if (filled($student->student_number)) {
                    $this->students[$this->rowNormalizer->normalizeKey($student->student_number)] = $student;
                }
            });

        Subject::withTrashed()
            ->get(['id', 'code', 'name', 'department_id', 'subject_type', 'is_active', 'deleted_at'])
            ->each(function (Subject $subject): void {
                if (filled($subject->code)) {
                    $this->subjects[$this->rowNormalizer->normalizeKey($subject->code)] = $subject;
                }
            });

        SubjectSection::query()
            ->get(['id', 'academic_term_id', 'subject_id', 'section_type', 'code', 'raw_section_number', 'section_number'])
            ->each(function (SubjectSection $section): void {
                $this->sections[$this->sectionKey($section->academic_term_id, $section->subject_id, $section->code)] = $section;
            });

        Enrollment::query()
            ->get([
                'id', 'academic_term_id', 'student_id', 'subject_id', 'theoretical_section_id',
                'practical_section_id', 'registration_date', 'semester', 'year', 'status',
            ])
            ->each(function (Enrollment $enrollment): void {
                $this->enrollments[$this->enrollmentKey(
                    $enrollment->academic_term_id,
                    $enrollment->student_id,
                    $enrollment->subject_id,
                )] = $enrollment;
            });
    }

    private function resolveAcademicTerm(array $data): AcademicTerm
    {
        $canonicalName = $data['academic_term_canonical_name'];
        $term = $this->academicTerms[$canonicalName] ?? null;

        if ($term) {
            return $term;
        }

        $term = AcademicTerm::query()->firstOrCreate(
            ['canonical_name' => $canonicalName],
            ['display_name' => $data['academic_term_display_name']],
        );

        if ($term->wasRecentlyCreated) {
            $this->summary['created_terms']++;
        }

        $this->academicTerms[$canonicalName] = $term;

        return $term;
    }

    private function resolveFaculty(string $name): Faculty
    {
        $key = $this->rowNormalizer->normalizeKey($name);
        $faculty = $this->faculties[$key] ?? null;

        if (! $faculty) {
            $faculty = Faculty::query()->create(['name' => $name, 'is_active' => true]);
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
        $key = $this->rowNormalizer->normalizeKey($data['student_number']);
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
        $key = $this->rowNormalizer->normalizeKey($data['subject_code']);
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

    private function resolveSection(
        AcademicTerm $academicTerm,
        Subject $subject,
        string $sectionType,
        string $rawNumber,
    ): SubjectSection {
        $code = SubjectSection::normalizeCodeForType($rawNumber, $sectionType);

        if (in_array($code, ['T0', 'P0'], true)) {
            throw new RuntimeException('لا يمكن إنشاء T0 أو P0 / T0 and P0 cannot be created.');
        }

        $key = $this->sectionKey($academicTerm->id, $subject->id, $code);
        $section = $this->sections[$key] ?? null;
        $attributes = [
            'academic_term_id' => $academicTerm->id,
            'subject_id' => $subject->id,
            'section_type' => $sectionType,
            'code' => $code,
            'raw_section_number' => SubjectSection::normalizeRawSectionNumber($rawNumber),
            'section_number' => $this->rowNormalizer->integerSectionNumber($rawNumber),
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
        AcademicTerm $academicTerm,
        Student $student,
        Subject $subject,
        ?SubjectSection $theoreticalSection,
        ?SubjectSection $practicalSection,
        array $data,
    ): Enrollment {
        $key = $this->enrollmentKey($academicTerm->id, $student->id, $subject->id);
        $enrollment = $this->enrollments[$key] ?? null;
        $attributes = [
            'academic_term_id' => $academicTerm->id,
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'theoretical_section_id' => $theoreticalSection?->id ?? $enrollment?->theoretical_section_id,
            'practical_section_id' => $practicalSection?->id ?? $enrollment?->practical_section_id,
            'registration_date' => $data['registration_date'],
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
            'academic_term' => $data['academic_term_display_name'] ?? null,
            'theoretical_section' => $data['theoretical_section_source_value'] ?? null,
            'practical_section' => $data['practical_section_source_value'] ?? null,
            'error_message' => implode(' | ', array_filter($messages)),
        ];
    }

    private function departmentKey(int|string|null $facultyId, mixed $name): string
    {
        return ((string) $facultyId).'|'.$this->rowNormalizer->normalizeKey($name);
    }

    private function sectionKey(int|string|null $termId, int|string|null $subjectId, mixed $code): string
    {
        return ((string) $termId).'|'.((string) $subjectId).'|'.$this->rowNormalizer->normalizeKey($code);
    }

    private function enrollmentKey(
        int|string|null $termId,
        int|string|null $studentId,
        int|string|null $subjectId,
    ): string {
        return ((string) $termId).'|'.((string) $studentId).'|'.((string) $subjectId);
    }

    private function restoreIfTrashed(Model $model): void
    {
        if (method_exists($model, 'trashed') && $model->trashed()) {
            $model->restore();
        }
    }

    private function updateIfChanged(Model $model, array $attributes): bool
    {
        foreach ($attributes as $key => $value) {
            if (! $this->valuesAreEqual($model->getAttribute($key), $value)) {
                $model->fill($attributes);

                if ($model->isDirty()) {
                    $model->save();

                    return true;
                }

                return false;
            }
        }

        return false;
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
}
