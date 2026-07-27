@extends('student.layout')

@section('title', __('student.profile_title'))

@section('content')

    <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-xl overflow-hidden">

        <div class="bg-blue-600 text-white p-6">
            <h2 class="text-2xl font-bold">
                {{ __('student.profile_title') }}
            </h2>

            <p class="opacity-90 text-sm mt-1">
                اسم المستخدم: {{ auth()->user()->login_username }}
            </p>
        </div>

        @if (session('success'))
            <div class="mx-8 mt-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mx-8 mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                <ul class="list-disc space-y-1 ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('student.profile.update') }}" class="p-8 space-y-6">

            @csrf
            @method('PUT')

            <div class="grid md:grid-cols-2 gap-6">

                <div>
                    <label class="block mb-2">
                        {{ __('student.full_name') }}
                    </label>

                    <input name="name"
                           value="{{ old('name', auth()->user()->name) }}"
                           class="w-full border rounded-xl px-4 py-3">
                </div>

                <div>
                    <label class="block mb-2">
                        {{ __('student.phone') }}
                    </label>

                    <input name="phone"
                           value="{{ old('phone', auth()->user()->phone) }}"
                           class="w-full border rounded-xl px-4 py-3">
                </div>

            </div>

            <div class="flex justify-end">
                <button class="bg-blue-600 text-white px-8 py-3 rounded-xl hover:bg-blue-700 transition">
                    {{ __('student.save_changes') }}
                </button>
            </div>

        </form>

        <form method="POST" action="{{ route('student.profile.password.update') }}" class="border-t p-8 space-y-6">
            @csrf
            @method('PUT')

            <h2 class="text-xl font-semibold">
                {{ __('profile.change_password') }}
            </h2>

            <div class="grid md:grid-cols-2 gap-6">
                <input type="password" name="current_password"
                       placeholder="{{ __('profile.current_password') }}"
                       class="border rounded-xl px-4 py-3" required autocomplete="current-password">

                <input type="password" name="new_password"
                       placeholder="{{ __('profile.new_password') }}"
                       class="border rounded-xl px-4 py-3" required autocomplete="new-password">

                <div class="md:col-span-2">
                    <input type="password" name="new_password_confirmation"
                           placeholder="{{ __('profile.confirm_new_password') }}"
                           class="w-full border rounded-xl px-4 py-3" required autocomplete="new-password">
                </div>
            </div>

            <div class="flex justify-end">
                <button class="bg-blue-600 text-white px-8 py-3 rounded-xl hover:bg-blue-700 transition">
                    {{ __('profile.change_password_action') }}
                </button>
            </div>
        </form>

        <form method="POST" action="{{ route('student.profile.pin.update') }}" class="border-t p-8 space-y-6">
            @csrf
            @method('PUT')

            <div>
                <h2 class="text-xl font-semibold">
                    {{ __('profile.change_pin') }}
                </h2>
                <p class="mt-2 text-sm text-gray-500">
                    {{ __('profile.pin_help') }}
                </p>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <input type="password" name="current_password"
                       placeholder="{{ __('profile.current_password') }}"
                       class="border rounded-xl px-4 py-3" required autocomplete="current-password">

                @if ($user->hasPinCode())
                    <input type="password" name="old_pin"
                           placeholder="{{ __('profile.old_pin') }}"
                           class="border rounded-xl px-4 py-3" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off">
                @endif

                <input type="password" name="new_pin"
                       placeholder="{{ __('profile.new_pin') }}"
                       class="border rounded-xl px-4 py-3" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off">

                <input type="password" name="new_pin_confirmation"
                       placeholder="{{ __('profile.confirm_new_pin') }}"
                       class="border rounded-xl px-4 py-3" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off">
            </div>

            <div class="flex justify-end">
                <button class="bg-blue-600 text-white px-8 py-3 rounded-xl hover:bg-blue-700 transition">
                    {{ __('profile.change_pin_action') }}
                </button>
            </div>
        </form>
    </div>

@endsection
