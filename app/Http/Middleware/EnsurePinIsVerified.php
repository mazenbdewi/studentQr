<?php

namespace App\Http\Middleware;

use App\Services\PinLoginService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsurePinIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $pinLogin = app(PinLoginService::class);

        Log::debug('PIN login debug: middleware executed', $pinLogin->debugContext(
            $request,
            $user,
            middlewareExecuted: true,
        ));

        if ($request->routeIs('logout', 'filament.*.auth.logout')) {
            $pinLogin->clearVerification();
        }

        if (! $user) {
            return $next($request);
        }

        if ($request->routeIs(
            'pin.set.form',
            'pin.set',
            'pin.verify.form',
            'pin.verify',
            'login',
            'logout',
            'lang.switch',
            'filament.*.auth.login',
            'filament.*.auth.logout',
        )) {
            return $next($request);
        }

        if (! $pinLogin->enabled()) {
            return $next($request);
        }

        if ($pinLogin->requiresPinSetup($user)) {
            $this->rememberIntendedUrl($request);

            return redirect()->route('pin.set.form');
        }

        if ($pinLogin->requiresPinVerification($user) && ! $pinLogin->sessionVerified($user)) {
            $this->rememberIntendedUrl($request);

            return redirect()->route('pin.verify.form');
        }

        return $next($request);
    }

    private function rememberIntendedUrl(Request $request): void
    {
        if (! $request->isMethod('GET') || $request->is('livewire/*')) {
            return;
        }

        session()->put('url.intended', $request->fullUrl());
    }
}
