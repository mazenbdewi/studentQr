<?php

namespace App\Filament\Pages\Auth;

use App\Services\PinLoginService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use SensitiveParameter;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $response = $this->authenticatePassword();

        if (! $response) {
            return null;
        }

        $user = Filament::auth()->user();
        $pinLogin = app(PinLoginService::class);

        if ($user) {
            $pinLogin->clearVerification();

            if ($pinLogin->enabled()) {
                session()->put('url.intended', Filament::getUrl());
            }
        }

        Log::debug('PIN login debug: filament password login completed', $pinLogin->debugContext(
            request(),
            $user,
            middlewareExecuted: false,
        ));

        return $response;
    }

    protected function authenticatePassword(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();
        $pinLogin = app(PinLoginService::class);
        $login = (string) ($data['login_username'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $user = $pinLogin->findUserForLogin($login);
        $credentials = $this->getCredentialsFromFormData($data);

        if ((! $user) || (! Hash::check($password, (string) $user->password))) {
            $this->userUndertakingMultiFactorAuthentication = null;

            $this->fireFailedEvent(Filament::auth(), $user, $credentials);
            $this->throwFailureValidationException();
        }

        if (! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
            $this->fireFailedEvent(Filament::auth(), $user, $credentials);
            $this->throwFailureValidationException();
        }

        Filament::auth()->login($user, (bool) ($data['remember'] ?? false));

        session()->regenerate();

        return app(LoginResponse::class);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login_username')
            ->label('اسم المستخدم')
            ->required()
            ->autocomplete()
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'login_username' => $data['login_username'] ?? null,
            'password' => $data['password'] ?? null,
        ];
    }
}
