<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $direction ?? 'rtl' }}">

<head>
    <meta charset="UTF-8">
    <title>@yield('title', __('teacher.dashboard_title'))</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Tajawal', sans-serif;
            background: #f3f4f6;
        }
    </style>

    @stack('styles')
</head>

<body class="antialiased">

<div class="flex min-h-screen">


    <aside class="w-64 bg-blue-900 text-white p-6 space-y-6">

        <div class="text-center">
            <img src="{{ asset('images/logo.png') }}" class="h-14 mx-auto mb-3">

            <h2 class="text-xl font-bold">
                {{ __('teacher.university') }}
            </h2>

            <p class="text-sm opacity-80 mt-1">
                {{ auth()->user()->name }}
            </p>
        </div>

        <div class="flex justify-center gap-3 text-sm mb-6">

            <a href="{{ route('lang.switch',['locale'=>'ar']) }}"
               class="hover:underline">
                {{ __('student.arabic') }}
            </a>

            <span>|</span>

            <a href="{{ route('lang.switch',['locale'=>'en']) }}"
               class="hover:underline">
                {{ __('student.english') }}

            </a>

        </div>
        <nav class="space-y-2">

            <a href="{{ route('manager.dashboard') }}"
               class="block px-4 py-2 rounded hover:bg-white/10
               {{ request()->routeIs('manager.dashboard') ? 'bg-white/20' : '' }}">
                {{ __('manager.dashboard') }}
            </a>

            <a href="{{ route('manager.profile') }}"
               class="block px-4 py-2 rounded hover:bg-white/10
               {{ request()->routeIs('manager.profile') ? 'bg-white/20' : '' }}">
                {{ __('manager.profile_title') }}
            </a>


            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    class="w-full px-4 py-2 rounded hover:bg-white/10 {{ app()->getLocale() == 'ar' ? 'text-right' : 'text-left' }}">
                    {{ __('manager.logout') }}
                </button>
            </form>

        </nav>

    </aside>


    <main class="flex-1 p-8">

        @if(session('success'))
            <div class="bg-green-100 text-green-700 p-4 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 text-red-700 p-4 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')

    </main>

</div>

@stack('scripts')

</body>
</html>
