<x-filament-panels::page dir="rtl">
    <div class="rounded-xl border border-warning-300 bg-warning-50 p-4 text-warning-900 shadow-sm dark:border-warning-600 dark:bg-warning-950/30 dark:text-warning-100">
        يحتوي هذا الملف على كلمات مرور مؤقتة حساسة. يجب حفظه وتوزيعه بطريقة آمنة ثم حذفه بعد انتهاء الحاجة إليه.
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
        <table class="w-full min-w-[900px] table-fixed text-right text-sm">
            <thead class="bg-gray-50 text-gray-700 dark:bg-white/5 dark:text-gray-200">
                <tr>
                    <th class="w-40 px-4 py-3">نوع الدفعة</th><th class="w-36 px-4 py-3">تاريخ الإنشاء</th><th class="w-24 px-4 py-3">عدد الحسابات</th><th class="min-w-72 px-4 py-3">اسم الملف</th><th class="w-32 px-4 py-3">الحالة</th><th class="w-28 px-4 py-3">عدد مرات التنزيل</th><th class="sticky left-0 z-10 w-56 bg-gray-50 px-4 py-3 dark:bg-gray-900">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @forelse($this->batches() as $batch)
                    <tr class="bg-white dark:bg-gray-900">
                        <td class="px-4 py-3 font-medium">{{ $this->batchTypeLabel($batch->batch_type) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $batch->generated_at?->format('Y/m/d H:i') }}</td>
                        <td class="px-4 py-3">{{ $batch->record_count }}</td>
                        <td class="px-4 py-3"><span class="block truncate" title="{{ $batch->original_filename }}">{{ $batch->original_filename }}</span></td>
                        <td class="px-4 py-3"><span class="rounded-full bg-success-50 px-2 py-1 text-success-700 dark:bg-success-500/10 dark:text-success-300">{{ $this->batchStatusLabel($batch->status) }}</span></td>
                        <td class="px-4 py-3">{{ $batch->downloaded_count }}</td>
                        <td class="sticky left-0 z-10 bg-white px-4 py-3 dark:bg-gray-900">
                            <div class="flex flex-nowrap gap-2">
                                @if($batch->status !== 'deleted' && $batch->encrypted_file_path && auth()->user()?->can('download lecturer credential batches'))
                                    <button type="button" wire:click="download({{ $batch->id }})" class="fi-btn fi-btn-color-primary inline-flex items-center gap-1 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium"><x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-4 w-4" />تنزيل الملف</button>
                                @endif
                                <button type="button" wire:click="openDetails({{ $batch->id }})" class="fi-btn inline-flex items-center whitespace-nowrap rounded-lg border px-3 py-2 text-sm">تفاصيل الدفعة</button>
                                @if($batch->status !== 'deleted' && auth()->user()?->can('delete lecturer credential batches'))
                                    <button type="button" wire:click="secureDelete({{ $batch->id }})" wire:confirm="سيتم حذف الملف المشفر نهائيًا مع الاحتفاظ ببيانات التدقيق. لن تتأثر حسابات المحاضرين أو كلمات مرورهم." class="fi-btn inline-flex items-center whitespace-nowrap rounded-lg bg-danger-600 px-3 py-2 text-sm text-white">حذف آمن</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">لا توجد دفعات بيانات دخول.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($details = $this->detailsBatch())
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between"><h2 class="text-lg font-bold">تفاصيل الدفعة</h2><button wire:click="closeDetails" class="text-gray-500">إغلاق</button></div>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2"><div><dt>المعرف الداخلي</dt><dd class="break-all text-sm">{{ $details->uuid }}</dd></div><div><dt>SHA-256</dt><dd class="break-all text-sm">{{ $details->sha256 }}</dd></div><div><dt>إصدار مفتاح التشفير</dt><dd>{{ $details->encryption_key_version }}</dd></div><div><dt>منشئ الدفعة</dt><dd>{{ $details->generatedBy?->name ?? 'غير متاح' }}</dd></div><div><dt>الفصل الدراسي</dt><dd>{{ $details->academicTerm?->display_name ?? 'غير متاح' }}</dd></div><div><dt>آخر تنزيل</dt><dd>{{ $details->last_downloaded_at?->format('Y/m/d H:i') ?? 'لم يتم التنزيل' }}</dd></div></dl>
            </div>
        </div>
    @endif
</x-filament-panels::page>
