<?php

namespace App\Imports;

use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SubjectStudentsImport implements ToCollection, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    private int $importedCount = 0;

    public function __construct(
        private readonly int $subjectId,
        private readonly ?string $semester = null,
        private readonly ?int $year = null,
    ) {}

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $subject = Subject::query()
            ->select(['id', 'level'])
            ->findOrFail($this->subjectId);

        $normalizedRows = $rows
            ->map(fn (array $row): array => $this->normalizeRow($row))
            ->values();

        $students = Student::query()
            ->select(['id', 'student_number'])
            ->whereIn('student_number', $normalizedRows->pluck('student_number')->filter()->unique())
            ->get()
            ->keyBy('student_number');

        $upsertRows = [];

        foreach ($normalizedRows as $index => $row) {
            $student = $students->get($row['student_number']);

            if (! $student) {
                $rowNumber = $index + 2;

                throw new \RuntimeException(
                    __('student.not_found') . " ({$row['student_number']}) " . __('subjects.not_found_in_row', ['row' => $rowNumber]),
                );
            }

            $upsertRows[] = [
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'semester' => Subject::normalizeSemester($row['semester'] ?? $this->semester),
                'year' => $row['year'] ?? $this->year ?? $subject->level,
                'status' => $row['status'] ?? Enrollment::STATUS_ENROLLED,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Enrollment::query()->upsert(
            $upsertRows,
            ['student_id', 'subject_id'],
            ['semester', 'year', 'status', 'updated_at'],
        );

        $this->importedCount += count($upsertRows);
    }

    public function rules(): array
    {
        return [
            'student_number' => ['required', 'max:20'],
            'semester' => ['nullable', Rule::in(array_keys(Subject::semesterOptions()))],
            'year' => ['nullable', 'integer', 'min:1', 'max:6'],
            'status' => ['nullable', Rule::in(array_keys(Enrollment::statusOptions()))],
        ];
    }

    public function prepareForValidation($data, $index)
    {
        return $this->normalizeRow($data);
    }

    public function customValidationMessages(): array
    {
        return [
            'student_number.required' => __('validation.student_number_required'),
            'semester.in' => __('subjects.semester_invalid'),
            'year.min' => 'السنة الدراسية يجب أن تكون رقمًا صحيحًا يبدأ من 1.',
            'year.max' => 'السنة الدراسية أكبر من المسموح.',
            'status.in' => __('validation.in', ['attribute' => __('enrollments.status')]),
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'student_number' => __('student.student_number'),
            'semester' => __('enrollments.semester'),
            'year' => __('enrollments.year'),
            'status' => __('enrollments.status'),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        if (isset($row['student_number']) && filled($row['student_number'])) {
            $row['student_number'] = trim((string) $row['student_number']);
        }

        if (isset($row['semester']) && filled($row['semester'])) {
            $row['semester'] = Subject::normalizeSemester($row['semester']);
        }

        if (isset($row['year']) && filled($row['year'])) {
            $row['year'] = (int) $row['year'];
        }

        if (isset($row['status']) && filled($row['status'])) {
            $row['status'] = trim((string) $row['status']);
        }

        return $row;
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }
}
