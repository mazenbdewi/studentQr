<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Row;

class SubjectsImport implements OnEachRow, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    private int $importedCount = 0;

    private $lecturers;
    private $departments;

    public function __construct()
    {

        $this->lecturers = User::whereHas('roles', fn($q) => $q->where('name', 'course_lecturer'))
            ->pluck('id', 'name')
            ->toArray();


        $this->departments = Department::query()
            ->get(['id', 'name', 'code'])
            ->flatMap(function (Department $department): array {
                $map = [];

                if (filled($department->name)) {
                    $map[trim((string) $department->name)] = $department->id;
                }

                if (filled($department->code)) {
                    $map[trim((string) $department->code)] = $department->id;
                }

                return $map;
            })
            ->toArray();
    }
    public function prepareForValidation($data, $index)
    {
        $data['code'] = $data['code'] ?? $data['subject_code'] ?? null;
        $data['name'] = $data['name'] ?? $data['subject_name'] ?? null;
        $data['department_name'] = $data['department_name'] ?? $data['department'] ?? null;
        $data['faculty_name'] = $data['faculty_name'] ?? $data['college'] ?? null;

        if (isset($data['subject_type'])) {
            $data['subject_type'] = Subject::normalizeSubjectType($data['subject_type']);
        }

        if (isset($data['sections'])) {
            $data['sections'] = trim((string) $data['sections']);
        }

        if (! empty($data['department_name'])) {
            $departmentName = trim((string) $data['department_name']);

            if (! isset($this->departments[$departmentName])) {
                $data['department_id'] = null;
            } else {
                $data['department_id'] = $this->departments[$departmentName];
            }
        }

        if (! empty($data['lecturer_name'])) {
            $lecturerName = trim((string) $data['lecturer_name']);

            if (! isset($this->lecturers[$lecturerName])) {
                $data['lecturer_id'] = null;
            } else {
                $data['lecturer_id'] = $this->lecturers[$lecturerName];
            }
        }

        return $data;
    }

    public function onRow(Row $row): void
    {
        $data = $row->toArray();

        DB::transaction(function () use ($data): void {
            $subject = Subject::query()->create([
                'code' => $data['code'] ?? null,
                'name' => $data['name'] ?? null,
                'subject_type' => $data['subject_type'] ?? null,
                'lecturer_id' => $data['lecturer_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'level' => $data['year'] ?? null,
                'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ]);

            foreach ($this->parseSections($data['sections'] ?? null) as $sectionCode) {
                $subject->sections()->create([
                    'section_type' => \App\Models\SubjectSection::inferSectionTypeFromCode($sectionCode) ?? $subject->subject_type,
                    'code' => $sectionCode,
                ]);
            }
        });

        $this->importedCount++;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|unique:subjects,code',
            'name' => 'required|string|max:255',
            'subject_type' => ['required', Rule::in(array_keys(Subject::subjectTypeOptions()))],
            'year' => ['nullable', 'integer', 'min:1', 'max:6'],
            'sections' => ['nullable', 'string'],
        ];
    }
    public function customValidationMessages(): array
    {
        return [
            'code.required' => __('validation.code_required'),
            'code.unique'   => __('validation.code_unique'),
            'name.required' => __('validation.name_required'),
            'name.max'      => __('validation.name_max'),
            'subject_type.required' => __('subjects.subject_type_required'),
            'subject_type.in' => __('subjects.subject_type_invalid'),
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'code' => __('subjects.code'),
            'name' => __('subjects.name'),
            'subject_type' => __('subjects.subject_type'),
            'lecturer_id' => __('subjects.lecturer'),
            'department_id' => __('subjects.department_id'),
            'year' => __('subjects.academic_year'),
            'sections' => __('subjects.sections'),
        ];
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    /**
     * @return array<int, string>
     */
    private function parseSections(mixed $sections): array
    {
        if (blank($sections)) {
            return [];
        }

        return collect(preg_split('/[,;\n]+/', (string) $sections) ?: [])
            ->map(fn (mixed $section): string => SubjectSection::normalizeCode($section))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
