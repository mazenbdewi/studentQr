<div class="space-y-3 text-sm">
    @forelse ($actions as $action)
        @php
            $notApplicableOutcome = collect(data_get($action->result, 'not_applicable_issue_outcomes', []))
                ->firstWhere('issue_type', $action->issue?->issue_type);
        @endphp
        <article class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <div class="flex flex-wrap justify-between gap-2">
                <span class="font-semibold text-gray-950 dark:text-white">{{ __('schedule-import-reconciliation.actions.'.$action->action) }}</span>
                <span class="text-xs text-gray-500">{{ $action->performed_at?->format('Y-m-d H:i:s') }}</span>
            </div>
            <div class="mt-2 text-gray-700 dark:text-gray-200">
                {{ $action->actor?->name ?? data_get($action->new_state, 'actor.name', __('hall.not_specified')) }}:
                {{ $action->previous_status }} → {{ $action->new_status }}
            </div>
            @if ($action->issue)
                <div class="mt-2 text-gray-700 dark:text-gray-200">
                    {{ __('schedule-import-reconciliation.fields.issue_type') }}: {{ \App\Models\ScheduleImportIssue::label($action->issue->issue_type) }}
                </div>
            @endif
            @if ($notApplicableOutcome)
                <div class="mt-2 text-gray-700 dark:text-gray-200">
                    {{ __('schedule-import-reconciliation.fields.resolution_outcome') }}: {{ $notApplicableOutcome['outcome'] }}
                    — {{ $notApplicableOutcome['reason'] }}
                </div>
            @endif
            @if ($action->note)<div class="mt-2">{{ $action->note }}</div>@endif
            @if ($action->result)<pre class="mt-2 overflow-auto rounded bg-gray-100 p-2 text-xs dark:bg-gray-900">{{ json_encode($action->result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>@endif
        </article>
    @empty
        <div class="text-gray-500">{{ __('schedule-import-reconciliation.fields.actions_history') }}: 0</div>
    @endforelse
</div>
