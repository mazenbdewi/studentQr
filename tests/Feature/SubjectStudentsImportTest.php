<?php

use App\Imports\SubjectStudentsImport;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Collection;

it('upserts subject enrollments without creating duplicates', function () {
    [$subject, $student] = createSubjectEnrollmentFixture(createStudent: true);

    Enrollment::query()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'semester' => 1,
        'year' => 1,
        'status' => Enrollment::STATUS_ENROLLED,
    ]);

    $import = new SubjectStudentsImport($subject->id);

    $import->collection(new Collection([
        [
            'student_number' => $student->student_number,
            'semester' => 2,
            'year' => 3,
            'status' => Enrollment::STATUS_PASSED,
        ],
    ]));

    expect(Enrollment::query()->count())->toBe(1)
        ->and(Enrollment::query()->first())->toMatchArray([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'semester' => Subject::SEMESTER_SECOND,
            'year' => 3,
            'status' => Enrollment::STATUS_PASSED,
        ]);
});

it('fails the subject student import when the student does not already exist', function () {
    [$subject] = createSubjectEnrollmentFixture(createStudent: false);

    $import = new SubjectStudentsImport($subject->id);

    expect(fn () => $import->collection(new Collection([
        [
            'student_number' => '999999',
            'semester' => 2,
            'year' => 3,
            'status' => Enrollment::STATUS_ENROLLED,
        ],
    ])))->toThrow(RuntimeException::class);

    expect(Student::query()->count())->toBe(0)
        ->and(Enrollment::query()->count())->toBe(0);
});

function createSubjectEnrollmentFixture(bool $createStudent): array
{
    $faculty = Faculty::query()->create([
        'name' => 'Faculty',
        'name_en' => 'Faculty',
        'is_active' => true,
    ]);

    $department = Department::query()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Department',
        'name_en' => 'Department',
        'code' => 'DEP',
        'is_active' => true,
    ]);

    $lecturer = User::query()->create([
        'name' => 'Lecturer',
        'email' => 'lecturer@example.com',
        'password' => 'password',
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'faculty_id' => $faculty->id,
        'department_id' => $department->id,
        'title' => 'lecturer',
        'is_active' => true,
    ]);

    $subject = Subject::query()->create([
        'code' => 'SUB101',
        'name' => 'Subject',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'lecturer_id' => $lecturer->id,
        'department_id' => $department->id,
        'credit_hours' => 3,
        'level' => 3,
        'is_active' => true,
    ]);

    $student = $createStudent
        ? Student::query()->create([
            'name' => 'Student',
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'year' => 3,
            'status' => 'active',
            'student_number' => '2024001',
            'national_number' => '12345678901',
            'is_active' => true,
        ])
        : null;

    return [$subject, $student];
}
