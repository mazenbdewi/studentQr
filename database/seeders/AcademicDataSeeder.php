<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class AcademicDataSeeder extends Seeder
{
    public function run(): void
    {
        $faculties = [
            [
                'name' => 'كلية الهندسة',
                'name_en' => 'Faculty of Engineering',
                'departments' => [
                    [
                        'code' => 'CSE',
                        'name' => 'هندسة المعلوماتية',
                        'name_en' => 'Computer Engineering',
                        'subjects' => [
                            ['code' => 'CSE101', 'name' => 'مقدمة في البرمجة', 'level' => 1, 'semester' => Subject::SEMESTER_FIRST, 'credit_hours' => 3],
                            ['code' => 'CSE102', 'name' => 'بنى المعطيات', 'level' => 1, 'semester' => Subject::SEMESTER_SECOND, 'credit_hours' => 3],
                            ['code' => 'CSE201', 'name' => 'قواعد البيانات', 'level' => 2, 'semester' => Subject::SEMESTER_FIRST, 'credit_hours' => 3],
                            ['code' => 'CSE202', 'name' => 'شبكات الحاسوب', 'level' => 2, 'semester' => Subject::SEMESTER_SECOND, 'credit_hours' => 3],
                        ],
                    ],
                    [
                        'code' => 'CIV',
                        'name' => 'الهندسة المدنية',
                        'name_en' => 'Civil Engineering',
                        'subjects' => [
                            ['code' => 'CIV101', 'name' => 'ميكانيك هندسي', 'level' => 1, 'semester' => Subject::SEMESTER_FIRST, 'credit_hours' => 3],
                            ['code' => 'CIV201', 'name' => 'مقاومة مواد', 'level' => 2, 'semester' => Subject::SEMESTER_FIRST, 'credit_hours' => 3],
                            ['code' => 'CIV202', 'name' => 'تحليل إنشائي', 'level' => 2, 'semester' => Subject::SEMESTER_SECOND, 'credit_hours' => 3],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'كلية العلوم الصحية',
                'name_en' => 'Faculty of Health Sciences',
                'departments' => [
                    [
                        'code' => 'NUR',
                        'name' => 'التمريض',
                        'name_en' => 'Nursing',
                        'subjects' => [
                            ['code' => 'NUR101', 'name' => 'أساسيات التمريض', 'level' => 1, 'semester' => Subject::SEMESTER_FIRST, 'credit_hours' => 3],
                            ['code' => 'NUR102', 'name' => 'تشريح ووظائف الأعضاء', 'level' => 1, 'semester' => Subject::SEMESTER_SECOND, 'credit_hours' => 3],
                            ['code' => 'NUR201', 'name' => 'تمريض صحة المجتمع', 'level' => 2, 'semester' => Subject::SEMESTER_FIRST, 'credit_hours' => 3],
                        ],
                    ],
                    [
                        'code' => 'LAB',
                        'name' => 'المخابر الطبية',
                        'name_en' => 'Medical Laboratories',
                        'subjects' => [
                            ['code' => 'LAB101', 'name' => 'كيمياء حيوية', 'level' => 1, 'semester' => Subject::SEMESTER_FIRST, 'credit_hours' => 3],
                            ['code' => 'LAB102', 'name' => 'علم الدم', 'level' => 1, 'semester' => Subject::SEMESTER_SECOND, 'credit_hours' => 3],
                            ['code' => 'LAB201', 'name' => 'الأحياء الدقيقة الطبية', 'level' => 2, 'semester' => Subject::SEMESTER_FIRST, 'credit_hours' => 3],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($faculties as $facultyData) {
            $faculty = Faculty::query()->updateOrCreate(
                ['name' => $facultyData['name']],
                [
                    'name_en' => $facultyData['name_en'],
                    'description' => null,
                    'is_active' => true,
                ],
            );

            foreach ($facultyData['departments'] as $departmentData) {
                $department = Department::query()->updateOrCreate(
                    ['code' => $departmentData['code']],
                    [
                        'name' => $departmentData['name'],
                        'name_en' => $departmentData['name_en'],
                        'faculty_id' => $faculty->id,
                        'description' => null,
                        'is_active' => true,
                    ],
                );

                foreach ($departmentData['subjects'] as $subjectData) {
                    Subject::query()->updateOrCreate(
                        ['code' => $subjectData['code']],
                        [
                            'name' => $subjectData['name'],
                            'department_id' => $department->id,
                            'lecturer_id' => null,
                            'credit_hours' => $subjectData['credit_hours'],
                            'level' => $subjectData['level'],
                            'semester' => $subjectData['semester'],
                            'is_active' => true,
                        ],
                    );
                }
            }
        }
    }
}
