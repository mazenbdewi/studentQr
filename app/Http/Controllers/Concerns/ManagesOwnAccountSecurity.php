<?php

namespace App\Http\Controllers\Concerns;

use App\Services\PinLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

trait ManagesOwnAccountSecurity
{
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check((string) $request->input('current_password'), (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('profile.current_password_incorrect'),
            ]);
        }

        $user->forceFill([
            'password' => Hash::make((string) $request->input('new_password')),
        ])->save();

        return back()->with('success', __('profile.password_changed_successfully'));
    }

    public function updatePin(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'old_pin' => [$user->hasPinCode() ? 'required' : 'nullable', 'digits:6'],
            'new_pin' => ['required', 'digits:6', 'confirmed'],
        ], $this->pinValidationMessages());

        $hadPinCode = $user->hasPinCode();

        if (! Hash::check((string) $request->input('current_password'), (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('auth.failed'),
            ]);
        }

        if ($hadPinCode && ! Hash::check((string) $request->input('old_pin'), (string) $user->pin_code)) {
            throw ValidationException::withMessages([
                'old_pin' => __('auth.failed'),
            ]);
        }

        $user->forceFill([
            'pin_code' => Hash::make((string) $request->input('new_pin')),
            'pin_enabled' => true,
            'pin_changed_at' => now(),
        ])->save();

        app(PinLoginService::class)->clearVerification();

        return back()->with('success', $hadPinCode
            ? __('profile.pin_changed_successfully')
            : __('profile.pin_set_successfully'));
    }

    private function pinValidationMessages(): array
    {
        return [
            'old_pin.required' => __('profile.old_pin_required'),
            'old_pin.digits' => __('profile.pin_digits'),
            'new_pin.required' => __('profile.new_pin_required'),
            'new_pin.digits' => __('profile.pin_digits'),
            'new_pin.confirmed' => __('profile.new_pin_confirmed'),
        ];
    }
}
