<?php

use App\Filament\Resources\LectureSessions\Pages\ListLectureSessions;

function generationPreviewIssueGroups(array $preview): array
{
    $method = new ReflectionMethod(ListLectureSessions::class, 'weeklyGenerationIssueGroups');

    return $method->invoke(null, $preview);
}

it('renders the generation preview in separate Arabic sections without internal reason keys', function (): void {
    app()->setLocale('ar');

    $preview = [
        'source_slot_count' => 4,
        'candidate_session_count' => 16,
        'to_create_count' => 10,
        'already_existing_count' => 2,
        'manual_existing_count' => 1,
        'blocked_slot_count' => 2,
        'blocked_unique_count' => 1,
        'blocked_readiness_counts' => [
            'missing_lecturer_count' => 1,
            'missing_hall_count' => 1,
            'missing_lecturer_only' => 0,
            'missing_hall_only' => 0,
            'missing_both' => 1,
        ],
        'conflict_count' => 1,
        'ready_slot_count' => 2,
        'ready_slot_breakdown' => [
            'complete' => 1,
            'unassigned_lecturer' => 1,
            'unassigned_hall' => 0,
            'both_unassigned' => 0,
            'total' => 2,
        ],
        'ready_for_partial_generation' => false,
        'prerequisite_errors' => [],
        'blocked_slots' => [['reasons' => ['missing_lecturer_identity', 'missing_hall']]],
        'conflicts' => [['reason' => 'weekly_schedule_overlap']],
        'structural_readiness' => [
            'valid_subject_and_section' => 4,
            'slots_with_lecturer_identity' => 3,
            'slots_without_lecturer_identity' => 1,
            'slots_with_valid_linked_lecturer_account_and_role' => 3,
            'slots_with_halls' => 3,
            'slots_without_halls' => 1,
            'ready_slots' => 2,
            'blocked_slots' => 2,
        ],
    ];

    $html = view('filament.components.lecture-session-generation-preview', [
        'preview' => $preview,
        'issues' => [
            ['code' => 'missing_lecturer_identity', 'label' => 'لم يتم تحديد المدرّس', 'count' => 1],
            ['code' => 'missing_hall', 'label' => 'لم يتم تحديد القاعة', 'count' => 1],
            ['code' => 'weekly_schedule_overlap', 'label' => 'يوجد تعارض في الجدول الأسبوعي', 'count' => 1],
        ],
    ])->render();
    $view = file_get_contents(resource_path('views/filament/components/lecture-session-generation-preview.blade.php'));

    expect($html)
        ->toContain('يوجد 2 خانة جاهزة للتوليد و1 خانة تحتاج معالجة.')
        ->toContain('يمكنك توليد الجلسات الجاهزة الآن ومعالجة البقية لاحقًا.')
        ->toContain('سيتم تجاوز 1 خانة تحتاج معالجة')
        ->toContain('يوجد 3 جلسة منشأة مسبقًا ولن يتم تكرارها.')
        ->toContain('المشكلات التي تحتاج إلى معالجة')
        ->toContain('الخانات الجاهزة حسب حالة التعيين')
        ->toContain('إجمالي الخانات الجاهزة: 2')
        ->toContain('الخانات غير الجاهزة للتوليد: 1')
        ->toContain('قد تحتوي الخانة الواحدة على أكثر من مشكلة')
        ->toContain('عرض تفاصيل التحقق')
        ->toContain('لم يتم تحديد المدرّس')
        ->toContain('لم يتم تحديد القاعة')
        ->toContain('يوجد تعارض في الجدول الأسبوعي')
        ->not->toContain('missing_lecturer_identity')
        ->not->toContain('missing_hall')
        ->not->toContain('weekly_schedule_overlap')
        ->toContain('خانات محجوبة بتعارض حقيقي')
        ->not->toContain('لا توجد جلسات جديدة جاهزة للتوليد حاليًا');

    expect($view)
        ->toContain('heroicon-o-check-circle')
        ->toContain('heroicon-o-x-circle')
        ->toContain('grid gap-3');
});

it('routes term schedule and account generation issues to their dedicated actions', function (): void {
    app()->setLocale('ar');

    $preview = [
        'source_slot_count' => 2,
        'candidate_session_count' => 0,
        'to_create_count' => 0,
        'already_existing_count' => 0,
        'manual_existing_count' => 0,
        'blocked_slot_count' => 2,
        'blocked_unique_count' => 2,
        'blocked_readiness_counts' => [
            'missing_lecturer_count' => 1,
            'missing_hall_count' => 1,
            'missing_lecturer_only' => 1,
            'missing_hall_only' => 1,
            'missing_both' => 0,
        ],
        'conflict_count' => 0,
        'prerequisite_errors' => ['missing_teaching_dates'],
        'blocked_slots' => [
            ['reasons' => ['missing_lecturer_identity', 'missing_active_lecturer_login']],
            ['reasons' => ['missing_hall']],
        ],
        'conflicts' => [],
        'structural_readiness' => [],
    ];
    $groups = generationPreviewIssueGroups($preview);

    $html = view('filament.components.lecture-session-generation-preview', [
        'preview' => $preview,
        'issues' => [],
        'issueGroups' => $groups,
        'termSetupUrl' => '/admin/current-academic-term',
        'issuesUrl' => '/admin/schedule-import-issues?batch=7',
        'accountPreparationUrl' => '/admin/lecturer-account-preparation',
    ])->render();

    expect($groups)->toHaveKeys(['term_configuration', 'schedule_data', 'accounts'])
        ->and($html)->toContain('لم يتم تحديد تاريخ بداية ونهاية التدريس للفصل الدراسي الحالي.')
        ->toContain('تحديد تواريخ التدريس')
        ->toContain('/admin/current-academic-term')
        ->toContain('معالجة مشكلات البرنامج')
        ->toContain('/admin/schedule-import-issues?batch=7')
        ->toContain('تهيئة حسابات المدرسين')
        ->toContain('/admin/lecturer-account-preparation');
});

it('does not route a teaching-date-only issue to schedule import issues', function (): void {
    app()->setLocale('ar');

    $preview = [
        'source_slot_count' => 1,
        'candidate_session_count' => 0,
        'to_create_count' => 0,
        'already_existing_count' => 0,
        'manual_existing_count' => 0,
        'blocked_slot_count' => 0,
        'blocked_unique_count' => 0,
        'blocked_readiness_counts' => [],
        'conflict_count' => 0,
        'prerequisite_errors' => ['missing_teaching_dates'],
        'blocked_slots' => [],
        'conflicts' => [],
        'structural_readiness' => [],
    ];
    $groups = generationPreviewIssueGroups($preview);

    $html = view('filament.components.lecture-session-generation-preview', [
        'preview' => $preview,
        'issues' => [],
        'issueGroups' => $groups,
        'termSetupUrl' => '/admin/current-academic-term',
        'issuesUrl' => '/admin/schedule-import-issues?batch=7',
        'accountPreparationUrl' => '/admin/lecturer-account-preparation',
    ])->render();

    expect($groups)->toHaveKey('term_configuration')
        ->not->toHaveKey('schedule_data')
        ->and($html)->toContain('تحديد تواريخ التدريس')
        ->not->toContain('معالجة مشكلات البرنامج')
        ->not->toContain('/admin/schedule-import-issues?batch=7');
});

it('routes an actual schedule data problem to schedule import issues', function (string $reason): void {
    app()->setLocale('ar');

    $preview = [
        'source_slot_count' => 1,
        'candidate_session_count' => 0,
        'to_create_count' => 0,
        'already_existing_count' => 0,
        'manual_existing_count' => 0,
        'blocked_slot_count' => 1,
        'blocked_unique_count' => 1,
        'blocked_readiness_counts' => [],
        'conflict_count' => 0,
        'prerequisite_errors' => [],
        'blocked_slots' => [['reasons' => [$reason]]],
        'conflicts' => [],
        'structural_readiness' => [],
    ];
    $groups = generationPreviewIssueGroups($preview);

    $html = view('filament.components.lecture-session-generation-preview', [
        'preview' => $preview,
        'issues' => [],
        'issueGroups' => $groups,
        'termSetupUrl' => '/admin/current-academic-term',
        'issuesUrl' => '/admin/schedule-import-issues?batch=7',
        'accountPreparationUrl' => '/admin/lecturer-account-preparation',
    ])->render();

    expect($groups)->toHaveKey('schedule_data')
        ->and($html)->toContain('معالجة مشكلات البرنامج')
        ->toContain('/admin/schedule-import-issues?batch=7')
        ->not->toContain('تحديد تواريخ التدريس');
})->with([
    'missing lecturer' => 'missing_lecturer_identity',
    'missing hall' => 'missing_hall',
]);

it('defines every generation preview reason in Arabic and English', function (): void {
    $reasons = [
        'missing_teaching_dates', 'invalid_teaching_date_range', 'missing_completed_enrollment_batch',
        'missing_completed_weekly_schedule_batch', 'missing_weekly_schedule_slots', 'missing_lecturer_identity',
        'missing_active_lecturer_login', 'missing_course_lecturer_role', 'missing_hall', 'invalid_subject_section',
        'invalid_weekday', 'invalid_time_range', 'persisted_session_overlap', 'weekly_schedule_overlap',
        'scheduling_conflict', 'source_date_already_generated', 'matching_manual_session_exists',
        'excluded_from_weekly_schedule', 'unexpected_item_failure',
    ];

    foreach (['ar', 'en'] as $locale) {
        app()->setLocale($locale);

        foreach ($reasons as $reason) {
            expect(__('lecture-session.lecture_generation.reasons.'.$reason))->not->toBe('lecture-session.lecture_generation.reasons.'.$reason);
        }

        foreach (['complete', 'unassigned_lecturer', 'unassigned_hall', 'both_unassigned', 'total'] as $key) {
            expect(__('lecture-session.lecture_generation.ready_breakdown.'.$key, ['count' => 2]))
                ->not->toBe('lecture-session.lecture_generation.ready_breakdown.'.$key);
        }
    }
});
