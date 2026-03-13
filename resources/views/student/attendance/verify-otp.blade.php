@extends('student.layout')

@section('title', __('student.confirm_attendance'))

@section('content')
<div class="header">
    <div class="logo-box">
        <img src="{{ url('/images/logo.png') }}" alt="{{ __('student.university_name') }}" class="logo-image"
            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">

        <div class="logo-fallback" aria-hidden="true">
            <svg viewBox="0 0 64 64" width="56" height="56" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="8" y="10" width="48" height="44" rx="10" fill="currentColor" opacity=".14" />
                <path d="M20 26L32 18L44 26V28C44 35.18 38.63 41.3 32 42C25.37 41.3 20 35.18 20 28V26Z"
                    fill="currentColor" />
                <path d="M16 24L32 16L48 24" stroke="currentColor" stroke-width="4" stroke-linecap="round"
                    stroke-linejoin="round" />
                <path d="M32 42V50" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
            </svg>
        </div>
    </div>

    <h1 class="title">{{ __('student.confirm_attendance') }}</h1>
    <p class="subtitle">{{ __('student.university_name') }}</p>
</div>

<div class="messages">
    @if (session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
    @endif

    @if (session('status'))
    <div class="alert alert-success">
        {{ session('status') }}
    </div>
    @endif

    @if (session('message') && !session('success') && !session('status'))
    <div class="alert alert-success">
        {{ session('message') }}
    </div>
    @endif

    @if (session('error'))
    <div class="alert alert-error">
        {{ session('error') }}
    </div>
    @endif

    @if ($errors->any())
    <div class="alert alert-error">
        <ul>
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif
</div>

<form method="POST" action="{{ route('student.attendance.store', ['session' => $sessionId]) }}" class="form" novalidate>
     @csrf

    <div class="field">
        <label for="student_number" class="label">
            {{ __('student.student_number') }}
        </label>

        <input id="student_number" name="student_number" type="text" value="{{ old('student_number') }}"
            class="input {{ $errors->has('student_number') ? 'is-invalid' : '' }}"
            placeholder="{{ __('student.student_number') }}" autocomplete="off" inputmode="numeric" dir="ltr"
            aria-invalid="{{ $errors->has('student_number') ? 'true' : 'false' }}" autofocus>

        @error('student_number')
        <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field">
        <label for="otp" class="label">
            {{ __('student.verification_code') }}
        </label>

        <input id="otp" name="otp" type="text" value="{{ old('otp') }}"
            class="input {{ $errors->has('otp') ? 'is-invalid' : '' }}"
            placeholder="{{ __('student.verification_code') }}" autocomplete="one-time-code" inputmode="numeric"
            dir="ltr" aria-invalid="{{ $errors->has('otp') ? 'true' : 'false' }}">

        @error('otp')
        <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <button type="submit" class="submit-btn">
        {{ __('student.verify') }}
    </button>

    <div class="footer-note">
        {{ __('student.enter_student_number_and_code') }}
    </div>
</form>


@endsection
