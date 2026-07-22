<x-filament-panels::page>
    @php($report = $this->report())
    @php($summary = $report['summary'])
    @php($rows = $this->filteredRows())
    @php($groups = $this->savedGroups())
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

        <div class="grid gap-4 lg:grid-cols-5">
            @foreach ($groups as $group)
                <button
                    type="button"
                    wire:click="selectGroup('{{ $group['key'] }}')"
                    class="rounded-xl border border-gray-200 bg-white p-4 text-right shadow-sm transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-500/40 dark:hover:bg-primary-500/10"
                >
                    <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $group['label'] }}</div>
                    <div class="mt-2 text-2xl font-bold text-primary-600 dark:text-primary-400">{{ $group['count'] }}</div>
                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $group['description'] }}</div>
                </button>
            @endforeach
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="mb-4 text-lg font-bold text-gray-950 dark:text-white">فلاتر الخانات المحجوبة</h2>
            <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-5">
                <label class="space-y-1 text-sm">
                    <span>الفصل الدراسي</span>
                    <input wire:model.live="filters.academic_term_id" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" placeholder="معرف الفصل">
                </label>
                <label class="space-y-1 text-sm">
                    <span>المادة</span>
                    <input wire:model.live="filters.subject" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" placeholder="بحث بالمادة">
                </label>
                <label class="space-y-1 text-sm">
                    <span>الشعبة</span>
                    <input wire:model.live="filters.section" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" placeholder="T1 / P1">
                </label>
                <label class="space-y-1 text-sm">
                    <span>اليوم</span>
                    <input wire:model.live="filters.weekday" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" placeholder="السبت">
                </label>
                <label class="space-y-1 text-sm">
                    <span>المشكلة</span>
                    <select wire:model.live="filters.problem" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                        <option value="">كل المشكلات</option>
                        <option value="missing_lecturer_identity">مدرس مفقود</option>
                        <option value="missing_hall">قاعة مفقودة</option>
                        <option value="weekly_schedule_overlap">تعارض أسبوعي</option>
                        <option value="scheduling_conflict">تعارض توليد</option>
                    </select>
                </label>
                <label class="space-y-1 text-sm">
                    <span>رقم الموعد الأسبوعي</span>
                    <input wire:model.live="filters.slot_id" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                </label>
                <label class="space-y-1 text-sm">
                    <span>رقم صف Excel</span>
                    <input wire:model.live="filters.excel_row" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                </label>
                <label class="flex items-center gap-2 pt-6 text-sm">
                    <input type="checkbox" wire:model.live="filters.missing_lecturer">
                    <span>المدرس مفقود</span>
                </label>
                <label class="flex items-center gap-2 pt-6 text-sm">
                    <input type="checkbox" wire:model.live="filters.missing_hall">
                    <span>القاعة مفقودة</span>
                </label>
                <label class="flex items-center gap-2 pt-6 text-sm">
                    <input type="checkbox" wire:model.live="filters.conflict">
                    <span>تعارض</span>
                </label>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-filament::button color="gray" wire:click="selectFiltered">تحديد النتائج المفلترة</x-filament::button>
                <x-filament::button color="gray" wire:click="clearSelection">مسح التحديد</x-filament::button>
                <span class="self-center text-sm text-gray-500">المحدد حالياً: {{ count($selectedSlotIds) }}</span>
            </div>
        </div>

        <div class="rounded-xl border border-primary-200 bg-white p-4 shadow-sm dark:border-primary-500/30 dark:bg-gray-900">
            <h2 class="mb-4 text-lg font-bold text-gray-950 dark:text-white">المعالجة الجماعية مع معاينة قبل الحفظ</h2>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <label class="space-y-1 text-sm">
                    <span>نوع الإجراء</span>
                    <select wire:model.live="bulkAction" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                        <option value="{{ \App\Services\BlockedWeeklySlotReconciliationService::ACTION_ASSIGN_LECTURER }}">إسناد مدرس للخانات المحددة</option>
                        <option value="{{ \App\Services\BlockedWeeklySlotReconciliationService::ACTION_ASSIGN_HALL }}">إسناد قاعة للخانات المحددة</option>
                        <option value="{{ \App\Services\BlockedWeeklySlotReconciliationService::ACTION_ASSIGN_LECTURER_AND_HALL }}">إسناد مدرس وقاعة للخانات المحددة</option>
                        <option value="{{ \App\Services\BlockedWeeklySlotReconciliationService::ACTION_CREATE_LECTURER_FROM_SOURCE }}">إنشاء هوية مدرس من قيمة المصدر</option>
                        <option value="{{ \App\Services\BlockedWeeklySlotReconciliationService::ACTION_CHANGE_TIME }}">تغيير وقت البداية والنهاية</option>
                        <option value="{{ \App\Services\BlockedWeeklySlotReconciliationService::ACTION_EXCLUDE_FROM_CURRENT_BATCH }}">استبعاد من برنامج هذا الفصل</option>
                    </select>
                </label>
                <label class="space-y-1 text-sm">
                    <span>المدرس المقترح</span>
                    <select wire:model.live="bulkLecturerId" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                        <option value="">اختر مدرساً</option>
                        @foreach ($this->lecturerOptions() as $lecturer)
                            <option value="{{ $lecturer['id'] }}">
                                {{ $lecturer['name'] }} — #{{ $lecturer['id'] }} — {{ $lecturer['login_username'] ?: 'لا يوجد حساب' }} — {{ $lecturer['has_course_lecturer_role'] ? 'course_lecturer' : 'بدون دور' }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1 text-sm">
                    <span>القاعة المقترحة</span>
                    <select wire:model.live="bulkHallId" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                        <option value="">اختر قاعة</option>
                        @foreach ($this->hallOptions() as $hall)
                            <option value="{{ $hall['id'] }}">
                                {{ $hall['code'] }} — {{ $hall['name'] }} — {{ $hall['floor'] ?: 'طابق غير محدد' }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1 text-sm">
                    <span>اليوم لتعديل الوقت</span>
                    <input wire:model.live="bulkWeekday" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" placeholder="1-7">
                </label>
                <label class="space-y-1 text-sm">
                    <span>وقت البداية</span>
                    <input wire:model.live="bulkStartTime" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" placeholder="08:30">
                </label>
                <label class="space-y-1 text-sm">
                    <span>وقت النهاية</span>
                    <input wire:model.live="bulkEndTime" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" placeholder="10:30">
                </label>
                <label class="space-y-1 text-sm xl:col-span-2">
                    <span>سبب الاستبعاد / ملاحظة المدير</span>
                    <input wire:model.live="bulkReason" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" placeholder="سبب إلزامي عند الاستبعاد">
                </label>
                <label class="space-y-1 text-sm xl:col-span-4">
                    <span>ملاحظة التدقيق</span>
                    <textarea wire:model.live="bulkNote" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" rows="2"></textarea>
                </label>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-filament::button wire:click="previewBulkAction">معاينة المعالجة المحددة</x-filament::button>
                <x-filament::button
                    color="success"
                    wire:click="applyBulkAction"
                    wire:confirm="سيتم حفظ المعالجة وتسجيلها في سجل التدقيق. هل أنت متأكد؟"
                    :disabled="! (($bulkPreview['confirm_enabled'] ?? false) === true)"
                >
                    حفظ المعالجة
                </x-filament::button>
                <x-filament::button color="gray" wire:click="previewBulkAction('{{ \App\Services\BlockedWeeklySlotReconciliationService::ACTION_CREATE_LECTURER_FROM_SOURCE }}')">إنشاء هوية مدرس من قيمة المصدر</x-filament::button>
                <x-filament::button color="gray" wire:click="previewBulkAction('{{ \App\Services\BlockedWeeklySlotReconciliationService::ACTION_EXCLUDE_FROM_CURRENT_BATCH }}')">استبعاد من برنامج هذا الفصل</x-filament::button>
            </div>

            @if ($bulkPreview)
                <div class="mt-4 rounded-lg border border-gray-200 p-4 text-sm dark:border-gray-700">
                    <div class="font-bold">نتيجة المعاينة — {{ $bulkPreview['confirm_enabled'] ? 'جاهزة للحفظ' : 'محجوبة' }}</div>
                    <div class="mt-2">عدد الخانات المحددة: {{ $bulkPreview['selected_count'] }}</div>
                    <div>الجلسات المتوقعة الآمنة بعد المعالجة: {{ $bulkPreview['readiness']['expected_dated_sessions_safe_to_create'] ?? 0 }}</div>
                    <div>الخانات التي ستصبح جاهزة: {{ $bulkPreview['readiness']['weekly_slots_that_would_become_ready'] ?? 0 }}</div>
                    <div>الخانات التي ستبقى محجوبة: {{ $bulkPreview['readiness']['slots_still_blocked'] ?? 0 }}</div>
                    <div>الحسابات التي ستبقى مفقودة: {{ $bulkPreview['readiness']['accounts_still_missing'] ?? 0 }}</div>
                    <div>القاعات التي ستبقى مفقودة: {{ $bulkPreview['readiness']['halls_still_missing'] ?? 0 }}</div>
                    <div class="mt-2 text-xs text-gray-500">التوليد يبقى إجراءً منفصلاً ولا يتم تشغيله من هذه الصفحة.</div>
                    @if (($bulkPreview['warnings'] ?? []) !== [])
                        <ul class="mt-3 list-disc space-y-1 pr-5 text-amber-700 dark:text-amber-300">
                            @foreach ($bulkPreview['warnings'] as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if (($bulkPreview['blocking_errors'] ?? []) !== [])
                        <ul class="mt-3 list-disc space-y-1 pr-5 text-danger-700 dark:text-danger-300">
                            @foreach ($bulkPreview['blocking_errors'] as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
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
                    <th class="px-3 py-2">تحديد</th>
                    <th class="px-3 py-2">رقم الموعد الأسبوعي</th>
                    <th class="px-3 py-2">رقم صف Excel</th>
                    <th class="px-3 py-2">المادة</th>
                    <th class="px-3 py-2">الشعبة</th>
                    <th class="px-3 py-2">المدرس</th>
                    <th class="px-3 py-2">القيمة الأصلية من الملف</th>
                    <th class="px-3 py-2">القيمة المعتمدة بعد المعالجة</th>
                    <th class="px-3 py-2">القاعة</th>
                    <th class="px-3 py-2">القيمة الأصلية للقاعة</th>
                    <th class="px-3 py-2">القاعة المعتمدة بعد المعالجة</th>
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
                        <td class="px-3 py-2">
                            <input type="checkbox" wire:model.live="selectedSlotIds" value="{{ $row['رقم الموعد الأسبوعي'] }}">
                        </td>
                        <td class="px-3 py-2 font-mono">{{ $row['رقم الموعد الأسبوعي'] }}</td>
                        <td class="px-3 py-2 font-mono">{{ $row['رقم صف Excel'] }}</td>
                        <td class="px-3 py-2">{{ $row['المادة'] }}</td>
                        <td class="px-3 py-2">{{ $row['الشعبة'] }}</td>
                        <td class="px-3 py-2">{{ $row['المدرس'] }}</td>
                        <td class="px-3 py-2">{{ $row['القيمة الأصلية من الملف - المدرس'] ?: '—' }}</td>
                        <td class="px-3 py-2">{{ $row['القيمة المعتمدة بعد المعالجة - المدرس'] ?: '—' }}</td>
                        <td class="px-3 py-2">{{ $row['القاعة'] }}</td>
                        <td class="px-3 py-2">{{ $row['القيمة الأصلية من الملف - القاعة'] ?: '—' }}</td>
                        <td class="px-3 py-2">{{ $row['القيمة المعتمدة بعد المعالجة - القاعة'] ?: '—' }}</td>
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
                        <td colspan="17" class="px-3 py-8 text-center text-gray-500">لا توجد خانات محجوبة في آخر عملية توليد.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
