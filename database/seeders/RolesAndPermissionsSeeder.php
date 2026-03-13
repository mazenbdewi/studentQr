<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Hall;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;


class RolesAndPermissionsSeeder extends Seeder
{

    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $cs = Faculty::create([
            'name' => 'كلية المعلوماتية'
        ]);


        Department::create([
            'faculty_id' => $cs->id,
            'code' => 'CS-SE',
            'name' => 'هندسة البرمجيات'
        ]);

        Department::create([
            'faculty_id' => $cs->id,
            'code' => 'CS-NET',
            'name' => 'الشبكات'
        ]);

        Department::create([
            'faculty_id' => $cs->id,
            'code' => 'CS-AI',
            'name' => 'الذكاء'
        ]);


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

        Hall::create([

            'code' => 'HALL-001',
            'name' => 'القاعة الرئيسية',
            'floor' => 1,
            'capacity' => 100,
            'has_projector' => true,
            'has_computer' => true,
            'network_ssid' => 'MainHall-WiFi',
            'ip_range_start' => '192.168.1.100',
            'ip_range_end' => '192.168.1.200',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
            [
                'code' => 'HALL-003',
                'name' => 'قاعة 2',
                'floor' => 3,
                'capacity' => 30,
                'has_projector' => false,
                'has_computer' => true,
                'network_ssid' => 'Lab-Net',
                'ip_range_start' => '10.0.0.1',
                'ip_range_end' => '10.0.0.254',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        Subject::create([

            'code' => 'P101',
            'name' => 'programming 1',
            'department_id' => 1,

            'credit_hours' => 3,
            'level' => 1,
            'semester' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
            [
                'code' => 'P102',
                'name' => 'programming 2',
                'department_id' => 1,
                'credit_hours' => 3,
                'level' => 1,
                'semester' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'code' => 'P103',
                'name' => 'programming 3',
                'department_id' => 1,
                'credit_hours' => 3,
                'level' => 1,
                'semester' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

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


        $this->createTestUsers();
    }

    private function createTestUsers(): void
    {

        User::firstOrCreate(
            ['email' => 'super@admin.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('123'),
                'type' => 'admin',
                'status' => 'active',
            ]
        )->assignRole('super-admin');


        User::firstOrCreate(
            ['email' => 'ahmed@uni.edu'],
            [
                'name' => 'Dr. Ahmed',
                'password' => Hash::make('123'),
                'type' => 'lecturer',
                'status' => 'active',
                'title' => 'professor',
            ]
        )->assignRole('course_lecturer');


        User::firstOrCreate(
            ['email' => 'ali@uni.edu'],
            [
                'name' => 'Ali',
                'password' => Hash::make('123'),
                'type' => 'manager',
                'status' => 'active',
                'student_number' => 'S12345',
            ]
        )->assignRole('manager');

        Student::insert([
            [
                'name' => 'nour',
                'faculty_id' => 1,
                'department_id' => 1,
                'year' => 3,
                'type' => 'student',
                'phone' => '0912345678',
                'status' => 'active',
                'student_number' => '20230001',
                'national_number' => '12345678901',
                'avatar' => null,
                'is_active' => 0,
            ],
            [
                'name' => 'Lama',
                'faculty_id' => 1,
                'department_id' => 1,
                'year' => 2,
                'type' => 'student',
                'phone' => '0987654321',
                'status' => 'pending',
                'student_number' => '20230002',
                'national_number' => '10987654321',
                'avatar' => null,
                'is_active' => 0,
            ],
        ]);

    }
}
