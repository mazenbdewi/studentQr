<?php

namespace App\Exports;

class LecturerPasswordResetCredentialsExport extends ArabicArrayWorkbookExport
{
    /** @param array<int, array<string, string|bool>> $rows */
    public function __construct(array $rows)
    {
        parent::__construct([[
            'title' => 'بيانات دخول المدرسين',
            'headings' => ['اسم المحاضر', 'اسم الدخول', 'كلمة المرور المؤقتة', 'النتيجة', 'الدور', 'الملاحظة'],
            'rows' => collect($rows)->map(fn (array $row): array => [
                'اسم المحاضر' => $row['lecturer_name'],
                'اسم الدخول' => $row['login_username'],
                'كلمة المرور المؤقتة' => $row['temporary_password'],
                'النتيجة' => $row['result'] ?? '',
                'الدور' => $row['role'] ?? '',
                'الملاحظة' => $row['note'] ?? '',
            ])->all(),
        ]]);
    }
}
