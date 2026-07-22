<x-filament-panels::page>
    @php($report = $this->report())
    @php($summary = $report['summary'])
    @php($rows = $report['rows'])
    @php($selectedConflicts = $this->selectedConflicts())

    <div class="space-y-6" dir="rtl">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">خانات دون مدرس</div>
                <div class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">{{ $summary['without_lecturer'] }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">خانات دون قاعة</div>
                <div class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">{{ $summary['without_hall'] }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">خانات تحمل أكثر من مشكلة</div>
                <div class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">{{ $summary['multi_issue'] }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">خانات مشاركة في تعارض</div>
                <div class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">{{ $summary['conflict_participants'] }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">إجمالي الخانات الفريدة المتأثرة</div>
                <div class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">{{ $summary['unique_affected_slots'] }}</div>
            </div>
        </div>

        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
            عدد صفوف تقرير الأخطاء: {{ $summary['error_report_rows'] }}.
            عدد الخانات المحجوبة في نتيجة التوليد: {{ $summary['generation_blocked_slots'] }}.
            عدد الصفوف أدناه يمثل الخانات الفريدة المتأثرة وليس عدد صفوف الأخطاء.
        </div>

        @if ($selectedConflicts !== [])
            <div class="rounded-xl border border-primary-200 bg-white p-4 shadow-sm dark:border-primary-500/30 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <h2 class="text-lg font-bold text-gray-950 dark:text-white">
                        تفاصيل التعارض للموعد الأسبوعي #{{ $selectedSlotId }}
                    </h2>
                    <x-filament::button color="gray" wire:click="closeConflictDetails">إغلاق</x-filament::button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead>
                        <tr class="bg-gray-50 text-right dark:bg-gray-800">
                            <th class="px-3 py-2">Source slot</th>
                            <th class="px-3 py-2">Conflicting slot</th>
                            <th class="px-3 py-2">Subject/section المصدر</th>
                            <th class="px-3 py-2">Subject/section المتعارض</th>
                            <th class="px-3 py-2">Lecturer المصدر</th>
                            <th class="px-3 py-2">Lecturer المتعارض</th>
                            <th class="px-3 py-2">Hall المصدر</th>
                            <th class="px-3 py-2">Hall المتعارض</th>
                            <th class="px-3 py-2">Weekday</th>
                            <th class="px-3 py-2">Full times</th>
                            <th class="px-3 py-2">Actual overlap</th>
                            <th class="px-3 py-2">Conflict dimension</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($selectedConflicts as $conflict)
                            <tr>
                                <td class="px-3 py-2">{{ $conflict['source_slot_id'] }}</td>
                                <td class="px-3 py-2">{{ $conflict['conflicting_source_slot_id'] }}</td>
                                <td class="px-3 py-2">{{ $conflict['source_subject_section'] }}</td>
                                <td class="px-3 py-2">{{ $conflict['conflicting_subject_section'] }}</td>
                                <td class="px-3 py-2">{{ $conflict['source_lecturer'] }}</td>
                                <td class="px-3 py-2">{{ $conflict['conflicting_lecturer'] }}</td>
                                <td class="px-3 py-2">{{ $conflict['source_hall'] }}</td>
                                <td class="px-3 py-2">{{ $conflict['conflicting_hall'] }}</td>
                                <td class="px-3 py-2">{{ $conflict['weekday'] }}</td>
                                <td class="px-3 py-2">
                                    {{ $conflict['source_start_time'] }} - {{ $conflict['source_end_time'] }}
                                    /
                                    {{ $conflict['conflicting_start_time'] }} - {{ $conflict['conflicting_end_time'] }}
                                </td>
                                <td class="px-3 py-2">{{ $conflict['actual_overlap_interval'] }}</td>
                                <td class="px-3 py-2">{{ $conflict['conflict_dimension'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead>
                <tr class="bg-gray-50 text-right dark:bg-gray-800">
                    <th class="px-3 py-2">رقم الموعد الأسبوعي</th>
                    <th class="px-3 py-2">المادة</th>
                    <th class="px-3 py-2">الشعبة</th>
                    <th class="px-3 py-2">المدرس</th>
                    <th class="px-3 py-2">القاعة</th>
                    <th class="px-3 py-2">اليوم</th>
                    <th class="px-3 py-2">وقت البداية</th>
                    <th class="px-3 py-2">وقت النهاية</th>
                    <th class="px-3 py-2">المشكلات</th>
                    <th class="px-3 py-2">عدد الجلسات المتأثرة</th>
                    <th class="px-3 py-2">الإجراء المقترح</th>
                    <th class="px-3 py-2">تفاصيل</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-3 py-2 font-mono">{{ $row['رقم الموعد الأسبوعي'] }}</td>
                        <td class="px-3 py-2">{{ $row['المادة'] }}</td>
                        <td class="px-3 py-2">{{ $row['الشعبة'] }}</td>
                        <td class="px-3 py-2">{{ $row['المدرس'] }}</td>
                        <td class="px-3 py-2">{{ $row['القاعة'] }}</td>
                        <td class="px-3 py-2">{{ $row['اليوم'] }}</td>
                        <td class="px-3 py-2">{{ $row['وقت البداية'] }}</td>
                        <td class="px-3 py-2">{{ $row['وقت النهاية'] }}</td>
                        <td class="px-3 py-2">{{ $row['المشكلات'] }}</td>
                        <td class="px-3 py-2">{{ $row['عدد الجلسات المتأثرة'] }}</td>
                        <td class="px-3 py-2">{{ $row['الإجراء المقترح'] }}</td>
                        <td class="px-3 py-2">
                            @if (collect($row['رموز المشكلات'])->intersect(['weekly_schedule_overlap', 'scheduling_conflict'])->isNotEmpty())
                                <x-filament::button size="sm" color="gray" wire:click="showConflictDetails({{ $row['رقم الموعد الأسبوعي'] }})">
                                    عرض التعارض
                                </x-filament::button>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="px-3 py-8 text-center text-gray-500">لا توجد خانات محجوبة في آخر عملية توليد.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
