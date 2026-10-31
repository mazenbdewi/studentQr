<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Services\ActivityLogger;
use App\Support\AcademicTermContext;
use Filament\Resources\Pages\CreateRecord;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

    protected array $selectedSubjectIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->selectedSubjectIds = array_values(array_filter((array) ($data['subject_ids'] ?? [])));

        unset($data['subject_ids']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncSubjects();

        app(ActivityLogger::class)->logModelCreated(
            $this->getRecord(),
            'students',
            'student_created'
        );
    }

    protected function syncSubjects(): void
    {
        if ($this->selectedSubjectIds === []) {
            return;
        }

        $academicTermId = app(AcademicTermContext::class)->requireCurrent()->id;
        $student = $this->getRecord();
        assert($student instanceof Student);

        foreach (Subject::query()->whereKey($this->selectedSubjectIds)->get(['id', 'level']) as $subject) {
            Enrollment::query()->updateOrCreate(
                [
                    'academic_term_id' => $academicTermId,
                    'student_id' => $student->id,
                    'subject_id' => $subject->id,
                ],
                [
                    'semester' => null,
                    'year' => $subject->level,
                    'status' => Enrollment::STATUS_ENROLLED,
                ],
            );
        }
    }
}
