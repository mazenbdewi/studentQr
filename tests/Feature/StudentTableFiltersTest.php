<?php

use App\Filament\Resources\Students\Pages\ListStudents;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
});

it('filters students by faculty and department', function (): void {
    $user = User::factory()->create([
        'login_username' => 'student_filter_admin',
        'role' => 'super_admin',
        'type' => 'admin',
        'status' => 'active',
    ]);
    $user->assignRole('super-admin');

    $engineering = Faculty::query()->create(['name' => 'كلية الهندسة', 'is_active' => true]);
    $medicine = Faculty::query()->create(['name' => 'كلية الطب', 'is_active' => true]);

    $architecture = Department::query()->create([
        'faculty_id' => $engineering->id,
        'code' => 'ARCH',
        'name' => 'هندسة العمارة',
        'is_active' => true,
    ]);
    $civil = Department::query()->create([
        'faculty_id' => $engineering->id,
        'code' => 'CIV',
        'name' => 'الهندسة المدنية',
        'is_active' => true,
    ]);
    $dentistry = Department::query()->create([
        'faculty_id' => $medicine->id,
        'code' => 'DENT',
        'name' => 'طب الأسنان',
        'is_active' => true,
    ]);

    $architectureStudent = Student::query()->create([
        'name' => 'طالب عمارة',
        'faculty_id' => $engineering->id,
        'department_id' => $architecture->id,
        'student_number' => '20261001',
        'status' => 'active',
        'is_active' => true,
    ]);
    $civilStudent = Student::query()->create([
        'name' => 'طالب مدني',
        'faculty_id' => $engineering->id,
        'department_id' => $civil->id,
        'student_number' => '20261002',
        'status' => 'active',
        'is_active' => true,
    ]);
    $dentistryStudent = Student::query()->create([
        'name' => 'طالب أسنان',
        'faculty_id' => $medicine->id,
        'department_id' => $dentistry->id,
        'student_number' => '20261003',
        'status' => 'active',
        'is_active' => true,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($user)
        ->test(ListStudents::class)
        ->filterTable('faculty', $engineering->id)
        ->assertCanSeeTableRecords([$architectureStudent, $civilStudent])
        ->assertCanNotSeeTableRecords([$dentistryStudent])
        ->filterTable('department', $architecture->id)
        ->assertCanSeeTableRecords([$architectureStudent])
        ->assertCanNotSeeTableRecords([$civilStudent, $dentistryStudent]);
});
