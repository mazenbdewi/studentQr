<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('auth.login_title') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">

    <style>
    :root {
        --bg-start: #1e40af;
        --bg-end: #3b82f6;
        --card-bg: rgba(255, 255, 255, 0.96);
        --text: #0f172a;
        --muted: #64748b;
        --border: #dbe3f0;
        --primary: #1d4ed8;
        --primary-dark: #1e3a8a;
        --primary-soft: rgba(29, 78, 216, 0.12);
        --danger: #b91c1c;
        --danger-bg: #fef2f2;
        --danger-border: #fecaca;
        --shadow: 0 20px 60px rgba(0, 0, 0, 0.22);
        --radius-xl: 24px;
        --radius-lg: 16px;
        --radius-md: 12px;
    }

    * {
        box-sizing: border-box;
    }

    html,
    body {
        margin: 0;
        padding: 0;
        min-height: 100%;
    }

    body {
        font-family: 'Tajawal', sans-serif;
        background:
            radial-gradient(circle at top right, rgba(255, 255, 255, 0.10), transparent 22%),
            linear-gradient(135deg, var(--bg-start) 0%, var(--bg-end) 100%);
        color: var(--text);
    }

    a {
        text-decoration: none;
    }

    .page {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px 16px;
    }

    .login-card {
        width: 100%;
        max-width: 470px;
        backdrop-filter: blur(12px);
        background: var(--card-bg);
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow);
        border: 1px solid rgba(255, 255, 255, 0.35);
        padding: 30px 24px;
    }

    .language-switch {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 12px;
        font-size: 0.95rem;
        margin-bottom: 24px;
        color: var(--muted);
    }

    .language-switch a {
        color: var(--primary-dark);
        font-weight: 700;
    }

    .language-switch a:hover {
        text-decoration: underline;
    }

    .brand {
        text-align: center;
        margin-bottom: 28px;
    }

    .brand-logo {
        width: 84px;
        height: 84px;
        object-fit: contain;
        display: block;
        margin: 0 auto 14px;
    }

    .brand-title {
        margin: 0;
        font-size: 1.9rem;
        font-weight: 800;
        color: #1f2937;
        line-height: 1.3;
    }

    .brand-subtitle {
        margin: 8px 0 0;
        color: var(--muted);
        font-size: 1rem;
        line-height: 1.7;
    }

    .alert {
        border-radius: var(--radius-lg);
        padding: 14px 16px;
        margin-bottom: 18px;
        border: 1px solid transparent;
        font-size: 0.96rem;
        line-height: 1.7;
    }

    .alert-error {
        background: var(--danger-bg);
        color: var(--danger);
        border-color: var(--danger-border);
    }

    .alert ul {
        margin: 0;
        padding: 0 18px 0 0;
    }

    .form-group {
        margin-bottom: 18px;
    }

    .label {
        display: block;
        margin-bottom: 8px;
        color: #334155;
        font-weight: 700;
        font-size: 0.97rem;
    }

    .input {
        width: 100%;
        height: 52px;
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 0 14px;
        font-size: 1rem;
        font-family: inherit;
        color: var(--text);
        background: #fff;
        outline: none;
        transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease;
    }

    .input::placeholder {
        color: #94a3b8;
    }

    .input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px var(--primary-soft);
    }

    .input.is-invalid {
        border-color: #ef4444;
        background: #fffafb;
    }

    .field-error {
        margin-top: 8px;
        color: var(--danger);
        font-size: 0.88rem;
        line-height: 1.6;
    }

    .role-title {
        display: block;
        margin-bottom: 12px;
        color: #334155;
        font-weight: 700;
        font-size: 0.97rem;
    }

    .roles-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .role-option {
        display: flex;
        align-items: center;
        gap: 10px;
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 12px 14px;
        background: #fff;
        cursor: pointer;
        transition: border-color .2s ease, background-color .2s ease, box-shadow .2s ease;
    }

    .role-option:hover {
        border-color: #bfdbfe;
        background: #f8fbff;
    }

    .role-option input {
        margin: 0;
        accent-color: var(--primary);
    }

    .role-option span {
        font-weight: 600;
        color: #334155;
    }

    .remember-row {
        margin-bottom: 22px;
    }

    .remember-label {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        color: #334155;
        font-weight: 500;
        cursor: pointer;
    }

    .remember-label input {
        margin: 0;
        accent-color: var(--primary);
    }

    .submit-btn {
        width: 100%;
        height: 54px;
        border: none;
        border-radius: 16px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: #fff;
        font-size: 1rem;
        font-family: inherit;
        font-weight: 800;
        cursor: pointer;
        transition: transform .15s ease, box-shadow .2s ease, opacity .2s ease;
        box-shadow: 0 14px 28px rgba(29, 78, 216, 0.24);
    }

    .submit-btn:hover {
        transform: translateY(-1px);
    }

    .submit-btn:active {
        transform: translateY(0);
    }

    .footer {
        text-align: center;
        margin-top: 22px;
        color: var(--muted);
        font-size: 0.96rem;
        line-height: 1.7;
    }

    .footer a {
        color: var(--primary-dark);
        font-weight: 700;
    }

    .footer a:hover {
        text-decoration: underline;
    }

    @media (max-width: 640px) {
        .page {
            padding: 16px 12px;
        }

        .login-card {
            padding: 24px 16px;
            border-radius: 20px;
        }

        .brand-title {
            font-size: 1.55rem;
        }

        .brand-subtitle,
        .footer,
        .language-switch {
            font-size: 0.92rem;
        }

        .roles-grid {
            grid-template-columns: 1fr;
        }

        .brand-logo {
            width: 72px;
            height: 72px;
        }
    }
    </style>
</head>

<body>
    <div class="page">
        <div class="login-card">

            <div class="language-switch">
                <a href="{{ route('lang.switch', ['locale' => 'ar']) }}">
                    {{ __('auth.arabic') }}
                </a>

                <span>|</span>

                <a href="{{ route('lang.switch', ['locale' => 'en']) }}">
                    {{ __('auth.english') }}
                </a>
            </div>

            <div class="brand">
                <img src="{{ asset('images/logo.png') }}" alt="logo" class="brand-logo"
                    onerror="this.style.display='none';">

                <h1 class="brand-title">
                    {{ __('auth.university_name') }}
                </h1>

                <p class="brand-subtitle">
                    {{ __('auth.system_name') }}
                </p>
            </div>

            @if($errors->any())
            <div class="alert alert-error">
                <ul>
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="form-group">
                    <label for="email" class="label">
                        {{ __('auth.email') }}
                    </label>

                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                        class="input {{ $errors->has('email') ? 'is-invalid' : '' }}" placeholder="example@uni.edu">

                    @error('email')
                    <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="password" class="label">
                        {{ __('auth.password') }}
                    </label>

                    <input id="password" type="password" name="password" required autocomplete="current-password"
                        class="input {{ $errors->has('password') ? 'is-invalid' : '' }}" placeholder="********">

                    @error('password')
                    <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <span class="role-title">
                        {{ __('auth.login_as_optional') }}
                    </span>

                    <div class="roles-grid">
                        <label class="role-option">
                            <input type="radio" name="role" value="manager"
                                {{ old('role') === 'manager' ? 'checked' : '' }}>
                            <span>{{ __('auth.manager') }}</span>
                        </label>

                        <label class="role-option">
                            <input type="radio" name="role" value="lecturer"
                                {{ old('role') === 'lecturer' ? 'checked' : '' }}>
                            <span>{{ __('auth.lecturer') }}</span>
                        </label>

                        <label class="role-option">
                            <input type="radio" name="role" value="super-admin"
                                {{ old('role') === 'super-admin' ? 'checked' : '' }}>
                            <span>{{ __('auth.super-admin') }}</span>
                        </label>
                    </div>

                    @error('role')
                    <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="remember-row">
                    <label class="remember-label">
                        <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                        <span>{{ __('auth.remember_me') }}</span>
                    </label>
                </div>

                <button type="submit" class="submit-btn">
                    {{ __('auth.login') }}
                </button>
            </form>

            @if (Route::has('register'))
            <div class="footer">
                {{ __('auth.no_account') }}

                <a href="{{ route('register') }}">
                    {{ __('auth.create_account') }}
                </a>
            </div>
            @endif

        </div>
    </div>
</body>

</html>