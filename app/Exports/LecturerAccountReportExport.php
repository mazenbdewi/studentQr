<?php

namespace App\Exports;

use App\Models\LecturerAccountGenerationItem;
use App\Models\LecturerAccountGenerationRun;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class LecturerAccountReportExport extends ArabicArrayWorkbookExport
{
    public static function successForRun(LecturerAccountGenerationRun $run): self
    {
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
                'اسم الدخول' => $item->user->login_username ?? $item->login_username,
                'كلمة المرور الابتدائية / المؤقتة' => self::temporaryPasswordForReport($item->user),
                'النتيجة' => $item->result,
                'الحساب المنشأ أو المعاد استخدامه' => $item->user_id ? __('lecturer-account-preparation.results.'.$item->result) : '',
                'الدور' => $item->user?->hasRole('course_lecturer') ? 'course_lecturer' : '',
                'الملاحظة' => $item->message,
            ])
            ->all();

        return self::success($rows);
    }

    public static function temporaryPasswordForReport(?User $user): string
    {
        if (! $user instanceof User || ! $user->must_change_password) {
            return '—';
        }

        if (blank($user->temporary_password_encrypted)) {
            return 'غير متاحة — يلزم إعادة تعيين كلمة المرور';
        }

        try {
            $password = Crypt::decryptString((string) $user->temporary_password_encrypted);

            if (Hash::check($password, (string) $user->password)) {
                return $password;
            }
        } catch (Throwable) {
            // Never include the encrypted value, plaintext, or exception context in logs.
        }

        Log::warning('Lecturer temporary password is unavailable for reporting.', ['user_id' => $user->id]);

        return 'غير متاحة';
    }

    /** @param array<int, array<string, mixed>> $rows */
    public static function success(array $rows): self
    {
        return new self('العمليات الناجحة', [
            'اسم المدرس',
            'اسم الدخول',
            'كلمة المرور الابتدائية / المؤقتة',
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
