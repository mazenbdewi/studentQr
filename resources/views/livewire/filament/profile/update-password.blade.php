<x-filament::section :aside="true" :heading="__('profile.change_password')">
    <form wire:submit.prevent="submit" class="space-y-6">
        {{ $this->form }}

        <div class="text-right">
            <x-filament::button type="submit">
                {{ __('profile.change_password_action') }}
            </x-filament::button>
        </div>
    </form>
</x-filament::section>
