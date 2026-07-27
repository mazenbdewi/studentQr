<x-filament::section :aside="true" :heading="__('profile.personal_information')">
    <form wire:submit.prevent="submit" class="space-y-6">
        {{ $this->form }}

        <div class="text-right">
            <x-filament::button type="submit">
                {{ __('profile.update_information') }}
            </x-filament::button>
        </div>
    </form>
</x-filament::section>
