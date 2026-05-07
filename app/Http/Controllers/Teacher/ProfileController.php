<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\ManagesOwnAccountSecurity;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\PinLoginService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class ProfileController extends Controller
{
    use ManagesOwnAccountSecurity;

    public function edit()
    {
        $user = Auth::user();
        $pinLoginRequired = AppSetting::boolean(PinLoginService::SETTING_KEY);

        return view('teacher.profile', compact('user', 'pinLoginRequired'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;

        $user->save();

        return back()->with('success', __('profile.updated_successfully'));
    }
}
