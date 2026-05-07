@extends('teacher.layout')

@section('title', __('teacher.profile_title'))
@push('styles')
<style>
:root {
    --primary: #2563eb;
    --primary-dark: #1d4ed8;
    --gray-50: #f8fafc;
    --gray-100: #f1f5f9;
    --gray-200: #e2e8f0;
    --gray-300: #cbd5e1;
    --gray-600: #475569;
    --gray-700: #334155;
    --gray-800: #1e293b;
    --gray-900: #0f172a;
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
    --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
    --radius-lg: 1rem;
    --radius-xl: 1.5rem;
}

body {
    font-family: 'Tajawal', sans-serif;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    min-height: 100vh;
    margin: 0;
    padding: 24px 16px;
    color: var(--gray-800);
}

.profile-container {
    max-width: 768px;
    margin: 0 auto;
    background: white;
    border-radius: var(--radius-xl);
    padding: 32px;
    box-shadow: var(--shadow-lg);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.profile-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--gray-800);
    margin-bottom: 32px;
    padding-bottom: 16px;
    border-bottom: 2px solid var(--gray-200);
    letter-spacing: -0.02em;
}

.profile-section {
    padding-top: 24px;
    margin-top: 28px;
    border-top: 1px solid var(--gray-200);
}

.section-title {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--gray-800);
    margin: 0 0 18px;
}

.section-help {
    color: var(--gray-600);
    font-size: 0.92rem;
    line-height: 1.7;
    margin: -10px 0 18px;
}

.form-group {
    margin-bottom: 24px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--gray-700);
    font-size: 0.95rem;
}

.form-input {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid var(--gray-200);
    border-radius: var(--radius-lg);
    font-size: 1rem;
    transition: all 0.2s ease;
    background: var(--gray-50);
    color: var(--gray-900);
    outline: none;
}

.form-input:focus {
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
}

.form-input::placeholder {
    color: var(--gray-400);
}

.submit-btn {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    font-weight: 700;
    font-size: 1rem;
    padding: 14px 32px;
    border: none;
    border-radius: 9999px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: var(--shadow-md);
    display: inline-block;
    margin-top: 16px;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-xl);
    background: linear-gradient(135deg, #1d4ed8 0%, #1e3a8a 100%);
}

.submit-btn:active {
    transform: translateY(0);
}

 
[dir="rtl"] .profile-title {
    text-align: right;
}

[dir="rtl"] .form-label {
    text-align: right;
}
 
@media (max-width: 640px) {
    .profile-container {
        padding: 20px;
        border-radius: 20px;
    }

    .profile-title {
        font-size: 1.5rem;
        margin-bottom: 24px;
    }

    .form-input {
        padding: 12px 16px;
        font-size: 16px; 
    }

    .submit-btn {
        width: 100%;
        text-align: center;
    }
}

 
.alert {
    padding: 16px 20px;
    border-radius: var(--radius-lg);
    margin-bottom: 24px;
    border: 1px solid transparent;
    font-size: 0.95rem;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border-color: #34d399;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border-color: #f87171;
}

.alert ul {
    margin: 0;
    padding-left: 20px;
}

.field-error {
    color: #dc2626;
    font-size: 0.875rem;
    margin-top: 6px;
}

.danger-btn {
    background: #dc2626;
}

.danger-btn:hover {
    background: #b91c1c;
}
</style>
@endpush
 @section('content')
<div class="profile-container">
    <h1 class="profile-title">{{ __('teacher.profile_title') }}</h1>

 
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
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

    <form method="POST" action="{{ route('teacher.profile.update') }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="name" class="form-label">{{ __('teacher.full_name') }}</label>
            <input type="text" id="name" name="name" value="{{ old('name', auth()->user()->name) }}" class="form-input" required>
        </div>

        <div class="form-group">
            <label for="email" class="form-label">{{ __('teacher.email') }}</label>
            <input type="email" id="email" name="email" value="{{ old('email', auth()->user()->email) }}" class="form-input" required>
        </div>

        <div class="form-group">
            <label for="phone" class="form-label">{{ __('teacher.phone') }}</label>
            <input type="text" id="phone" name="phone" value="{{ old('phone', auth()->user()->phone) }}" class="form-input">
        </div>

        <button type="submit" class="submit-btn">{{ __('teacher.save_changes') }}</button>
    </form>

    <form method="POST" action="{{ route('teacher.profile.password.update') }}" class="profile-section">
        @csrf
        @method('PUT')

        <h2 class="section-title">{{ __('profile.change_password') }}</h2>

        <div class="form-group">
            <label for="password_current_password" class="form-label">{{ __('profile.current_password') }}</label>
            <input type="password" id="password_current_password" name="current_password" class="form-input" required autocomplete="current-password">
            @error('current_password')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="new_password" class="form-label">{{ __('profile.new_password') }}</label>
            <input type="password" id="new_password" name="new_password" class="form-input" required autocomplete="new-password">
        </div>

        <div class="form-group">
            <label for="new_password_confirmation" class="form-label">{{ __('profile.confirm_new_password') }}</label>
            <input type="password" id="new_password_confirmation" name="new_password_confirmation" class="form-input" required autocomplete="new-password">
        </div>

        <button type="submit" class="submit-btn">{{ __('profile.change_password_action') }}</button>
    </form>

    <form method="POST" action="{{ route('teacher.profile.pin.update') }}" class="profile-section">
        @csrf
        @method('PUT')

        <h2 class="section-title">{{ __('profile.change_pin') }}</h2>
        <p class="section-help">{{ __('profile.pin_help') }}</p>

        <div class="form-group">
            <label for="pin_current_password" class="form-label">{{ __('profile.current_password') }}</label>
            <input type="password" id="pin_current_password" name="current_password" class="form-input" required autocomplete="current-password">
        </div>

        @if ($user->hasPinCode())
            <div class="form-group">
                <label for="old_pin" class="form-label">{{ __('profile.old_pin') }}</label>
                <input type="password" id="old_pin" name="old_pin" class="form-input" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off">
                @error('old_pin')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        @endif

        <div class="form-group">
            <label for="new_pin" class="form-label">{{ __('profile.new_pin') }}</label>
            <input type="password" id="new_pin" name="new_pin" class="form-input" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off">
        </div>

        <div class="form-group">
            <label for="new_pin_confirmation" class="form-label">{{ __('profile.confirm_new_pin') }}</label>
            <input type="password" id="new_pin_confirmation" name="new_pin_confirmation" class="form-input" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off">
        </div>

        <button type="submit" class="submit-btn">{{ __('profile.change_pin_action') }}</button>
    </form>
</div>
@endsection
