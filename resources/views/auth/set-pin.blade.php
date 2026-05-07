<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('auth.pin_set_title') }}</title>
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            background: #f8fafc;
            color: #0f172a;
            font-family: "Cairo", "Tajawal", sans-serif;
        }

        .pin-card {
            width: min(420px, calc(100vw - 32px));
            padding: 28px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
        }

        .pin-title {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 800;
        }

        .pin-help {
            margin: 8px 0 22px;
            color: #64748b;
            font-size: 0.94rem;
            line-height: 1.7;
        }

        .label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .input {
            width: 100%;
            height: 52px;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 0 14px;
            font: inherit;
        }

        .input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        .field {
            margin-top: 14px;
        }

        .field-error {
            margin-top: 8px;
            color: #dc2626;
            font-size: 0.88rem;
            line-height: 1.6;
        }

        .submit-btn {
            width: 100%;
            margin-top: 18px;
            border: 0;
            border-radius: 12px;
            padding: 13px 18px;
            color: #fff;
            background: #1d4ed8;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
        }

        .logout-btn {
            width: 100%;
            margin-top: 10px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 12px 18px;
            color: #334155;
            background: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <main class="pin-card">
        <h1 class="pin-title">{{ __('auth.pin_set_title') }}</h1>
        <p class="pin-help">{{ __('auth.pin_set_required_message') }}</p>

        <form method="POST" action="{{ route('pin.set') }}">
            @csrf

            <div class="field">
                <label for="new_pin" class="label">{{ __('profile.new_pin') }}</label>
                <input id="new_pin" class="input" type="password" name="new_pin" required inputmode="numeric"
                    pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus>

                @error('new_pin')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="field">
                <label for="new_pin_confirmation" class="label">{{ __('profile.confirm_new_pin') }}</label>
                <input id="new_pin_confirmation" class="input" type="password" name="new_pin_confirmation" required
                    inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code">
            </div>

            <button type="submit" class="submit-btn">{{ __('auth.pin_set_action') }}</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="logout-btn">{{ __('auth.logout') }}</button>
        </form>
    </main>
</body>

</html>
