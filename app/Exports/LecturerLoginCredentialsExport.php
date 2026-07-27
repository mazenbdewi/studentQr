<?php

namespace App\Exports;

class LecturerLoginCredentialsExport extends ArabicArrayWorkbookExport
{
    /** @param array<int, array<string, mixed>> $credentialRows */
    public function __construct(array $credentialRows)
    {
        parent::__construct([
            [
                'title' => 'بيانات دخول المدرسين',
                'headings' => [
                    'الرقم',
                    'اسم المدرس',
                    'اسم الدخول',
                    'كلمة المرور المؤقتة',
                    'حالة الحساب',
                    'الدور',
                    'تغيير كلمة المرور عند أول دخول',
                    'ملاحظات',
                ],
                'rows' => collect($credentialRows)
                    ->values()
                    ->map(fn (array $row, int $index): array => [
                        'الرقم' => $index + 1,
                        'اسم المدرس' => $row['lecturer_name'] ?? '',
                        'اسم الدخول' => $row['login_username'] ?? '',
                        'كلمة المرور المؤقتة' => $row['temporary_password'] ?? '',
                        'حالة الحساب' => $row['account_status'] ?? '',
                        'الدور' => $row['role'] ?? 'course_lecturer',
                        'تغيير كلمة المرور عند أول دخول' => ($row['must_change_password'] ?? false) ? 'نعم' : 'لا',
                        'ملاحظات' => $row['notes'] ?? 'يجب تغيير كلمة المرور المؤقتة عند أول تسجيل دخول.',
                    ])
                    ->all(),
            ],
            [
                'title' => 'تعليمات الاستخدام',
                'headings' => ['التعليمات'],
                'rows' => [
                    ['التعليمات' => 'يجب تغيير كلمة المرور المؤقتة عند أول تسجيل دخول.'],
                    ['التعليمات' => 'اسم الدخول يستخدم بدل البريد الإلكتروني.'],
                    ['التعليمات' => 'كلمة المرور مؤقتة.'],
                    ['التعليمات' => 'يجب تغيير كلمة المرور عند أول دخول.'],
                    ['التعليمات' => 'يجب حفظ الملف في مكان آمن.'],
                    ['التعليمات' => 'لا يمكن استعادة كلمة المرور المؤقتة نفسها بعد فقدان الملف.'],
                    ['التعليمات' => 'عند ضياع الملف يجب استخدام إجراء إعادة تعيين كلمات المرور المؤقتة.'],
                ],
            ],
        ]);
    }
}
