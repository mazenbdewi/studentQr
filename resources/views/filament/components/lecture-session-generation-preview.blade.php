<div class="space-y-4" dir="rtl">
    @if (! is_array($preview))
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
            {{ __('lecture-session.weekly_generation_preview_empty') }}
        </div>
    @else
        @php
            $toCreate = (int) ($preview['to_create_count'] ?? 0);
            $alreadyExists = (int) ($preview['already_existing_count'] ?? 0) + (int) ($preview['manual_existing_count'] ?? 0);
            $blocked = (int) ($preview['blocked_slot_count'] ?? 0);
            $blockedUnique = (int) ($preview['blocked_unique_count'] ?? $blocked);
            $conflicts = (int) ($preview['conflict_count'] ?? 0);
            $sourceSlots = (int) ($preview['source_slot_count'] ?? 0);
            $readiness = $preview['structural_readiness'] ?? [];
            $blockedReadiness = $preview['blocked_readiness_counts'] ?? [];
            $hasReadinessIssues = $blockedUnique > 0;
            $hasIssues = $hasReadinessIssues || $issues !== [];
        @endphp

        <x-filament::section :heading="__('lecture-session.lecture_generation.main.title')" icon="heroicon-o-sparkles">
            <div class="space-y-4">
                <div class="text-sm font-semibold text-gray-950 dark:text-white">
                    @if ($sourceSlots === 0)
                        {{ __('lecture-session.lecture_generation.main.no_source_slots') }}
                    @elseif ($toCreate > 0)
                        {{ __('lecture-session.lecture_generation.main.can_create', ['count' => $toCreate]) }}
                        <p class="mt-1 font-normal text-gray-600 dark:text-gray-300">
                            {{ __('lecture-session.lecture_generation.main.already_exists', ['count' => $alreadyExists]) }}
                        </p>
                    @elseif ($alreadyExists > 0 && $blocked === 0 && $conflicts === 0)
                        {{ __('lecture-session.lecture_generation.main.all_already_exists') }}
                    @else
                        {{ __('lecture-session.lecture_generation.main.none_ready') }}
                        <p class="mt-1 font-normal text-gray-600 dark:text-gray-300">{{ __('lecture-session.lecture_generation.main.resolve_issues') }}</p>
                    @endif
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10"><div class="text-sm text-emerald-700 dark:text-emerald-300">{{ __('lecture-session.lecture_generation.summary.to_create') }}</div><div class="mt-1 text-2xl font-bold text-emerald-900 dark:text-emerald-100">{{ $toCreate }}</div></div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5"><div class="text-sm text-gray-600 dark:text-gray-300">{{ __('lecture-session.lecture_generation.summary.already_exists') }}</div><div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $alreadyExists }}</div></div>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10"><div class="text-sm text-amber-700 dark:text-amber-300">{{ __('lecture-session.lecture_generation.summary.not_ready_slots') }}</div><div class="mt-1 text-2xl font-bold text-amber-900 dark:text-amber-100">{{ $blockedUnique }}</div></div>
                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-500/30 dark:bg-rose-500/10"><div class="text-sm text-rose-700 dark:text-rose-300">{{ __('lecture-session.lecture_generation.summary.conflicts') }}</div><div class="mt-1 text-2xl font-bold text-rose-900 dark:text-rose-100">{{ $conflicts }}</div></div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section :heading="__('lecture-session.lecture_generation.issues.title')" icon="heroicon-o-exclamation-triangle" :icon-color="$hasIssues ? 'danger' : 'success'">
            @if (! $hasIssues)
                <div class="flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-300"><x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5" />{{ __('lecture-session.lecture_generation.issues.none') }}</div>
            @else
                <ul class="space-y-2">
                    @if ($hasReadinessIssues)
                        <li class="text-sm font-semibold text-amber-800 dark:text-amber-200">{{ __('lecture-session.lecture_generation.issues.not_ready_unique', ['count' => $blockedUnique]) }}</li>
                        @if ((int) ($blockedReadiness['missing_lecturer_count'] ?? 0) > 0)
                            <li class="flex items-center gap-2 text-sm text-rose-800 dark:text-rose-200"><x-filament::icon icon="heroicon-o-x-circle" class="h-5 w-5 shrink-0" />{{ __('lecture-session.lecture_generation.issues.missing_lecturer', ['count' => $blockedReadiness['missing_lecturer_count']]) }}</li>
                        @endif
                        @if ((int) ($blockedReadiness['missing_hall_count'] ?? 0) > 0)
                            <li class="flex items-center gap-2 text-sm text-rose-800 dark:text-rose-200"><x-filament::icon icon="heroicon-o-x-circle" class="h-5 w-5 shrink-0" />{{ __('lecture-session.lecture_generation.issues.missing_hall', ['count' => $blockedReadiness['missing_hall_count']]) }}</li>
                        @endif
                        @if ((int) ($blockedReadiness['missing_lecturer_only'] ?? 0) > 0 || (int) ($blockedReadiness['missing_hall_only'] ?? 0) > 0 || (int) ($blockedReadiness['missing_both'] ?? 0) > 0)
                            <li class="mr-7 text-xs text-gray-600 dark:text-gray-300">
                                {{ __('lecture-session.lecture_generation.issues.breakdown', ['lecturer_only' => $blockedReadiness['missing_lecturer_only'] ?? 0, 'hall_only' => $blockedReadiness['missing_hall_only'] ?? 0, 'both' => $blockedReadiness['missing_both'] ?? 0]) }}
                            </li>
                        @endif
                    @endif
                    @foreach ($issues as $issue)
                        <li class="flex items-center gap-2 text-sm text-rose-800 dark:text-rose-200"><x-filament::icon icon="heroicon-o-x-circle" class="h-5 w-5 shrink-0" />{{ __('lecture-session.lecture_generation.issues.counted', ['count' => $issue['count'], 'reason' => $issue['label']]) }}</li>
                    @endforeach
                </ul>
                @if ($hasReadinessIssues)
                    <p class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-100">{{ __('lecture-session.lecture_generation.issues.unique_note') }}</p>
                @endif
            @endif

            @if ($hasIssues && filled($issuesUrl ?? null))
                <div class="mt-4"><x-filament::button tag="a" :href="$issuesUrl" color="warning" icon="heroicon-o-wrench-screwdriver">{{ __('lecture-session.lecture_generation.issues.manage', ['count' => $blockedUnique]) }}</x-filament::button></div>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('lecture-session.lecture_generation.details.title')" icon="heroicon-o-clipboard-document-check" collapsible collapsed>
            <ul class="space-y-2 text-sm">
                @foreach ([
                    ['success', 'valid_subject_sections', 'valid_subject_and_section'], ['success', 'with_lecturer', 'slots_with_lecturer_identity'], ['success', 'with_valid_account_role', 'slots_with_valid_linked_lecturer_account_and_role'], ['success', 'with_hall', 'slots_with_halls'], ['success', 'ready', 'ready_slots'],
                    ['danger', 'without_lecturer', 'slots_without_lecturer_identity'], ['danger', 'without_hall', 'slots_without_halls'], ['danger', 'not_ready', 'blocked_slots'],
                ] as [$tone, $label, $key])
                    <li @class(['flex items-center gap-2', 'text-emerald-700 dark:text-emerald-300' => $tone === 'success', 'text-rose-800 dark:text-rose-200' => $tone === 'danger'])><x-filament::icon :icon="$tone === 'success' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'" class="h-5 w-5 shrink-0" />{{ __('lecture-session.lecture_generation.readiness.'.$label) }}: {{ (int) ($readiness[$key] ?? 0) }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
</div>
