<x-filament-panels::page>
    <div class="mx-auto w-full max-w-5xl space-y-6" dir="rtl">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="p-4">
                {{ $this->form }}
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-white/10">
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">
                    {{ __('student.attendance_history') }}
                </h3>
            </div>

            <div class="p-6">
                {{ $this->table }}
            </div>
        </section>
    </div>
</x-filament-panels::page>
