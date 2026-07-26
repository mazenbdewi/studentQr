<div
    wire:loading
    wire:target="callMountedAction"
    class="absolute inset-0 z-20 flex min-h-full items-center justify-center rounded-xl bg-white/95 p-8 text-right dark:bg-gray-950/95"
    dir="rtl"
    role="status"
    aria-live="assertive"
>
    <div class="w-full max-w-lg space-y-5">
        <div class="mx-auto h-16 w-16 animate-spin rounded-full border-4 border-primary-200 border-t-primary-600 dark:border-primary-900 dark:border-t-primary-400"></div>

        <div class="space-y-2 text-center">
            <div class="text-xl font-bold text-gray-950 dark:text-white">جارٍ توليد جلسات المحاضرات...</div>
            <p class="text-sm text-gray-700 dark:text-gray-300">يتم الآن معالجة الجلسات الجاهزة اعتمادًا على البرنامج الأسبوعي.</p>
            <p class="text-sm font-semibold text-primary-700 dark:text-primary-300">جارٍ تجهيز {{ number_format($readySessionCount) }} جلسة.</p>
            <p class="text-sm text-gray-700 dark:text-gray-300">يرجى الانتظار وعدم إغلاق الصفحة حتى انتهاء العملية.</p>
        </div>

        <div class="h-3 overflow-hidden rounded-full bg-primary-100 dark:bg-primary-950" aria-label="جارٍ تنفيذ العملية">
            <div class="h-full w-1/2 animate-pulse rounded-full bg-primary-600"></div>
        </div>

        <p class="rounded-lg bg-amber-50 p-3 text-center text-xs text-amber-900 dark:bg-amber-950/50 dark:text-amber-100" role="alert">
            عملية توليد الجلسات قيد التنفيذ. مغادرة الصفحة قد تمنع عرض النتيجة، لكنها لا تعني بالضرورة توقف العملية.
        </p>
    </div>
</div>
