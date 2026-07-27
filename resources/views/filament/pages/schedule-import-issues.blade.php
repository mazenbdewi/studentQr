<x-filament-panels::page dir="rtl">
    @php($result = $this->issueResult())
    @php($rows = $this->filteredRows())
    @php($filters = $this->filterOptions())
    @php($term = \App\Models\AcademicTerm::query()->find($this->academicTermId))
    @php($batch = $this->importBatchId ? \App\Models\ImportBatch::query()->find($this->importBatchId) : null)
    @php($actor = \Filament\Facades\Filament::auth()->user())

    <div class="space-y-5">
        <x-filament::section heading="معالجة مشكلات الجدول الأسبوعي" description="تستند هذه الصفحة إلى معاينة التوليد نفسها. عداداتها التالية تخص كامل الفصل والدفعة المحددين، ولا تتغير عند تطبيق الفلاتر." icon="heroicon-o-wrench-screwdriver">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-1 text-sm"><p><strong>الفصل الدراسي:</strong> {{ $term?->display_name }}</p>@if($batch)<p><strong>دفعة استيراد البرنامج:</strong> {{ $batch->source_filename ?: $batch->completed_at?->format('d/m/Y') }}</p>@endif</div>
                <x-filament::button tag="a" color="gray" :href="\App\Filament\Resources\LectureSessions\LectureSessionResource::getUrl('index')">العودة إلى معاينة توليد الجلسات</x-filament::button>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                @foreach ([['الخانات التي تحتاج معالجة', $result->uniqueAffectedSlots, 'danger'], ['مشكلة المدرّس', $result->issueCountsByKey['missing_lecturer_identity'] ?? 0, 'warning'], ['مشكلة القاعة', $result->issueCountsByKey['missing_hall'] ?? 0, 'warning'], ['تعارضات الجدول', $result->issueCountsByKey['weekly_schedule_overlap'] ?? 0, 'danger'], ['مشكلات حساب أو دور المدرّس', ($result->issueCountsByKey['missing_active_lecturer_login'] ?? 0) + ($result->issueCountsByKey['missing_course_lecturer_role'] ?? 0), 'gray']] as [$label, $count, $color])
                    <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10"><x-filament::badge :color="$color">{{ $count }}</x-filament::badge><p class="mt-2 text-sm">{{ $label }}</p></div>
                @endforeach
            </div>
            <p class="mt-3 text-sm text-success-700 dark:text-success-300">الخانات الجاهزة بعد المعالجة: {{ $result->readySlots }}</p>
        </x-filament::section>

        <x-filament::section heading="الفلاتر" icon="heroicon-o-funnel">
            <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-5">
                <select wire:model.live="statusFilter" class="fi-input w-full rounded-lg"><option value="needs_attention">تحتاج معالجة</option><option value="resolved">عولجت</option><option value="excluded">مستبعدة</option><option value="all">الكل</option></select>
                <select wire:model.live="reasonFilter" class="fi-input w-full rounded-lg"><option value="">نوع المشكلة: الكل</option>@foreach($filters['reasons'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                @foreach (['facultyFilter' => 'faculties', 'departmentFilter' => 'departments', 'subjectFilter' => 'subjects', 'sectionFilter' => 'sections', 'lecturerFilter' => 'lecturers', 'hallFilter' => 'halls'] as $model => $source)
                    <select wire:model.live="{{ $model }}" class="fi-input w-full rounded-lg"><option value="">الكل</option>@foreach($filters[$source] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select>
                @endforeach
                <select wire:model.live="weekdayFilter" class="fi-input w-full rounded-lg"><option value="">اليوم: الكل</option>@foreach($filters['weekdays'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
            </div>
            @if($actor?->isSuperAdmin() || $actor?->can(\App\Policies\ScheduleImportRowPolicy::EXPORT))<x-filament::button class="mt-4" icon="heroicon-o-arrow-down-tray" wire:click="exportExcel">تصدير Excel للنتائج المفلترة</x-filament::button>@endif
        </x-filament::section>

        <x-filament::section heading="الخانات" icon="heroicon-o-exclamation-triangle">
            <div class="overflow-x-auto"><table class="w-full min-w-[1250px] text-right text-sm"><thead class="border-b text-gray-600 dark:text-gray-300"><tr><th class="p-3">المادة</th><th class="p-3">الشعبة</th><th class="p-3">اليوم والوقت</th><th class="p-3">المدرّس</th><th class="p-3">القاعة</th><th class="p-3">المشكلات</th><th class="p-3">الحالة</th><th class="p-3">الإجراءات</th></tr></thead><tbody class="divide-y dark:divide-white/10">
                @forelse ($rows as $row)
                    <tr><td class="p-3">{{ $row['subject'] }}<div class="text-xs text-gray-500">{{ $row['subject_code'] }}</div></td><td class="p-3">{{ $row['section'] }}</td><td class="p-3">{{ $row['weekday'] }} — {{ $row['time'] }}</td><td class="p-3">{{ $row['lecturer'] }}</td><td class="p-3">{{ $row['hall'] }}</td><td class="p-3"><div class="flex flex-wrap gap-1">@forelse ($row['reasons'] as $reason)<x-filament::badge color="danger">{{ $this->reasonLabel($reason) }}</x-filament::badge>@empty <span class="text-success-700">لا توجد مشكلات</span>@endforelse</div></td><td class="p-3"><x-filament::badge :color="$row['status'] === 'excluded' ? 'warning' : ($row['status'] === 'resolved' ? 'success' : 'danger')">{{ $row['status'] === 'excluded' ? 'مستبعدة بقرار مستخدم' : ($row['status'] === 'resolved' ? 'عولجت' : 'تحتاج معالجة') }}</x-filament::badge></td><td class="p-3"><div class="flex flex-wrap gap-2">
                        @if ($row['status'] === 'excluded')<x-filament::button size="sm" color="gray" wire:click="reopenSlot({{ $row['slot_id'] }})">إعادة فتح الخانة</x-filament::button>@else
                            @if(in_array('missing_lecturer_identity', $row['reasons'], true))<x-filament::button size="sm" wire:click="openResolution({{ $row['slot_id'] }}, 'lecturer')">تحديد المدرّس</x-filament::button>@endif
                            @if(in_array('missing_hall', $row['reasons'], true))<x-filament::button size="sm" color="gray" wire:click="openResolution({{ $row['slot_id'] }}, 'hall')">تحديد القاعة</x-filament::button>@endif
                            @if(in_array('weekly_schedule_overlap', $row['reasons'], true))<x-filament::button size="sm" color="warning" wire:click="openTimeResolution({{ $row['slot_id'] }})">معالجة التعارض</x-filament::button><x-filament::button size="sm" color="gray" wire:click="openResolution({{ $row['slot_id'] }}, 'lecturer')">تغيير المدرّس</x-filament::button><x-filament::button size="sm" color="gray" wire:click="openResolution({{ $row['slot_id'] }}, 'hall')">تغيير القاعة</x-filament::button>@endif
                            @if($row['status'] === 'needs_attention')<x-filament::button size="sm" color="danger" wire:click="openExclusion({{ $row['slot_id'] }})">استبعاد الخانة</x-filament::button>@endif
                        @endif
                    </div></td></tr>
                @empty <tr><td colspan="8" class="p-8 text-center text-gray-500">لا توجد خانات مطابقة للفلاتر الحالية.</td></tr>@endforelse
            </tbody></table></div>
        </x-filament::section>

        @if ($selectedSlotId && $resolutionType)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true"><div class="w-full max-w-lg rounded-xl bg-white p-6 dark:bg-gray-900"><h2 class="text-lg font-bold">{{ match($resolutionType) {'lecturer' => 'تحديد المدرّس', 'hall' => 'تحديد القاعة', 'time' => 'معالجة التعارض بتعديل الوقت', default => 'استبعاد الخانة'} }}</h2>
                @if(in_array($resolutionType, ['lecturer', 'hall'], true))<div class="mt-4"><select wire:model="{{ $resolutionType === 'lecturer' ? 'selectedLecturerId' : 'selectedHallId' }}" class="fi-input w-full rounded-lg"><option value="">اختر {{ $resolutionType === 'lecturer' ? 'مدرّسًا' : 'قاعة' }}</option>@foreach ($resolutionType === 'lecturer' ? $this->lecturerOptions() : $this->hallOptions() as $option)<option value="{{ $option['id'] }}">{{ $option['label'] }}</option>@endforeach</select></div><div class="mt-5 flex gap-2"><x-filament::button wire:click="applyResolution">حفظ وإعادة التحقق</x-filament::button>@elseif($resolutionType === 'time')<p class="mt-2 text-sm text-gray-600">الخانة الحالية فقط هي التي ستتغير. لا تُحذف أو تُعدّل الخانة المتعارضة تلقائيًا.</p>@if($selectedConflicts)<ul class="mt-3 list-inside list-disc rounded-lg bg-amber-50 p-3 text-sm text-amber-900">@foreach($selectedConflicts as $conflict)<li>الخانة المتعارضة: {{ $conflict['conflicting_source_slot_id'] ?? 'جلسة منشأة مسبقًا' }} — {{ $this->reasonLabel($conflict['reason'] ?? '') }}</li>@endforeach</ul>@endif<div class="mt-4 grid grid-cols-2 gap-3"><input type="time" wire:model="selectedStartTime" class="fi-input rounded-lg"><input type="time" wire:model="selectedEndTime" class="fi-input rounded-lg"></div><div class="mt-5 flex gap-2"><x-filament::button wire:click="applyTimeResolution">حفظ الوقت وإعادة التحقق</x-filament::button>@else<p class="mt-2 text-sm text-gray-600">الاستبعاد قرار مستخدم مسجل، ولا يعني أن الخانة عولجت.</p><textarea wire:model="exclusionReason" class="fi-input mt-4 w-full rounded-lg" rows="3" placeholder="سبب الاستبعاد (خمسة أحرف على الأقل)"></textarea><div class="mt-5 flex gap-2"><x-filament::button color="danger" wire:click="applyExclusion">تأكيد الاستبعاد</x-filament::button>@endif <x-filament::button color="gray" wire:click="closeResolution">إلغاء</x-filament::button></div></div></div>
        @endif
    </div>
</x-filament-panels::page>
