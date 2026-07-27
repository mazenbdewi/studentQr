<?php

use App\Models\ScheduleImportIssue;

it('resolves reconciliation issue classifications to Arabic labels without changing stored keys', function (): void {
    app()->setLocale('ar');

    expect(ScheduleImportIssue::label(ScheduleImportIssue::TYPE_NON_AUTHORITATIVE_SUBJECT_CODE))
        ->toBe('رمز المادة غير معتمد')
        ->and(ScheduleImportIssue::TYPE_NON_AUTHORITATIVE_SUBJECT_CODE)
        ->toBe('non_authoritative_subject_code');
});
