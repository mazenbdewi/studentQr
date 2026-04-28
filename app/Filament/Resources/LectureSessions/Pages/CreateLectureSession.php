<?php

namespace App\Filament\Resources\LectureSessions\Pages;

use App\Filament\Resources\LectureSessions\LectureSessionResource;
use App\Services\ActivityLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateLectureSession extends CreateRecord
{
    protected static string $resource = LectureSessionResource::class;


    protected function afterCreate(): void
    {
        app(ActivityLogger::class)->logModelCreated(
            $this->getRecord(),
            'lecture_sessions',
            'lecture_session_created'
        );
    }
}
