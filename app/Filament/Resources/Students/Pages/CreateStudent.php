<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use App\Services\ActivityLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

    protected function afterCreate(): void
    {
        app(ActivityLogger::class)->logModelCreated(
            $this->getRecord(),
            'students',
            'student_created'
        );
    }
}
