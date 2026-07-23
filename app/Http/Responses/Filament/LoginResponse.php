<?php

namespace App\Http\Responses\Filament;

use App\Services\PinLoginService;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as Responsable;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements Responsable
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = Filament::auth()->user();
        $pinLogin = app(PinLoginService::class);

        if ($user && $user->must_change_password) {
            return redirect()->route('password.force-change.form');
        }

        Log::debug('PIN login debug: filament login response', $pinLogin->debugContext(
            $request,
            $user,
            middlewareExecuted: false,
        ));

        if ($user && $pinLogin->requiresPinSetup($user)) {
            return redirect()->route('pin.set.form');
        }

        if ($user && $pinLogin->requiresPinVerification($user) && ! $pinLogin->sessionVerified($user)) {
            return redirect()->route('pin.verify.form');
        }

        return redirect()->intended(Filament::getUrl());
    }
}
