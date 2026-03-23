<?php

namespace App\Filament\Resources\LectureSessions\Pages;

use App\Filament\Resources\LectureSessions\LectureSessionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLectureSession extends CreateRecord
{
    protected static string $resource = LectureSessionResource::class;


    protected function afterCreate(): void
{
    // REMOVED: Do not auto-create attendance records
    // Attendance only after successful QR/OTP validation via ProcessAttendanceJob
}
}
