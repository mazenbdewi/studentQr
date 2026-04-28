<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Hall;
use App\Models\LectureSession;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;


class RolesAndPermissionsSeeder extends Seeder
{

    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        AppSetting::put('qr_base_url', AppSetting::value('qr_base_url', rtrim((string) config('app.url'), '/')));

        $cs = Faculty::withTrashed()->updateOrCreate(
            ['name' => 'كلية المعلوماتية'],
            ['name' => 'كلية المعلوماتية']
        );

        if ($cs->trashed()) {
            $cs->restore();
        }

        $departments = collect([
            ['code' => 'CS-SE', 'name' => 'هندسة البرمجيات'],
            ['code' => 'CS-NET', 'name' => 'الشبكات'],
            ['code' => 'CS-AI', 'name' => 'الذكاء'],
        ])->mapWithKeys(function (array $department) use ($cs): array {
            $record = Department::withTrashed()->updateOrCreate(
                ['code' => $department['code']],
                [
                    'faculty_id' => $cs->id,
                    'name' => $department['name'],
                ]
            );

            if ($record->trashed()) {
                $record->restore();
            }

            return [$department['code'] => $record];
        });


        $permissions = [
            'view_dashboard',
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            'import_users',
            'export_users',
            'activate_users',
            'block_users',


            'view_lecturers',
            'create_lecturers',
            'edit_lecturers',
            'delete_lecturers',
            'activate_lecturers',


            'view_subjects',
            'create_subjects',
            'edit_subjects',
            'delete_subjects',
            'assign_lecturer_to_subject',


            'view_halls',
            'create_halls',
            'edit_halls',
            'delete_halls',


            'view_departments',
            'create_departments',
            'edit_departments',
            'delete_departments',


            'view_lecture_sessions',
            'create_lecture_sessions',
            'edit_lecture_sessions',
            'delete_lecture_sessions',
            'start_lecture_session',
            'end_lecture_session',
            'cancel_lecture_session',


            'record_attendance',
            'view_attendances',
            'edit_attendances',
            'export_attendances',


            'view_reports',
            'generate_reports',
            'export_reports',


            'view_system_users',
            'create_system_users',
            'edit_system_users',
            'delete_system_users',


            'view_roles',
            'create_roles',
            'edit_roles',
            'delete_roles',
            'view_permissions',
            'assign_permissions',


            'view_student_devices',
            'block_student_devices',


            'view_audit_logs',
        ];

        $permissions = array_merge($permissions, [
            'view_any_user',
            'view_user',
            'create_user',
            'update_user',
            'delete_user',
            'delete_any_user',
            'restore_user',
            'restore_any_user',
            'force_delete_user',
            'force_delete_any_user',
            'replicate_user',
            'reorder_user',
        ]);

        collect([
            [
                'code' => 'HALL-001',
                'name' => 'القاعة الرئيسية',
                'floor' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'HALL-003',
                'name' => 'قاعة 2',
                'floor' => 3,
                'is_active' => true,
            ],
        ])->each(function (array $hall): void {
            $record = Hall::withTrashed()->updateOrCreate(
                ['code' => $hall['code']],
                $hall,
            );

            if ($record->trashed()) {
                $record->restore();
            }
        });


        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }


        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $lecturerRole = Role::firstOrCreate(['name' => 'course_lecturer', 'guard_name' => 'web']);


        $superAdminRole->syncPermissions(Permission::all());


        $managerRole->syncPermissions([
            'view_dashboard',
            'view_lecture_sessions',
            'view_attendances',
            'edit_attendances',
            'view_reports',
            'generate_reports',
            'export_reports',
            'view_student_devices',
            'block_student_devices',
            'view_audit_logs',
        ]);


        $lecturerRole->syncPermissions([
            'view_dashboard',
            'view_subjects',
            'view_lecture_sessions',
            'create_lecture_sessions',
            'edit_lecture_sessions',
            'start_lecture_session',
            'end_lecture_session',
            'view_attendances',
            'export_attendances',
            'view_reports',
            'generate_reports',
        ]);
        $this->createTestUsers($cs, $departments);
    }

    private function createTestUsers(Faculty $faculty, Collection $departments): void
    {
        $softwareDepartment = $departments->get('CS-SE');

        $superAdmin = User::withTrashed()->updateOrCreate(
            ['email' => 'super@admin.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('123'),
                'role' => 'super_admin',
                'type' => 'admin',
                'status' => 'active',
                'is_active' => true,
            ]
        );

        if ($superAdmin->trashed()) {
            $superAdmin->restore();
        }

        $superAdmin->assignRole('super-admin');


        $lecturer = User::withTrashed()->updateOrCreate(
            ['email' => 'ahmed@uni.edu'],
            [
                'name' => 'Dr. Ahmed',
                'password' => Hash::make('123'),
                'role' => 'course_lecturer',
                'type' => 'lecturer',
                'status' => 'active',
                'title' => 'professor',
                'is_active' => true,
            ]
        );

        if ($lecturer->trashed()) {
            $lecturer->restore();
        }

        $lecturer->assignRole('course_lecturer');


        $manager = User::withTrashed()->updateOrCreate(
            ['email' => 'ali@uni.edu'],
            [
                'name' => 'Ali',
                'password' => Hash::make('123'),
                'role' => 'attendance_monitor',
                'type' => 'manager',
                'status' => 'active',
                'student_number' => 'S12345',
                'is_active' => true,
            ]
        );

        if ($manager->trashed()) {
            $manager->restore();
        }

        $manager->assignRole('manager');

        collect([
            [
                'name' => 'nour',
                'faculty_id' => $faculty->id,
                'department_id' => $softwareDepartment?->id,
                'year' => 3,
                'type' => 'student',
                'phone' => '0912345678',
                'status' => 'active',
                'student_number' => '20230001',
                'national_number' => '12345678901',
                'avatar' => null,
                'is_active' => false,
            ],
            [
                'name' => 'Lama',
                'faculty_id' => $faculty->id,
                'department_id' => $softwareDepartment?->id,
                'year' => 2,
                'type' => 'student',
                'phone' => '0987654321',
                'status' => 'pending',
                'student_number' => '20230002',
                'national_number' => '10987654321',
                'avatar' => null,
                'is_active' => false,
            ],
        ])->each(function (array $student): void {
            $record = Student::withTrashed()->updateOrCreate(
                ['student_number' => $student['student_number']],
                $student,
            );

            if ($record->trashed()) {
                $record->restore();
            }
        });

        $subject = Subject::withTrashed()->updateOrCreate(
            ['code' => 'P101'],
            [
                'name' => 'programming 1',
                'department_id' => $softwareDepartment?->id,
                'lecturer_id' => $lecturer->id,
                'credit_hours' => 3,
                'level' => 1,
                'semester' => Subject::SEMESTER_FIRST,
                'is_active' => true,
            ]
        );

        if ($subject->trashed()) {
            $subject->restore();
        }

        LectureSession::query()
            ->where('subject_id', $subject->id)
            ->update(['lecturer_id' => $lecturer->id]);
    }
}
