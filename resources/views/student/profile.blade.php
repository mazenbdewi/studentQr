@extends('student.layout')

@section('title', __('student.profile_title'))

@section('content')

    <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-xl overflow-hidden">

        <div class="bg-blue-600 text-white p-6">
            <h2 class="text-2xl font-bold">
                {{ __('student.profile_title') }}
            </h2>

            <p class="opacity-90 text-sm mt-1">
                {{ auth()->user()->email }}
            </p>
        </div>

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
                        {{ __('student.email') }}
                    </label>

                    <input name="email"
                           value="{{ old('email', auth()->user()->email) }}"
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

            <hr class="my-6">

            <h2 class="text-xl font-semibold">
                {{ __('student.change_password') }}
            </h2>

            <div class="grid md:grid-cols-2 gap-6">

                <input type="password" name="current_password"
                       placeholder="{{ __('student.current_password') }}"
                       class="border rounded-xl px-4 py-3">

                <input type="password" name="new_password"
                       placeholder="{{ __('student.new_password') }}"
                       class="border rounded-xl px-4 py-3">

                <div class="md:col-span-2">
                    <input type="password"
                           name="new_password_confirmation"
                           placeholder="{{ __('student.confirm_new_password') }}"
                           class="w-full border rounded-xl px-4 py-3">
                </div>

            </div>

            <div class="flex justify-end">
                <button class="bg-blue-600 text-white px-8 py-3 rounded-xl hover:bg-blue-700 transition">
                    {{ __('student.save_changes') }}
                </button>
            </div>

        </form>
    </div>

@endsection
