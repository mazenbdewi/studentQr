<?php

namespace App\Livewire\Filament\Profile;

use App\Services\PinLoginService;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Jeffgreco13\FilamentBreezy\Livewire\MyProfileComponent;

class UpdatePinCode extends MyProfileComponent
{
    protected string $view = 'livewire.filament.profile.update-pin-code';

    public ?array $data = [];

    public $user;

    public static $sort = 30;

    public function mount(): void
    {
        $this->user = Filament::getCurrentOrDefaultPanel()->auth()->user();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('current_password')
                    ->label(__('profile.current_password'))
                    ->password()
                    ->required(),
                TextInput::make('old_pin')
                    ->label(__('profile.old_pin'))
                    ->password()
                    ->length(6)
                    ->rules(['digits:6'])
                    ->visible(fn (): bool => $this->user?->hasPinCode() ?? false)
                    ->required(fn (): bool => $this->user?->hasPinCode() ?? false),
                TextInput::make('new_pin')
                    ->label(__('profile.new_pin'))
                    ->password()
                    ->length(6)
                    ->rules(['digits:6'])
                    ->required(),
                TextInput::make('new_pin_confirmation')
                    ->label(__('profile.confirm_new_pin'))
                    ->password()
                    ->same('new_pin')
                    ->length(6)
                    ->rules(['digits:6'])
                    ->required(),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $hadPinCode = $this->user->hasPinCode();

        if (! Hash::check((string) ($data['current_password'] ?? ''), (string) $this->user->password)) {
            throw ValidationException::withMessages([
                'data.current_password' => __('auth.failed'),
            ]);
        }

        if ($hadPinCode && ! Hash::check((string) ($data['old_pin'] ?? ''), (string) $this->user->pin_code)) {
            throw ValidationException::withMessages([
                'data.old_pin' => __('auth.failed'),
            ]);
        }

        $this->user->forceFill([
            'pin_code' => Hash::make((string) $data['new_pin']),
            'pin_enabled' => true,
            'pin_changed_at' => now(),
        ])->save();

        app(PinLoginService::class)->clearVerification();

        $this->user->refresh();
        $this->reset(['data']);

        Notification::make()
            ->success()
            ->title($hadPinCode ? __('profile.pin_changed_successfully') : __('profile.pin_set_successfully'))
            ->send();
    }
}
