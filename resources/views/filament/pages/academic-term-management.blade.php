<x-filament-panels::page>
    <div class="p-4 border rounded-lg border-warning-300 bg-warning-50 text-warning-900 dark:bg-warning-950 dark:text-warning-100">
        سيتم إخفاء بيانات الفصل النشط من صفحات التشغيل اليومية، لكنها ستبقى محفوظة في الأرشيف ولن يتم حذف حسابات المستخدمين أو تغيير كلمات مرورهم.
    </div>
    <div class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700">
        <table class="w-full text-sm text-right">
            <thead class="bg-gray-50 dark:bg-gray-900"><tr><th class="p-3">الفصل الدراسي</th><th class="p-3">الحالة</th><th class="p-3"></th></tr></thead>
            <tbody>
                @foreach ($this->terms() as $term)
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="p-3">{{ $term->display_name }}</td>
                        <td class="p-3">{{ $this->currentTerm()?->is($term) ? 'الفصل الدراسي النشط' : ($term->is_archived ? 'مؤرشف' : 'غير نشط') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
