<x-filament-panels::page>
    <div class="p-4 font-semibold border border-gray-300 rounded-lg bg-gray-50 dark:bg-gray-900">عرض أرشيفي — الفصل غير نشط</div>
    <select wire:model.live="termId" class="w-full mt-4 border-gray-300 rounded-lg dark:bg-gray-800">
        <option value="">اختر فصلاً دراسياً سابقاً</option>
        @foreach ($this->terms() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
    </select>
    @if ($term = $this->selectedTerm())
        <h2 class="mt-5 text-lg font-bold">{{ $term->display_name }}</h2>
        <div class="grid gap-3 mt-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($this->counts() as $label => $count)<div class="p-4 border rounded-lg"><div class="text-sm">{{ $label }}</div><div class="text-2xl font-bold">{{ $count }}</div></div>@endforeach
        </div>
    @endif
</x-filament-panels::page>
