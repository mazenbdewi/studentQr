<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\PinLoginService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomLoginController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'student_number' => 'required|string|unique:users,student_number',
            'name' => 'required|string|max:255',
            'login_username' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9._-]+$/', 'unique:users,login_username'],
            'faculty_id' => 'required|exists:faculties,id',
            'department_id' => 'required|exists:departments,id',
            'year' => 'required|integer|min:1|max:4',
            'password' => 'required|min:8|confirmed',
            'avatar' => 'nullable|image|max:2048',
        ]);

        $avatarPath = null;

        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')
                ->store('avatars', 'public');
        }

        $user = User::create([
            'student_number' => $request->student_number,
            'name' => $request->name,
            'login_username' => strtolower(trim((string) $request->login_username)),
            'faculty_id' => $request->faculty_id,
            'department_id' => $request->department_id,
            'year' => $request->year,
            'avatar' => $avatarPath,
            'password' => Hash::make($request->password),
            'status' => 'pending',
            'type' => 'student',
        ]);
        $otp = rand(100000, 999999);

        $user->update([
            'activation_code' => $otp,
            'activation_expires' => now()->addMinutes(5),
        ]);

        $user->assignRole('student');

        return redirect()->route('otp.verify.form')
            ->with('login_username', $user->login_username);
    }

    public function showRegister()
    {
        $faculties = Faculty::with('departments')->get();

        return view('auth.register', compact('faculties'));
    }

    public function showLoginForm(PinLoginService $pinLogin)
    {
        return view('auth.custom-login', [
            'pinLoginEnabled' => $pinLogin->enabled(),
        ]);
    }

    public function login(Request $request, PinLoginService $pinLogin)
    {
        $pinLoginEnabled = $pinLogin->enabled();
        $login = strtolower(trim((string) $request->input('login')));

        $request->merge([
            'login' => $login,
        ]);

        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
            'role' => 'nullable|in:super-admin,admin,lecturer,manager',
        ]);

        $throttleKey = $this->throttleKey($request);

        if ($pinLoginEnabled && RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        $user = $pinLogin->attemptPassword(
            $login,
            (string) $request->input('password'),
            $request->boolean('remember')
        );

        if (! $user) {
            if ($pinLoginEnabled) {
                RateLimiter::hit($throttleKey, 60);
            }

            app(ActivityLogger::class)->logAuth('failed_login', 'login_failed', [
                'login' => $login,
                'role' => $request->input('role'),
                'pin_login_enabled' => $pinLoginEnabled,
            ]);

            return back()->withErrors([
                'login' => __('auth.failed'),
            ])->onlyInput('login', 'role', 'remember');
        }

        if (! ($user->is_active ?? true)) {
            if ($pinLoginEnabled) {
                RateLimiter::hit($throttleKey, 60);
            }

            app(ActivityLogger::class)->logAuth('failed_login', 'inactive_user_login_attempt', [
                'user_id' => $user->id,
                'login_username' => $user->login_username,
            ]);

            Auth::logout();

            return back()->withErrors([
                'login' => 'اسم المستخدم أو كلمة المرور غير صحيحة.',
            ])->onlyInput('login', 'role', 'remember');
        }

        if ($request->filled('role')) {

            $map = [
                'super-admin' => 'super-admin',
                'admin' => 'admin',
                'lecturer' => 'course_lecturer',
                'manager' => 'manager',
            ];

            $spatieRole = $map[$request->role] ?? null;

            if ($spatieRole && ! $user->hasRole($spatieRole)) {
                app(ActivityLogger::class)->logAuth('failed_login', 'unauthorized_role_login_attempt', [
                    'user_id' => $user->id,
                    'login_username' => $user->login_username,
                    'requested_role' => $request->input('role'),
                ]);

                Auth::logout();

                return back()->withErrors([
                    'role' => __('auth.unauthorized_role'),
                ])->onlyInput('login', 'role', 'remember');
            }
        }

        $request->session()->regenerate();

        $pinLogin->clearVerification();

        Log::debug('PIN login debug: custom password login completed', $pinLogin->debugContext(
            $request,
            $user,
            middlewareExecuted: false,
        ));

        if ($pinLogin->requiresPinSetup($user)) {
            RateLimiter::clear($throttleKey);
            session()->put('url.intended', $this->dashboardPath($user));

            return redirect()->route('pin.set.form');
        }

        if ($pinLogin->requiresPinVerification($user)) {
            RateLimiter::clear($throttleKey);
            session()->put('url.intended', $this->dashboardPath($user));

            return redirect()->route('pin.verify.form');
        }

        if ($pinLoginEnabled) {
            RateLimiter::clear($throttleKey);
        }

        app(ActivityLogger::class)->logAuth('login', 'user_logged_in', [
            'user_id' => $user->id,
            'login_username' => $user->login_username,
            'role' => $user->role,
        ]);

        return redirect($this->dashboardPath($user));
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('login')).'|'.$request->ip());
    }

    private function dashboardPath(User $user): string
    {
        return match (true) {
            $user->hasAnyRole(['super-admin', 'admin']) => '/admin',
            $user->hasRole('course_lecturer') => '/admin',
            $user->hasRole('manager') => '/manager',
            $user->hasRole('student') => '/student',
            default => '/login',
        };
    }

    public function logout(Request $request)
    {
        $user = Auth::user();

        if ($user) {
            app(ActivityLogger::class)->logAuth('logout', 'user_logged_out', [
                'user_id' => $user->id,
                'login_username' => $user->login_username,
            ]);
        }

        Auth::logout();
        app(PinLoginService::class)->clearVerification();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
