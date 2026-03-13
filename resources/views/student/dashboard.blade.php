<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}" dir="{{ $direction ?? 'rtl' }}">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        @yield('title', __('student.page_title'))
    </title>

    @vite(['resources/css/app.css','resources/js/app.js'])

    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
        body{
            font-family:Tajawal,sans-serif;
            background:#f3f4f6;
        }
    </style>

    @stack('styles')

</head>

<body class="antialiased">

<div class="flex min-h-screen">

     <aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-900 text-white p-6 flex flex-col">

        <div class="text-center mb-8">

            <img src="{{ asset('images/logo.png') }}"
                 alt="logo"
                 class="h-14 mx-auto mb-3">

            <h2 class="text-xl font-bold">
                {{ __('student.university') }}
            </h2>

            <p class="text-sm opacity-80 mt-1">
                {{ __('student.welcome') }}
                <!-- {{ auth()->user()->name }} -->
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
       
        <nav class="space-y-2 flex-1">

            <a href="{{ route('student.dashboard') }}"
               class="block px-4 py-2 rounded-lg transition hover:bg-white/10
               {{ request()->routeIs('student.dashboard') ? 'bg-white/20' : '' }}">

                {{ __('student.home') }}
            </a>

            <a href="{{ route('student.attendance') }}"
               class="block px-4 py-2 rounded-lg transition hover:bg-white/10
               {{ request()->routeIs('student.attendance') ? 'bg-white/20' : '' }}">

                {{ __('student.attendance') }}
            </a>

            <a href="{{ route('student.profile.edit') }}"
               class="block px-4 py-2 rounded-lg transition hover:bg-white/10
               {{ request()->routeIs('student.profile.edit') ? 'bg-white/20' : '' }}">

                {{ __('student.profile') }}
            </a>

        </nav>

      
        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf

            <button type="submit"
                    class="w-full bg-red-600 hover:bg-red-700 transition
                    py-2 rounded-lg font-semibold">

                {{ __('student.logout') }}

            </button>
        </form>

    </aside>

  
    <main class="flex-1 p-8 overflow-y-auto">

        @if(session('success'))
            <div class="bg-green-100 text-green-700 p-4 rounded-xl mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 text-red-700 p-4 rounded-xl mb-4">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-lg p-8">

            <h1 class="text-2xl font-bold mb-6 text-gray-800">
                @yield('title')
            </h1>

            @yield('content')

        </div>

    </main>

</div>

@stack('scripts')

</body>
</html>
