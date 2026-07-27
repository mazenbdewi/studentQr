<?php

use App\Models\AcademicTerm;
use App\Models\Hall;
use App\Models\LectureSession;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\User;
use App\Services\LectureSessionCalendarService;
use Spatie\Permission\Models\Role;

function createCalendarServiceSubject(): array
{
    Role::firstOrCreate(['name' => 'course_lecturer', 'guard_name' => 'web']);

    $lecturer = User::factory()->create([
        'role' => 'course_lecturer',
        'type' => 'teacher',
        'status' => 'active',
        'is_active' => true,
    ]);
    $lecturer->assignRole('course_lecturer');

    $term = AcademicTerm::query()->create([
        'display_name' => 'Calendar Test Term',
        'canonical_name' => 'calendar-test-term-'.uniqid(),
        'teaching_start_date' => '2026-05-01',
        'teaching_end_date' => '2026-05-31',
    ]);

    $subject = Subject::create([
        'code' => 'CAL-101',
        'name' => 'Calendar Test Subject',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'lecturer_id' => $lecturer->id,
        'is_active' => true,
    ]);

    $hall = Hall::forceCreate([
        'code' => 'CAL-H1',
        'name' => 'Calendar Hall',
        'floor' => 1,
        'is_active' => true,
    ]);

    $section = SubjectSection::query()->create([
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'lecturer_id' => $lecturer->id,
        'code' => 'T1',
    ]);

    return [$term, $subject, $section, $hall];
}

it('creates lecture sessions for matching weekdays in a selected date range', function (): void {
    [$term, $subject, $section, $hall] = createCalendarServiceSubject();

    $result = app(LectureSessionCalendarService::class)->createRecurring([
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'subject_section_id' => $section->id,
        'hall_id' => $hall->id,
        'date_from' => '2026-05-04',
        'date_to' => '2026-05-17',
        'weekdays' => [1, 3],
        'start_time' => '08:00',
        'end_time' => '09:30',
        'status' => 'scheduled',
        'qr_refresh_rate' => 90,
        'notes' => 'Generated from calendar',
    ]);

    expect($result['created'])->toBe(4)
        ->and($result['skipped'])->toBe(0)
        ->and(LectureSession::query()->count())->toBe(4)
        ->and(LectureSession::query()->orderBy('session_date')->pluck('session_date')->map->toDateString()->all())
        ->toBe([
            '2026-05-04',
            '2026-05-06',
            '2026-05-11',
            '2026-05-13',
        ])
        ->and(LectureSession::query()->first()->qr_refresh_rate)->toBe(90);
});

it('skips already existing recurring lecture sessions', function (): void {
    [$term, $subject, $section, $hall] = createCalendarServiceSubject();

    $payload = [
        'academic_term_id' => $term->id,
        'subject_id' => $subject->id,
        'subject_section_id' => $section->id,
        'hall_id' => $hall->id,
        'date_from' => '2026-05-04',
        'date_to' => '2026-05-17',
        'weekdays' => [1, 3],
        'start_time' => '08:00',
        'end_time' => '09:30',
        'status' => 'scheduled',
        'qr_refresh_rate' => 90,
    ];

    app(LectureSessionCalendarService::class)->createRecurring($payload);
    $secondResult = app(LectureSessionCalendarService::class)->createRecurring($payload);

    expect($secondResult['created'])->toBe(0)
        ->and($secondResult['skipped'])->toBe(4)
        ->and(LectureSession::query()->count())->toBe(4);
});
