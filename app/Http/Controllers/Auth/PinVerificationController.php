<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PinLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PinVerificationController extends Controller
{
    public function showSet(Request $request, PinLoginService $pinLogin): RedirectResponse|View
    {
        $user = $request->user();

        if (! $user || ! $pinLogin->requiresPinSetup($user)) {
            return $this->redirectToDashboard($user);
        }

        return view('auth.set-pin');
    }

    public function set(Request $request, PinLoginService $pinLogin): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $pinLogin->requiresPinSetup($user)) {
            return $this->redirectToDashboard($user);
        }

        $request->validate([
            'new_pin' => ['required', 'digits:6', 'confirmed'],
        ], [
            'new_pin.required' => __('profile.new_pin_required'),
            'new_pin.digits' => __('profile.pin_digits'),
            'new_pin.confirmed' => __('profile.new_pin_confirmed'),
        ]);

        $user->forceFill([
            'pin_code' => Hash::make((string) $request->input('new_pin')),
            'pin_enabled' => true,
            'pin_changed_at' => now(),
        ])->save();

        $pinLogin->markVerified();

        return $this->redirectToDashboard($user)
            ->with('success', __('profile.pin_set_successfully'));
    }

    public function show(Request $request, PinLoginService $pinLogin): RedirectResponse|View
    {
        $user = $request->user();

        if ($user && $pinLogin->requiresPinSetup($user)) {
            return redirect()->route('pin.set.form');
        }

        if (! $user || ! $pinLogin->requiresPinVerification($user) || $pinLogin->sessionVerified($user)) {
            return $this->redirectToDashboard($user);
        }

        return view('auth.verify-pin');
    }

    public function verify(Request $request, PinLoginService $pinLogin): RedirectResponse
    {
        $user = $request->user();

        if ($user && $pinLogin->requiresPinSetup($user)) {
            return redirect()->route('pin.set.form');
        }

        if (! $user || ! $pinLogin->requiresPinVerification($user)) {
            return $this->redirectToDashboard($user);
        }

        $request->validate([
            'pin_code' => ['required', 'digits:6'],
        ], [
            'pin_code.required' => __('auth.pin_required'),
            'pin_code.digits' => __('auth.pin_digits'),
        ]);

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'pin_code' => __('auth.failed'),
            ]);
        }

        if (! $pinLogin->validPin($user, $request->input('pin_code'))) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'pin_code' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        $pinLogin->markVerified();

        return $this->redirectToDashboard($user);
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate('pin|' . $request->user()?->id . '|' . $request->ip());
    }

    private function dashboardPath($user): string
    {
        return match (true) {
            $user?->hasAnyRole(['super-admin', 'admin']) => '/admin',
            $user?->hasRole('course_lecturer') => '/admin',
            $user?->hasRole('manager') => '/manager',
            $user?->hasRole('student') => '/student',
            default => '/login',
        };
    }

    private function redirectToDashboard($user): RedirectResponse
    {
        $this->forgetLivewireIntendedUrl();

        return redirect()->intended($this->dashboardPath($user));
    }

    private function forgetLivewireIntendedUrl(): void
    {
        $intended = session('url.intended');

        if (! is_string($intended)) {
            return;
        }

        $path = parse_url($intended, PHP_URL_PATH);

        if (is_string($path) && Str::startsWith(ltrim($path, '/'), 'livewire/')) {
            session()->forget('url.intended');
        }
    }
}
