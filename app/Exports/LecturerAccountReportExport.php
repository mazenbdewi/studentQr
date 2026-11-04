<?php

namespace App\Exports;

use App\Models\LecturerAccountGenerationItem;
use App\Models\LecturerAccountGenerationRun;

class LecturerAccountReportExport extends ArabicArrayWorkbookExport
{
    /** @param array<int, array<string, mixed>> $credentialRows */
    public static function successForRun(LecturerAccountGenerationRun $run, array $credentialRows = []): self
    {
        $passwordsByUsername = collect($credentialRows)
            ->pluck('temporary_password', 'login_username');
        $successResults = [
            LecturerAccountGenerationItem::RESULT_ACCOUNT_CREATED,
            LecturerAccountGenerationItem::RESULT_EXISTING_ACCOUNT,
            LecturerAccountGenerationItem::RESULT_ROLE_ADDED,
            LecturerAccountGenerationItem::RESULT_USERNAME_ASSIGNED,
            LecturerAccountGenerationItem::RESULT_TEMPORARY_PASSWORD_RESET,
        ];

        $rows = $run->items()
            ->with(['lecturer', 'user.roles'])
            ->whereIn('result', $successResults)
            ->orderBy('id')
            ->get()
            ->map(fn (LecturerAccountGenerationItem $item): array => [
                'اسم المدرس' => $item->lecturer?->name,
                'اسم الدخول' => $item->login_username,
                'كلمة المرور الابتدائية' => $item->result === LecturerAccountGenerationItem::RESULT_ACCOUNT_CREATED
                    ? ($passwordsByUsername->get($item->login_username) ?? '—')
                    : '—',
                'النتيجة' => $item->result,
                'الحساب المنشأ أو المعاد استخدامه' => $item->user_id ? __('lecturer-account-preparation.results.'.$item->result) : '',
                'الدور' => $item->user?->hasRole('course_lecturer') ? 'course_lecturer' : '',
                'الملاحظة' => $item->message,
            ])
            ->all();

        return self::success($rows);
    }

    /** @param array<int, array<string, mixed>> $rows */
    public static function success(array $rows): self
    {
        return new self('العمليات الناجحة', [
            'اسم المدرس',
            'اسم الدخول',
            'كلمة المرور الابتدائية',
            'النتيجة',
            'الحساب المنشأ أو المعاد استخدامه',
            'الدور',
            'الملاحظة',
        ], $rows);
    }

    /** @param array<int, array<string, mixed>> $rows */
    public static function errors(array $rows): self
    {
        return new self('الأخطاء والحالات المستبعدة', [
            'اسم المدرس',
            'اسم الدخول المقترح',
            'رمز الخطأ',
            'السبب بالعربية',
            'الإجراء المقترح',
        ], $rows);
    }

    /** @param array<int, string> $headings */
    private function __construct(string $title, array $headings, array $rows)
    {
        parent::__construct([[
            'title' => $title,
            'headings' => $headings,
            'rows' => $rows,
        ]]);
    }
}
