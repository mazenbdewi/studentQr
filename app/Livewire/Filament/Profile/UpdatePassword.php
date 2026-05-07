<?php

namespace App\Livewire\Filament\Profile;

use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Jeffgreco13\FilamentBreezy\Livewire\MyProfileComponent;

class UpdatePassword extends MyProfileComponent
{
    protected string $view = 'livewire.filament.profile.update-password';

    public ?array $data = [];

    public $user;

    public static $sort = 20;

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
                TextInput::make('new_password')
                    ->label(__('profile.new_password'))
                    ->password()
                    ->minLength(8)
                    ->required(),
                TextInput::make('new_password_confirmation')
                    ->label(__('profile.confirm_new_password'))
                    ->password()
                    ->same('new_password')
                    ->required(),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        if (! Hash::check((string) ($data['current_password'] ?? ''), (string) $this->user->password)) {
            throw ValidationException::withMessages([
                'data.current_password' => __('profile.current_password_incorrect'),
            ]);
        }

        $this->user->forceFill([
            'password' => Hash::make((string) $data['new_password']),
            'remember_token' => Str::random(60),
        ])->save();

        $this->reset(['data']);

        Notification::make()
            ->success()
            ->title(__('profile.password_changed_successfully'))
            ->send();
    }
}
