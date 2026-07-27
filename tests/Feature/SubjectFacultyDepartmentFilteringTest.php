<?php

use App\Filament\Resources\Subjects\Pages\CreateSubject;
use App\Filament\Resources\Subjects\Pages\EditSubject;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Subject;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'course_lecturer', 'guard_name' => 'web']);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function subjectAdmin(): User
{
    $user = User::factory()->create([
        'login_username' => fake()->unique()->bothify('subject-admin-####??'),
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
        'is_active' => true,
    ]);

    $user->assignRole('super-admin');

    return $user;
}

function subjectLecturer(): User
{
    $user = User::factory()->create([
        'login_username' => fake()->unique()->bothify('subject-lecturer-####??'),
        'role' => 'course_lecturer',
        'type' => 'lecturer',
        'status' => 'active',
        'is_active' => true,
    ]);

    $user->assignRole('course_lecturer');

    return $user;
}

function subjectFaculty(string $name): Faculty
{
    return Faculty::query()->create([
        'name' => $name,
        'is_active' => true,
    ]);
}

function subjectDepartment(Faculty $faculty, string $name, string $code): Department
{
    return Department::query()->create([
        'faculty_id' => $faculty->id,
        'name' => $name,
        'code' => $code,
        'is_active' => true,
    ]);
}

function subjectFormData(Faculty $faculty, Department $department, User $lecturer, string $code = 'SUB-101'): array
{
    return [
        'faculty_id' => $faculty->id,
        'department_id' => $department->id,
        'code' => $code,
        'name' => "Subject {$code}",
        'subject_type' => Subject::TYPE_THEORETICAL,
        'lecturer_id' => $lecturer->id,
        'is_active' => true,
    ];
}

it('returns only departments that belong to the selected faculty', function (): void {
    $facultyA = subjectFaculty('Faculty A');
    $facultyB = subjectFaculty('Faculty B');
    $departmentA = subjectDepartment($facultyA, 'Department A', 'DEP-A');
    $departmentB = subjectDepartment($facultyB, 'Department B', 'DEP-B');

    expect(SubjectResource::getDepartmentOptionsForFaculty($facultyA->id))
        ->toBe([$departmentA->id => $departmentA->name])
        ->and(SubjectResource::getDepartmentOptionsForFaculty($facultyB->id))
        ->toBe([$departmentB->id => $departmentB->name])
        ->and(SubjectResource::getDepartmentOptionsForFaculty(null))
        ->toBe([]);
});

it('clears the selected department when the faculty changes on the create page', function (): void {
    $admin = subjectAdmin();
    $facultyA = subjectFaculty('Faculty A');
    $facultyB = subjectFaculty('Faculty B');
    $departmentA = subjectDepartment($facultyA, 'Department A', 'DEP-A1');
    subjectDepartment($facultyB, 'Department B', 'DEP-B1');

    Livewire::actingAs($admin)
        ->test(CreateSubject::class)
        ->set('data.faculty_id', $facultyA->id)
        ->set('data.department_id', $departmentA->id)
        ->set('data.faculty_id', $facultyB->id)
        ->assertSet('data.department_id', null);
});

it('prevents creating a subject with a department from another faculty', function (): void {
    $admin = subjectAdmin();
    $lecturer = subjectLecturer();
    $facultyA = subjectFaculty('Faculty A');
    $facultyB = subjectFaculty('Faculty B');
    $departmentB = subjectDepartment($facultyB, 'Department B', 'DEP-B2');

    Livewire::actingAs($admin)
        ->test(CreateSubject::class)
        ->fillForm(subjectFormData($facultyA, $departmentB, $lecturer))
        ->call('create')
        ->assertHasFormErrors(['department_id']);

    expect(Subject::query()->count())->toBe(0);
});

it('creates a subject when the department belongs to the selected faculty', function (): void {
    $admin = subjectAdmin();
    $lecturer = subjectLecturer();
    $faculty = subjectFaculty('Faculty A');
    $department = subjectDepartment($faculty, 'Department A', 'DEP-A2');

    Livewire::actingAs($admin)
        ->test(CreateSubject::class)
        ->fillForm(subjectFormData($faculty, $department, $lecturer, 'SUB-201'))
        ->call('create')
        ->assertHasNoFormErrors();

    $subject = Subject::query()->first();

    expect($subject)->not->toBeNull()
        ->and($subject?->department_id)->toBe($department->id);
});

it('hydrates the correct faculty on the edit page and clears the department when the faculty changes', function (): void {
    $admin = subjectAdmin();
    $lecturer = subjectLecturer();
    $facultyA = subjectFaculty('Faculty A');
    $facultyB = subjectFaculty('Faculty B');
    $departmentA = subjectDepartment($facultyA, 'Department A', 'DEP-A3');
    subjectDepartment($facultyB, 'Department B', 'DEP-B3');
    $subject = Subject::query()->create([
        'code' => 'SUB-301',
        'name' => 'Subject 301',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'lecturer_id' => $lecturer->id,
        'department_id' => $departmentA->id,
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(EditSubject::class, ['record' => $subject->getRouteKey()])
        ->assertSet('data.faculty_id', $facultyA->id)
        ->assertSet('data.department_id', $departmentA->id)
        ->set('data.faculty_id', $facultyB->id)
        ->assertSet('data.department_id', null);
});

it('prevents editing a subject with a department from another faculty', function (): void {
    $admin = subjectAdmin();
    $lecturer = subjectLecturer();
    $facultyA = subjectFaculty('Faculty A');
    $facultyB = subjectFaculty('Faculty B');
    $departmentA = subjectDepartment($facultyA, 'Department A', 'DEP-A4');
    $departmentB = subjectDepartment($facultyB, 'Department B', 'DEP-B4');
    $subject = Subject::query()->create([
        'code' => 'SUB-401',
        'name' => 'Subject 401',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'lecturer_id' => $lecturer->id,
        'department_id' => $departmentA->id,
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(EditSubject::class, ['record' => $subject->getRouteKey()])
        ->fillForm(subjectFormData($facultyA, $departmentB, $lecturer, 'SUB-401'))
        ->call('save')
        ->assertHasFormErrors(['department_id']);

    expect($subject->refresh()->department_id)->toBe($departmentA->id);
});

it('updates a subject when the new department belongs to the selected faculty', function (): void {
    $admin = subjectAdmin();
    $lecturer = subjectLecturer();
    $facultyA = subjectFaculty('Faculty A');
    $departmentA = subjectDepartment($facultyA, 'Department A', 'DEP-A5');
    $departmentB = subjectDepartment($facultyA, 'Department B', 'DEP-A6');
    $subject = Subject::query()->create([
        'code' => 'SUB-501',
        'name' => 'Subject 501',
        'subject_type' => Subject::TYPE_THEORETICAL,
        'lecturer_id' => $lecturer->id,
        'department_id' => $departmentA->id,
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(EditSubject::class, ['record' => $subject->getRouteKey()])
        ->fillForm(subjectFormData($facultyA, $departmentB, $lecturer, 'SUB-501'))
        ->call('save')
        ->assertHasNoFormErrors();

    expect($subject->refresh()->department_id)->toBe($departmentB->id);
});
