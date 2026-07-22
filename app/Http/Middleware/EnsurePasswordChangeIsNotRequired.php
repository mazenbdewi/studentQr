<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChangeIsNotRequired
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->passwordChangeIsRequired($user)) {
            return $next($request);
        }

        if ($request->routeIs(
            'login',
            'logout',
            'lang.switch',
            'password.force-change.form',
            'password.force-change.update',
            'pin.set.form',
            'pin.set',
            'pin.verify.form',
            'pin.verify',
            'filament.*.auth.login',
            'filament.*.auth.logout',
        )) {
            return $next($request);
        }

        if (! $request->isMethod('GET') || $request->is('livewire/*')) {
            abort(403, __('auth.password_change_required'));
        }

        session()->put('url.intended', $request->fullUrl());

        return redirect()->route('password.force-change.form');
    }

    private function passwordChangeIsRequired(Authenticatable $user): bool
    {
        if (! $user instanceof Model) {
            return false;
        }

        if (array_key_exists('must_change_password', $user->getAttributes())) {
            return (bool) $user->getAttribute('must_change_password');
        }

        return (bool) $user->newQuery()
            ->whereKey($user->getKey())
            ->value('must_change_password');
    }
}
