<?php

namespace App\Imports;

use App\Models\AcademicTerm;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Support\AcademicTermNormalizer;
use App\Support\ManaraEnrollmentRowNormalizer;
use DateTimeInterface;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class ManaraStudentEnrollmentsPreviewImport extends StringValueBinder implements OnEachRow, WithChunkReading, SkipsEmptyRows, WithCustomValueBinder
{
    private ManaraEnrollmentRowNormalizer $rowNormalizer;

    /** @var array<string, array<string, mixed>> */
    private array $terms = [];

    /** @var array<string, array<string, mixed>> */
    private array $students = [];

    /** @var array<string, array<string, mixed>> */
    private array $subjects = [];

    /** @var array<string, array<string, mixed>> */
    private array $sections = [];

    /** @var array<string, array<string, mixed>> */
    private array $enrollments = [];

    /** @var array<string, true> */
    private array $updatedStudents = [];

    /** @var array<string, true> */
    private array $updatedSubjects = [];

    /** @var array<string, true> */
    private array $updatedSections = [];

    /** @var array<string, true> */
    private array $updatedEnrollments = [];

    private array $summary = [
        'total_rows' => 0,
        'valid_rows' => 0,
        'invalid_rows' => 0,
        'new_terms' => 0,
        'existing_terms' => 0,
        'new_students' => 0,
        'updated_students' => 0,
        'new_subjects' => 0,
        'updated_subjects' => 0,
        'new_sections' => 0,
        'updated_sections' => 0,
        'new_enrollments' => 0,
        'updated_enrollments' => 0,
        'zero_section_values' => 0,
        'zero_sections_to_create' => 0,
    ];

    private array $errors = [];

    private array $blockingErrors = [];

    public function __construct()
    {
        $academicTermNormalizer = new AcademicTermNormalizer();
        $this->rowNormalizer = new ManaraEnrollmentRowNormalizer($academicTermNormalizer);
        $this->warmSnapshots();
    }

    public function onRow(Row $row): void
    {
        $rowNumber = $row->getIndex();
        $values = array_values($row->toArray());

        if ($rowNumber === 1) {
            $missingHeadings = $this->rowNormalizer->captureHeadings($values);

            if ($missingHeadings !== []) {
                $this->blockingErrors[] = 'العمود الفصل الدراسي مفقود / The الفصل الدراسي heading is missing.';
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
            $this->summary['invalid_rows']++;
            $this->addError($rowNumber, $data, $messages);

            return;
        }

        $this->summary['valid_rows']++;
        $this->analyzeValidRow($data);
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function getPreview(): array
    {
        return [
            ...$this->summary,
            'can_apply' => $this->blockingErrors === []
                && $this->summary['valid_rows'] > 0
                && $this->summary['zero_sections_to_create'] === 0,
            'blocking_errors' => $this->blockingErrors,
            'terms' => collect($this->terms)
                ->filter(fn (array $term): bool => (int) ($term['row_count'] ?? 0) > 0)
                ->map(fn (array $term): array => [
                    'display_name' => $term['display_name'],
                    'canonical_name' => $term['canonical_name'],
                    'row_count' => $term['row_count'],
                    'exists' => $term['_persisted'],
                ])
                ->sortBy('display_name')
                ->values()
                ->all(),
        ];
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function warmSnapshots(): void
    {
        AcademicTerm::query()
            ->get(['id', 'display_name', 'canonical_name'])
            ->each(function (AcademicTerm $term): void {
                $this->terms[$term->canonical_name] = [
                    'id' => $term->id,
                    'display_name' => $term->display_name,
                    'canonical_name' => $term->canonical_name,
                    'row_count' => 0,
                    '_persisted' => true,
                ];
            });

        Student::withTrashed()
            ->with(['faculty:id,name', 'department:id,name'])
            ->get(['id', 'student_number', 'name', 'faculty_id', 'department_id', 'type', 'status', 'is_active', 'deleted_at'])
            ->each(function (Student $student): void {
                if (filled($student->student_number)) {
                    $this->students[$this->rowNormalizer->normalizeKey($student->student_number)] = [
                        'name' => $student->name,
                        'faculty_name' => $student->faculty?->name,
                        'department_name' => $student->department?->name,
                        'type' => $student->type,
                        'status' => $student->status,
                        'is_active' => (bool) $student->is_active,
                        'deleted' => $student->trashed(),
                        '_persisted' => true,
                    ];
                }
            });

        Subject::withTrashed()
            ->with('department:id,name')
            ->get(['id', 'code', 'name', 'department_id', 'subject_type', 'is_active', 'deleted_at'])
            ->each(function (Subject $subject): void {
                if (filled($subject->code)) {
                    $this->subjects[$this->rowNormalizer->normalizeKey($subject->code)] = [
                        'name' => $subject->name,
                        'department_name' => $subject->department?->name,
                        'is_active' => (bool) $subject->is_active,
                        'deleted' => $subject->trashed(),
                        '_persisted' => true,
                    ];
                }
            });

        SubjectSection::query()
            ->with(['academicTerm:id,canonical_name', 'subject:id,code'])
            ->get(['id', 'academic_term_id', 'subject_id', 'section_type', 'code', 'raw_section_number', 'section_number'])
            ->each(function (SubjectSection $section): void {
                $term = $section->academicTerm?->canonical_name ?? '';
                $subject = $this->rowNormalizer->normalizeKey($section->subject?->code);
                $key = $this->sectionKey($term, $subject, $section->code);
                $this->sections[$key] = [
                    'section_type' => $section->section_type,
                    'code' => $section->code,
                    'raw_section_number' => $section->raw_section_number,
                    'section_number' => $section->section_number,
                    '_persisted' => true,
                ];
            });

        Enrollment::query()
            ->with([
                'academicTerm:id,canonical_name',
                'student:id,student_number',
                'subject:id,code',
                'theoreticalSection:id,code',
                'practicalSection:id,code',
            ])
            ->get([
                'id', 'academic_term_id', 'student_id', 'subject_id', 'theoretical_section_id',
                'practical_section_id', 'registration_date', 'year', 'status',
            ])
            ->each(function (Enrollment $enrollment): void {
                $term = $enrollment->academicTerm?->canonical_name ?? '';
                $student = $this->rowNormalizer->normalizeKey($enrollment->student?->student_number);
                $subject = $this->rowNormalizer->normalizeKey($enrollment->subject?->code);
                $key = $this->enrollmentKey($term, $student, $subject);
                $this->enrollments[$key] = [
                    'theoretical_section_code' => $enrollment->theoreticalSection?->code,
                    'practical_section_code' => $enrollment->practicalSection?->code,
                    'registration_date' => $enrollment->registration_date?->toDateString(),
                    'year' => $enrollment->year,
                    'status' => $enrollment->status,
                    '_persisted' => true,
                ];
            });
    }

    private function analyzeValidRow(array $data): void
    {
        $termKey = $data['academic_term_canonical_name'];
        $term = $this->terms[$termKey] ?? null;

        if (! $term) {
            $term = [
                'id' => null,
                'display_name' => $data['academic_term_display_name'],
                'canonical_name' => $termKey,
                'row_count' => 0,
                '_persisted' => false,
            ];
            $this->summary['new_terms']++;
        } elseif ((int) $term['row_count'] === 0 && $term['_persisted']) {
            $this->summary['existing_terms']++;
        }

        $term['row_count']++;
        $this->terms[$termKey] = $term;

        $studentKey = $this->rowNormalizer->normalizeKey($data['student_number']);
        $this->analyzeEntity(
            $this->students,
            $studentKey,
            [
                'name' => $data['student_name'],
                'faculty_name' => $data['faculty_name'],
                'department_name' => $data['department_name'],
                'type' => 'student',
                'status' => 'active',
                'is_active' => true,
                'deleted' => false,
            ],
            'new_students',
            'updated_students',
            $this->updatedStudents,
        );

        $subjectKey = $this->rowNormalizer->normalizeKey($data['subject_code']);
        $this->analyzeEntity(
            $this->subjects,
            $subjectKey,
            [
                'name' => $data['subject_name'],
                'department_name' => $data['department_name'],
                'is_active' => true,
                'deleted' => false,
            ],
            'new_subjects',
            'updated_subjects',
            $this->updatedSubjects,
        );

        $theoreticalCode = $this->analyzeSection(
            $termKey,
            $subjectKey,
            Subject::TYPE_THEORETICAL,
            $data['theoretical_section_number'],
        );
        $practicalCode = $this->analyzeSection(
            $termKey,
            $subjectKey,
            Subject::TYPE_PRACTICAL,
            $data['practical_section_number'],
        );

        $enrollmentKey = $this->enrollmentKey($termKey, $studentKey, $subjectKey);
        $currentEnrollment = $this->enrollments[$enrollmentKey] ?? null;
        $incomingEnrollment = [
            'theoretical_section_code' => $theoreticalCode ?? ($currentEnrollment['theoretical_section_code'] ?? null),
            'practical_section_code' => $practicalCode ?? ($currentEnrollment['practical_section_code'] ?? null),
            'registration_date' => $data['registration_date'],
            'year' => $data['course_level'],
            'status' => Enrollment::STATUS_ENROLLED,
        ];

        $this->analyzeEntity(
            $this->enrollments,
            $enrollmentKey,
            $incomingEnrollment,
            'new_enrollments',
            'updated_enrollments',
            $this->updatedEnrollments,
        );
    }

    private function analyzeSection(string $termKey, string $subjectKey, string $sectionType, ?string $rawNumber): ?string
    {
        if ($rawNumber === null) {
            return null;
        }

        $code = SubjectSection::normalizeCodeForType($rawNumber, $sectionType);

        if (in_array($code, ['T0', 'P0'], true)) {
            $this->summary['zero_sections_to_create']++;

            return null;
        }

        $key = $this->sectionKey($termKey, $subjectKey, $code);
        $this->analyzeEntity(
            $this->sections,
            $key,
            [
                'section_type' => $sectionType,
                'code' => $code,
                'raw_section_number' => SubjectSection::normalizeRawSectionNumber($rawNumber),
                'section_number' => $this->rowNormalizer->integerSectionNumber($rawNumber),
            ],
            'new_sections',
            'updated_sections',
            $this->updatedSections,
        );

        return $code;
    }

    private function analyzeEntity(
        array &$entities,
        string $key,
        array $incoming,
        string $newCounter,
        string $updatedCounter,
        array &$updatedKeys,
    ): void {
        $current = $entities[$key] ?? null;

        if (! $current) {
            $entities[$key] = [...$incoming, '_persisted' => false];
            $this->summary[$newCounter]++;

            return;
        }

        $persisted = (bool) ($current['_persisted'] ?? false);
        $comparableCurrent = $current;
        unset($comparableCurrent['_persisted']);

        if ($persisted && ! $this->valuesAreEqual($comparableCurrent, $incoming) && ! isset($updatedKeys[$key])) {
            $updatedKeys[$key] = true;
            $this->summary[$updatedCounter]++;
        }

        $entities[$key] = [...$incoming, '_persisted' => $persisted];
    }

    private function valuesAreEqual(array $current, array $incoming): bool
    {
        foreach ($incoming as $key => $value) {
            $currentValue = $current[$key] ?? null;

            if ($currentValue instanceof DateTimeInterface) {
                $currentValue = $currentValue->format('Y-m-d');
            }

            if ($value instanceof DateTimeInterface) {
                $value = $value->format('Y-m-d');
            }

            if (is_bool($currentValue) || is_bool($value)) {
                if ((bool) $currentValue !== (bool) $value) {
                    return false;
                }

                continue;
            }

            if ($currentValue === null || $value === null) {
                if ($currentValue !== $value) {
                    return false;
                }

                continue;
            }

            if ((string) $currentValue !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    private function addError(int $rowNumber, array $data, array $messages): void
    {
        $this->errors[] = [
            'row_number' => $rowNumber,
            'student_university_number' => $data['student_number'] ?? null,
            'subject_code' => $data['subject_code'] ?? null,
            'academic_term' => $data['academic_term_display_name'] ?? null,
            'theoretical_section' => $data['theoretical_section_number'] ?? null,
            'practical_section' => $data['practical_section_number'] ?? null,
            'error_message' => implode(' | ', array_filter($messages)),
        ];
    }

    private function sectionKey(string $term, string $subject, mixed $code): string
    {
        return $term.'|'.$subject.'|'.$this->rowNormalizer->normalizeKey($code);
    }

    private function enrollmentKey(string $term, string $student, string $subject): string
    {
        return $term.'|'.$student.'|'.$subject;
    }
}
