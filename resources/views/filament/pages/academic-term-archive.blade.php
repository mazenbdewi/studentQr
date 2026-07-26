<x-filament-panels::page>
    <div class="rounded-lg border border-gray-300 bg-gray-50 p-4 font-semibold dark:bg-gray-900">عرض أرشيفي — الفصل غير حالي</div>
    <select wire:model.live="termId" class="mt-4 w-full rounded-lg border-gray-300 dark:bg-gray-800">
        <option value="">اختر فصلاً دراسياً سابقاً</option>
        @foreach ($this->terms() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
    </select>
    @if ($term = $this->selectedTerm())
        <h2 class="mt-5 text-lg font-bold">{{ $term->display_name }}</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($this->counts() as $label => $count)<div class="rounded-lg border p-4"><div class="text-sm">{{ $label }}</div><div class="text-2xl font-bold">{{ $count }}</div></div>@endforeach
        </div>
    @endif
</x-filament-panels::page>
