<?php

namespace App\Livewire\Filament\Profile;

use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Jeffgreco13\FilamentBreezy\Livewire\MyProfileComponent;

/** @property Schema $form */
class UsernamePersonalInfo extends MyProfileComponent
{
    protected string $view = 'livewire.filament.profile.personal-info';

    public ?array $data = [];

    public static $sort = 10;

    public function mount(): void
    {
        $user = Filament::getCurrentOrDefaultPanel()->auth()->user();

        $this->form->fill([
            'name' => $user->name,
            'login_username' => $user->login_username,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('profile.full_name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('login_username')
                    ->label(__('profile.login_username'))
                    ->disabled()
                    ->dehydrated(false),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $user = Filament::getCurrentOrDefaultPanel()->auth()->user();

        $user->forceFill([
            'name' => $data['name'],
        ])->save();

        Notification::make()
            ->success()
            ->title(__('profile.personal_information_updated'))
            ->send();
    }
}
