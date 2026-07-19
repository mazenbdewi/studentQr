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

    <section>
        <h3 class="font-semibold text-gray-950 dark:text-white">{{ __('schedule-import-reconciliation.fields.canonical_resolution') }}</h3>
        <div class="mt-2 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
            {{ $row->resolvedSubject?->code }} {{ $row->resolvedSubject?->name }}
            @if ($row->resolvedSubjectSection) — {{ $row->resolvedSubjectSection->code }} @endif
            @if ($row->resolvedLecturer) — {{ $row->resolvedLecturer->name }} @endif
            @if ($row->resolvedHall) — {{ $row->resolvedHall->name }} @endif
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
