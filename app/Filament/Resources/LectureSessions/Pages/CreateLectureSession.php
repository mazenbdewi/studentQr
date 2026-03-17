<?php

namespace App\Filament\Resources\LectureSessions\Pages;

use App\Filament\Resources\LectureSessions\LectureSessionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLectureSession extends CreateRecord
{
    protected static string $resource = LectureSessionResource::class;


    protected function afterCreate(): void
{
    $lectureSession = $this->record;
    $students = $lectureSession->subject->students;

    foreach ($students as $student) {
        \App\Models\Attendance::firstOrCreate([
            'student_id' => $student->id,
            'lecture_session_id' => $lectureSession->id,
        ], [
            'attendance_status' => 'pending',
            'attendance_time' => now(),
        ]);
    }
}
}
