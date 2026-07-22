<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PinLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ForcePasswordChangeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! (bool) $request->user()?->must_change_password) {
            return redirect('/admin');
        }

        return view('auth.force-password-change');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user !== null, 403);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->forceFill([
            'password' => Hash::make((string) $data['password']),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();

        app(PinLoginService::class)->clearVerification();

        return redirect()->intended('/admin')->with('success', __('auth.password_changed_continue'));
    }
}
