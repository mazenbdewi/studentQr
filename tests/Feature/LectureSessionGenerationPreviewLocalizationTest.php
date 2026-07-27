<?php

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
        ->toContain('يمكن إنشاء 10 جلسة جديدة.')
        ->toContain('يوجد 3 جلسة منشأة مسبقًا ولن يتم تكرارها.')
        ->toContain('المشكلات التي تحتاج إلى معالجة')
        ->toContain('الخانات غير الجاهزة للتوليد: 1')
        ->toContain('قد تحتوي الخانة الواحدة على أكثر من مشكلة')
        ->toContain('عرض تفاصيل التحقق')
        ->toContain('لم يتم تحديد المدرّس')
        ->toContain('لم يتم تحديد القاعة')
        ->toContain('يوجد تعارض في الجدول الأسبوعي')
        ->not->toContain('missing_lecturer_identity')
        ->not->toContain('missing_hall')
        ->not->toContain('weekly_schedule_overlap')
        ->not->toContain('خانات محجوبة')
        ->not->toContain('لا توجد جلسات جديدة جاهزة للتوليد حاليًا');

    expect($view)
        ->toContain('heroicon-o-check-circle')
        ->toContain('heroicon-o-x-circle')
        ->toContain('grid gap-3');
});

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
    }
});
