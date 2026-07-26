@php
    $resolutionContext = app(\App\Services\ScheduleImportRowResolutionContext::class);
    $effectiveSubject = $resolutionContext->effectiveSubject($row);
    $effectiveSection = $resolutionContext->effectiveSubjectSection($row);
    $effectiveLecturer = $resolutionContext->effectiveLecturer($row);
    $effectiveHall = $resolutionContext->effectiveHall($row);
    $lecturerResolution = $resolutionContext->effectiveLecturerResolution($row);
    $hallResolution = $resolutionContext->effectiveHallResolution($row);
@endphp

<div class="space-y-5 text-sm">
    <section>
        <h3 class="font-semibold text-gray-950 dark:text-white">{{ __('schedule-import-reconciliation.fields.source_values') }}</h3>
        <dl class="mt-2 grid gap-2 sm:grid-cols-2">
            @foreach (($row->source_payload ?? []) as $key => $value)
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $key }}</dt>
                    <dd class="mt-1 break-words text-gray-950 dark:text-white">{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : ($value ?? __('hall.not_specified')) }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    @if ($row->isExcludedFromWeeklySchedule())
        <section class="rounded-lg border border-warning-300 bg-warning-50 p-4 dark:border-warning-500/40 dark:bg-warning-500/10">
            <h3 class="font-semibold text-warning-900 dark:text-warning-100">{{ __('schedule-import-reconciliation.statuses.excluded_from_batch_schedule') }}</h3>
            <p class="mt-2 text-warning-800 dark:text-warning-200">{{ __('schedule-import-reconciliation.exclusion.explanation') }}</p>
            <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                <div><dt class="font-medium">{{ __('schedule-import-reconciliation.fields.exclusion_reason') }}</dt><dd>{{ $row->exclusion_note }}</dd></div>
                <div><dt class="font-medium">{{ __('schedule-import-reconciliation.fields.excluded_by') }}</dt><dd>{{ $row->excludedFromWeeklyScheduleBy?->name ?? __('hall.not_specified') }}</dd></div>
                <div><dt class="font-medium">{{ __('schedule-import-reconciliation.fields.excluded_at') }}</dt><dd>{{ $row->excluded_from_weekly_schedule_at?->format('Y-m-d H:i:s') }}</dd></div>
            </dl>
        </section>
    @endif

    @if ($row->issues?->isNotEmpty())
        <section>
            <h3 class="font-semibold text-gray-950 dark:text-white">{{ __('schedule-import-reconciliation.fields.issue_type') }}</h3>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($row->issues->pluck('issue_type')->unique() as $issueType)
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-gray-800 dark:bg-gray-800 dark:text-gray-100">{{ \App\Models\ScheduleImportIssue::label($issueType) }}</span>
                @endforeach
            </div>
        </section>
    @endif

    <section>
        <h3 class="font-semibold text-gray-950 dark:text-white">{{ __('schedule-import-reconciliation.fields.canonical_resolution') }}</h3>
        <div class="mt-2 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
            {{ $effectiveSubject?->code }} {{ $effectiveSubject?->name }}
            @if ($effectiveSection) — {{ $effectiveSection->code }} @endif
            @if ($effectiveLecturer) — {{ $effectiveLecturer->name }} ({{ __('schedule-import-reconciliation.identity_sources.'.$lecturerResolution['source']) }}) @endif
            @if ($effectiveHall) — {{ $effectiveHall->name }} ({{ __('schedule-import-reconciliation.identity_sources.'.$hallResolution['source']) }}) @endif
        </div>
    </section>

    @if ($row->timeOverrides?->isNotEmpty())
        <section>
            <h3 class="font-semibold text-gray-950 dark:text-white">{{ __('schedule-import-reconciliation.fields.time_overrides') }}</h3>
            <ul class="mt-2 space-y-2">
                @foreach ($row->timeOverrides as $override)
                    <li class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        {{ __('weekly-schedule.weekdays')[$override->weekday] ?? $override->weekday }} — {{ substr($override->start_time, 0, 5) }}–{{ substr($override->end_time, 0, 5) }}
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @isset($relatedSlots)
        @if ($relatedSlots->isNotEmpty())
            <section>
                <h3 class="font-semibold text-gray-950 dark:text-white">{{ __('schedule-import-reconciliation.fields.related_schedule_slots') }}</h3>
                <div class="mt-2 space-y-2">
                    @foreach ($relatedSlots as $slot)
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            #{{ $slot->id }} — {{ $slot->subject?->code }} / {{ $slot->subjectSection?->code }} —
                            {{ __('weekly-schedule.weekdays')[$slot->weekday] ?? $slot->weekday }}
                            {{ substr($slot->start_time, 0, 5) }}–{{ substr($slot->end_time, 0, 5) }} —
                            {{ $slot->lecturer?->name ?? __('hall.not_specified') }} —
                            {{ $slot->hall?->name ?? __('hall.not_specified') }}
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endisset

    @isset($conflictingRows)
        <section>
            <h3 class="font-semibold text-gray-950 dark:text-white">{{ __('schedule-import-reconciliation.actions.resolve_conflict') }}</h3>
            <div class="mt-2 space-y-2">
                @foreach ($conflictingRows as $conflictingRow)
                    <div class="rounded-lg border border-warning-300 bg-warning-50 p-3 dark:border-warning-700 dark:bg-warning-950">
                        {{ __('schedule-import-reconciliation.fields.row') }}: {{ $conflictingRow->source_row_number }} — {{ json_encode($conflictingRow->source_payload, JSON_UNESCAPED_UNICODE) }}
                    </div>
                @endforeach
            </div>
        </section>
    @endisset
</div>
