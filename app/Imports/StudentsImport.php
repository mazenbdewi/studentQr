<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Row;

class StudentsImport implements OnEachRow, WithHeadingRow, WithValidation
{
    private int $importedCount = 0;

    private array $faculties = [];
    private array $departmentsByName = [];
    private array $departmentsByFacultyAndName = [];
    private array $subjectsByCode = [];
    private array $subjectsByDepartmentAndCode = [];

    public function __construct()
    {
        $this->faculties = Faculty::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Faculty $faculty): array => [
                $this->normalizeLookupValue($faculty->name) => $faculty->id,
            ])
            ->toArray();

        Department::query()
            ->get(['id', 'name', 'faculty_id'])
            ->each(function (Department $department): void {
                $normalizedDepartmentName = $this->normalizeLookupValue($department->name);

                $this->departmentsByName[$normalizedDepartmentName] ??= $department->id;
                $this->departmentsByFacultyAndName[$department->faculty_id.'|'.$normalizedDepartmentName] = $department->id;
            });

        Subject::query()
            ->withoutTrashed()
            ->get(['id', 'code', 'name', 'department_id', 'level'])
            ->each(function (Subject $subject): void {
                $normalizedCode = $this->normalizeLookupValue($subject->code);

                if ($normalizedCode !== null) {
                    $this->subjectsByCode[$normalizedCode] ??= $subject;
                    $this->subjectsByDepartmentAndCode[$subject->department_id.'|'.$normalizedCode] = $subject;
                }
            });
    }

    public function prepareForValidation($data, $index)
    {
        $facultyName = $this->normalizeLookupValue($data['faculty_name'] ?? null);
        $departmentName = $this->normalizeLookupValue($data['department_name'] ?? null);

        $data['faculty_name'] = $facultyName;
        $data['department_name'] = $departmentName;
        $data['faculty_id'] = $facultyName !== null ? ($this->faculties[$facultyName] ?? null) : null;

        $data['department_id'] = null;

        if ($departmentName !== null) {
            if ($data['faculty_id'] !== null) {
                $data['department_id'] = $this->departmentsByFacultyAndName[$data['faculty_id'].'|'.$departmentName] ?? null;
            }

            $data['department_id'] ??= $this->departmentsByName[$departmentName] ?? null;
        }

        [$subjectIds, $subjectErrors] = $this->resolveSubjectIds($data);

        $data['subject_ids'] = $subjectIds;
        $data['subject_errors'] = $subjectErrors;

        return $data;
    }

    public function onRow(Row $row): void
    {
        $row = $row->toArray();

        $student = Student::create([
            'national_number' => $row['national_number'] ?? null,
            'student_number' => $row['student_number'] ?? null,
            'name' => $row['name'] ?? null,
            'faculty_id' => $row['faculty_id'],
            'department_id' => $row['department_id'],
            'year' => $row['year'] ?? null,
            'type' => 'student',
            'phone' => $row['phone'] ?? null,
            'status' => $row['status'] ?? 'pending',
            'avatar' => null,
            'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ]);

        foreach ($this->subjectsForIds((array) ($row['subject_ids'] ?? [])) as $subject) {
            Enrollment::query()->updateOrCreate(
                [
                    'student_id' => $student->id,
                    'subject_id' => $subject->id,
                ],
                [
                    'semester' => null,
                    'year' => $subject->level ?: $student->year,
                    'status' => Enrollment::STATUS_ENROLLED,
                ],
            );
        }

        $this->importedCount++;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($validator->getData() as $rowIndex => $row) {
                foreach ((array) ($row['subject_errors'] ?? []) as $message) {
                    $validator->errors()->add($rowIndex.'.subject_codes', $message);
                }
            }
        });
    }

    public function uniqueBy()
    {
        return 'student_number';
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'student_number' => 'required|unique:students,student_number',
            'national_number' => 'nullable|unique:students,national_number',
            'year' => 'nullable|integer|min:1|max:6',
            'faculty_id' => 'required|exists:faculties,id',
            'department_id' => 'required|exists:departments,id',
            'subject_codes' => 'nullable',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => __('validation.name_required'),
            'name.max' => __('validation.name_max'),
            'student_number.required' => __('validation.student_number_required'),
            'student_number.unique' => __('validation.student_number_unique'),
            'national_number.unique' => __('validation.national_number_unique'),
            'year.min' => 'السنة الدراسية يجب أن تكون رقمًا صحيحًا يبدأ من 1.',
            'year.max' => 'السنة الدراسية أكبر من المسموح.',
            'faculty_id.required' => __('validation.faculty_required'),
            'faculty_id.exists' => __('validation.faculty_not_found_in_row', ['row' => ':row']),
            'department_id.required' => __('validation.department_required'),
            'department_id.exists' => __('validation.department_not_found_in_row', ['row' => ':row']),
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'name' => __('validation.name'),
            'student_number' => __('validation.student_number'),
            'national_number' => __('validation.national_number'),
            'year' => __('student.year'),
            'faculty_id' => __('validation.faculty'),
            'department_id' => __('validation.department'),
            'subject_codes' => __('student.subject_codes'),
        ];
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    private function resolveSubjectIds(array $data): array
    {
        $departmentId = $data['department_id'] ?? null;
        $subjectIds = [];
        $errors = [];

        foreach ($this->parseList($data['subject_codes'] ?? null) as $code) {
            $subject = null;

            if ($departmentId) {
                $subject = $this->subjectsByDepartmentAndCode[$departmentId.'|'.$code] ?? null;
            }

            $subject ??= $this->subjectsByCode[$code] ?? null;

            if (! $subject) {
                $errors[] = __('student.subject_code_not_found', ['code' => $code]);

                continue;
            }

            if ($departmentId && (int) $subject->department_id !== (int) $departmentId) {
                $errors[] = __('student.subject_not_in_department', ['subject' => $subject->code ?: $subject->name]);

                continue;
            }

            $subjectIds[] = $subject->id;
        }

        return [array_values(array_unique($subjectIds)), $errors];
    }

    /**
     * @return array<int, Subject>
     */
    private function subjectsForIds(array $subjectIds): array
    {
        return Subject::query()
            ->whereKey($subjectIds)
            ->get(['id', 'level'])
            ->all();
    }

    private function parseList(mixed $value): array
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return [];
        }

        return collect(preg_split('/[,،;؛|\\n]+/u', (string) $value) ?: [])
            ->map(fn (mixed $item): ?string => $this->normalizeLookupValue($item))
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeLookupValue(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = str_replace("\xc2\xa0", ' ', (string) $value);
        $value = preg_replace('/\s+/u', ' ', $value ?? '');
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return Str::lower($value);
    }
}
