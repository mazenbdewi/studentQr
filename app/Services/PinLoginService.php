<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PinLoginService
{
    public const SETTING_KEY = 'enable_pin_login';

    public const SESSION_VERIFIED = 'pin_verified';

    public const SESSION_VERIFIED_AT = 'pin_verified_at';

    public function enabled(): bool
    {
        return AppSetting::boolean(self::SETTING_KEY);
    }

    public function attempt(string $login, string $password, ?string $pinCode, bool $remember = false): bool
    {
        $user = $this->findUserForLogin($login);

        if (! $user || ! Hash::check($password, $user->password)) {
            return false;
        }

        if ($this->enabled() && ! $this->validPin($user, $pinCode)) {
            return false;
        }

        Auth::login($user, $remember);

        return true;
    }

    public function attemptPassword(string $login, string $password, bool $remember = false): ?User
    {
        $user = $this->findUserForLogin($login);

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        Auth::login($user, $remember);

        return $user;
    }

    public function requiresPin(User $user): bool
    {
        return $this->requiresPinVerification($user);
    }

    public function requiresPinSetup(User $user): bool
    {
        return $this->enabled()
            && ! $user->hasPinCode();
    }

    public function requiresPinVerification(User $user): bool
    {
        return $this->enabled()
            && $user->hasPinCode();
    }

    public function markVerified(): void
    {
        session([
            self::SESSION_VERIFIED => true,
            self::SESSION_VERIFIED_AT => now()->toISOString(),
        ]);
    }

    public function clearVerification(): void
    {
        session()->forget([
            self::SESSION_VERIFIED,
            self::SESSION_VERIFIED_AT,
        ]);
    }

    public function sessionVerified(User $user): bool
    {
        if (! $this->requiresPin($user)) {
            return true;
        }

        if (session(self::SESSION_VERIFIED) !== true) {
            return false;
        }

        $verifiedAt = session(self::SESSION_VERIFIED_AT);

        if (! $verifiedAt) {
            return false;
        }

        $pinChangedAt = $user->getRawOriginal('pin_changed_at');

        if (! $pinChangedAt) {
            return true;
        }

        return Carbon::parse($verifiedAt)->greaterThanOrEqualTo(Carbon::parse($pinChangedAt));
    }

    public function validPin(User $user, ?string $pinCode): bool
    {
        return $user->hasPinCode()
            && filled($pinCode)
            && Hash::check((string) $pinCode, (string) $user->pin_code);
    }

    public function findUserForLogin(string $login): ?User
    {
        $matches = $this->matchingUsersForLogin($login);

        if ($matches->count() !== 1) {
            return null;
        }

        return $matches->first();
    }

    /** @return Collection<int, User> */
    public function matchingUsersForLogin(string $login): Collection
    {
        $login = strtolower(trim($login));

        if ($login === '') {
            return collect();
        }

        return User::query()
            ->where('login_username', $login)
            ->get()
            ->unique('id')
            ->values();
    }

    public function debugContext(?Request $request = null, ?User $user = null, bool $middlewareExecuted = false): array
    {
        $request ??= request();
        $user ??= Auth::user();

        return [
            'user_id' => $user?->id,
            'global_pin_enabled' => $this->enabled(),
            'user_has_pin' => (bool) ($user?->hasPinCode()),
            'user_requires_pin_setup' => (bool) ($user && $this->requiresPinSetup($user)),
            'user_requires_pin_verification' => (bool) ($user && $this->requiresPinVerification($user)),
            'session_pin_verified' => session(self::SESSION_VERIFIED) === true,
            'current_route_name' => $request->route()?->getName(),
            'current_path' => '/'.ltrim($request->path(), '/'),
            'middleware_executed' => $middlewareExecuted,
        ];
    }
}
