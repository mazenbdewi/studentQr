<a
    href="{{ $url }}"
    @class([
        'hidden items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-medium transition hover:opacity-80 sm:flex',
        'border-success-300 bg-success-50 text-success-700 dark:border-success-500/40 dark:bg-success-500/10 dark:text-success-300' => $academicTerm,
        'border-warning-300 bg-warning-50 text-warning-700 dark:border-warning-500/40 dark:bg-warning-500/10 dark:text-warning-300' => ! $academicTerm,
    ])
>
    <x-filament::icon icon="heroicon-o-academic-cap" class="h-5 w-5" />
    <span>
        {{ __('academic-term.current_label') }}:
        {{ $academicTerm?->display_name ?? __('academic-term.no_current_short') }}
    </span>
</a>
