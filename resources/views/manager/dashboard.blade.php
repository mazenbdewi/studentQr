@extends('manager.layout')

@section('title', __('manager.dashboard_title'))

@section('content')

    <div class="grid md:grid-cols-4 gap-6">


        <div class="bg-white rounded-2xl shadow hover:shadow-xl transition p-6">

            <div class="text-sm text-gray-500">
                {{ __('manager.my_sessions') }}
            </div>

            <div class="mt-3 text-3xl font-bold text-blue-700">
                {{ $sessionsCount ?? 0 }}
            </div>

        </div>


        <div class="bg-white rounded-2xl shadow hover:shadow-xl transition p-6">

            <div class="text-sm text-gray-500">
                {{ __('manager.today_attendance') }}
            </div>

            <div class="mt-3 text-3xl font-bold text-green-600">
                {{ $todayAttendance ?? 0 }}
            </div>

        </div>


        <div class="bg-white rounded-2xl shadow hover:shadow-xl transition p-6">

            <div class="text-sm text-gray-500">
                {{ __('manager.total_students') }}
            </div>

            <div class="mt-3 text-3xl font-bold text-indigo-600">
                {{ $totalStudents ?? 0 }}
            </div>

        </div>


        <div class="bg-white rounded-2xl shadow hover:shadow-xl transition p-6">

            <div class="text-sm text-gray-500">
                {{ __('manager.active_session') }}
            </div>

            <div class="mt-4">


            </div>

        </div>

    </div>

    @if(auth()->user()->hasAnyRole(['super_admin', 'course_lecturer' ,'manager']))
        <div class="mt-8 flex justify-end">
            <a href="/admin"
               class="inline-flex items-center gap-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold px-6 py-3 rounded-xl shadow-md hover:shadow-lg transition-all duration-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                {{ __('manager.go_admin_panel')}}
            </a>
        </div>
    @endif

@endsection
