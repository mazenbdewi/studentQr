<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('auth.force_password_change_title') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <main class="mx-auto flex min-h-screen max-w-md items-center px-6">
        <section class="w-full rounded-2xl bg-white p-8 shadow">
            <h1 class="mb-3 text-2xl font-bold">{{ __('auth.force_password_change_title') }}</h1>
            <p class="mb-6 text-sm text-slate-600">{{ __('auth.force_password_change_message') }}</p>

            @if (session('success'))
                <div class="mb-4 rounded bg-green-50 p-3 text-sm text-green-700">{{ session('success') }}</div>
            @endif

            <form method="POST" action="{{ route('password.force-change.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium">{{ __('auth.new_password') }}</label>
                    <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1 block text-sm font-medium">{{ __('auth.confirm_password') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <button type="submit" class="w-full rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white hover:bg-blue-800">
                    {{ __('auth.force_password_change_action') }}
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="mt-4">
                @csrf
                <button type="submit" class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">
                    {{ __('auth.logout') }}
                </button>
            </form>
        </section>
    </main>
</body>
</html>
