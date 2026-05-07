<?php

namespace App\Filament\Resources\Subjects\Pages;

use App\Filament\Resources\Subjects\SubjectResource;
use App\Services\ActivityLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateSubject extends CreateRecord
{
    protected static string $resource = SubjectResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return SubjectResource::validateSubjectFacultyDepartment($data);
    }

    protected function afterCreate(): void
    {
        app(ActivityLogger::class)->logModelCreated(
            $this->getRecord(),
            'subjects',
            'subject_created'
        );
    }
}
