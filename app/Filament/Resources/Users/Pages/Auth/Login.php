<?php

namespace App\Filament\Resources\Users\Pages\Auth;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class Login extends Page
{
    use InteractsWithRecord;

    protected static string $resource = UserResource::class;

    protected string $view = 'filament.resources.users.pages.auth.login';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }
}
