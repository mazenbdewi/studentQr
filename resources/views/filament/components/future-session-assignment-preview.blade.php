@php
    $conflicts = $preview['conflicts'] ?? [];
@endphp

<div class="space-y-4" dir="rtl">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            'eligible_future_count' => 'eligible',
            'skipped_past_count' => 'past',
            'skipped_completed_count' => 'completed',
            'skipped_started_count' => 'started',
            'skipped_attendance_count' => 'attendance',
            'skipped_activity_count' => 'activity',
            'conflict_count' => 'conflicts',
        ] as $key => $label)
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5">
                <div class="text-xs text-gray-600 dark:text-gray-300">{{ __('weekly-schedule.future_assignment.preview.'.$label) }}</div>
                <div class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ (int) ($preview[$key] ?? 0) }}</div>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-gray-200 p-4 text-sm dark:border-white/10">
        @if (($preview['lecturer_from_names'] ?? []) !== [])
            <div>
                {{ __('weekly-schedule.future_assignment.preview.lecturer_change') }}:
                <strong>{{ implode('، ', $preview['lecturer_from_names']) }}</strong>
                <span aria-hidden="true">←</span>
                <strong>{{ $preview['target_lecturer_name'] ?? __('weekly-schedule.not_specified') }}</strong>
            </div>
        @endif
        @if (($preview['hall_from_names'] ?? []) !== [])
            <div @class(['mt-2' => ($preview['lecturer_from_names'] ?? []) !== []])>
                {{ __('weekly-schedule.future_assignment.preview.hall_change') }}:
                <strong>{{ implode('، ', $preview['hall_from_names']) }}</strong>
                <span aria-hidden="true">←</span>
                <strong>{{ $preview['target_hall_name'] ?? __('weekly-schedule.not_specified') }}</strong>
            </div>
        @endif
    </div>

    @if ($preview['lecturer_account_missing'] ?? false)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
            {{ __('weekly-schedule.future_assignment.account_warning') }}
        </div>
    @endif

    @if ($conflicts !== [])
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100">
            <div class="font-bold">{{ __('weekly-schedule.future_assignment.preview.conflict_warning') }}</div>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach ($conflicts as $conflict)
                    <li>{{ __('weekly-schedule.future_assignment.preview.conflict_item', ['session' => $conflict['session_id'], 'other' => $conflict['conflicting_session_id'], 'type' => __('weekly-schedule.future_assignment.conflict_types.'.$conflict['type'])]) }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
