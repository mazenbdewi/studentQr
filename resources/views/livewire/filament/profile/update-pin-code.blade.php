<x-filament::section :aside="true" :heading="__('profile.change_pin')" :description="__('profile.pin_help')">
    <form wire:submit.prevent="submit" class="space-y-6">
        {{ $this->form }}

        <div class="text-right">
            <x-filament::button type="submit">
                {{ __('profile.change_pin_action') }}
            </x-filament::button>
        </div>
    </form>
</x-filament::section>
