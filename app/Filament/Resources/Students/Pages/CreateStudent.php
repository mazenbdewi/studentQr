<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Services\ActivityLogger;
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
        foreach (Subject::query()->whereKey($this->selectedSubjectIds)->get(['id', 'level']) as $subject) {
            Enrollment::query()->updateOrCreate(
                [
                    'student_id' => $this->getRecord()->id,
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
