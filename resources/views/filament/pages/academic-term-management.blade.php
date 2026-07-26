<x-filament-panels::page>
    <div class="rounded-lg border border-warning-300 bg-warning-50 p-4 text-warning-900 dark:bg-warning-950 dark:text-warning-100">
        سيتم إخفاء بيانات الفصل الحالي من صفحات التشغيل اليومية، لكنها ستبقى محفوظة في الأرشيف ولن يتم حذف حسابات المستخدمين أو تغيير كلمات مرورهم.
    </div>
    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-right text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900"><tr><th class="p-3">الفصل الدراسي</th><th class="p-3">الحالة</th><th class="p-3"></th></tr></thead>
            <tbody>
                @foreach ($this->terms() as $term)
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="p-3">{{ $term->display_name }}</td>
                        <td class="p-3">{{ $this->currentTerm()?->is($term) ? 'الفصل الدراسي الحالي' : ($term->is_archived ? 'مؤرشف' : 'غير حالي') }}</td>
                        <td class="p-3">
                            @if (! $this->currentTerm()?->is($term))
                                <x-filament::button wire:click="mountAction('activate', { term: {{ $term->id }} })">تعيين هذا الفصل كفصل دراسي حالي</x-filament::button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
