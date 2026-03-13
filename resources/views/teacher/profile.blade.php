@extends('teacher.layout')

@section('title', __('teacher.profile_title'))

@section('content')

    <div class="max-w-3xl mx-auto bg-white rounded-2xl shadow p-8">

        <h2 class="text-2xl font-bold mb-8 text-gray-800">
            {{ __('teacher.profile_title') }}
        </h2>

        <form method="POST" action="{{ route('teacher.profile.update') }}">
            @csrf
            @method('PUT')

            <div class="space-y-6">

                <div>
                    <label class="block mb-2 font-semibold text-gray-700">
                        {{ __('teacher.full_name') }}
                    </label>

                    <input type="text"
                           name="name"
                           value="{{ old('name', auth()->user()->name) }}"
                           class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-600 outline-none transition">

                </div>

                <div>
                    <label class="block mb-2 font-semibold text-gray-700">
                        {{ __('teacher.email') }}
                    </label>

                    <input type="email"
                           name="email"
                           value="{{ old('email', auth()->user()->email) }}"
                           class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-600 outline-none transition">

                </div>

                <div>
                    <label class="block mb-2 font-semibold text-gray-700">
                        {{ __('teacher.phone') }}
                    </label>

                    <input type="text"
                           name="phone"
                           value="{{ old('phone', auth()->user()->phone) }}"
                           class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-600 outline-none transition">

                </div>

            </div>

            <button class="mt-8 bg-blue-700 hover:bg-blue-800 text-white px-6 py-3 rounded-xl font-semibold transition">
                {{ __('teacher.save_changes') }}
            </button>

        </form>

    </div>

@endsection
