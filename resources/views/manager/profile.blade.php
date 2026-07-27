@extends('manager.layout')

@section('title', __('manager.profile_title'))

@push('styles')
<style>
.profile-card {
    max-width: 48rem;  
    margin-left: auto;
    margin-right: auto;
    background: #ffffff;
    border-radius: 1.5rem;  
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);  
    padding: 2rem; 
    border: 1px solid #e5e7eb;
}

.profile-title {
    font-size: 1.5rem;  
    font-weight: 700;  
    margin-bottom: 2rem;  
    color: #1f2937;  
    text-align: start;
}

.profile-section {
    padding-top: 1.5rem;
    margin-top: 1.75rem;
    border-top: 1px solid #e5e7eb;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0 0 1rem;
    color: #1f2937;
}

.section-help {
    color: #6b7280;
    font-size: 0.92rem;
    line-height: 1.7;
    margin: -0.5rem 0 1rem;
}

.form-group {
    margin-bottom: 1.5rem;  
}

.form-label {
    display: block;  
    margin-bottom: 0.5rem; 
    font-weight: 600; 
    color: #374151;  
    font-size: 0.95rem;
}

.form-input {
    width: 100%; 
    border: 1px solid #d1d5db;  
    border-radius: 0.75rem;  
    padding: 0.75rem 1rem;  
    font-size: 1rem;
    outline: none;
    transition: all 0.2s ease;
    background-color: #ffffff;
    box-sizing: border-box;
}

.form-input:focus {
    outline: none;
    box-shadow: 0 0 0 2px #3b82f6; 
    border-color: transparent;
}

.form-input.is-invalid {
    border-color: #ef4444;
    background-color: #fef2f2;
}

.submit-btn {
    margin-top: 2rem;  
    background-color: #1d4ed8;  
    color: white;
    font-weight: 600;  
    padding: 0.75rem 1.5rem;  
    border: none;
    border-radius: 0.75rem;  
    cursor: pointer;
    transition: background-color 0.2s ease, transform 0.1s ease;
    font-size: 1rem;
}

.submit-btn:hover {
    background-color: #1e40af;  
    transform: translateY(-1px);
}

.submit-btn:active {
    transform: translateY(0);
}

.field-error {
    color: #ef4444;
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

.alert {
    padding: 0.9rem 1rem;
    border-radius: 0.75rem;
    margin-bottom: 1.25rem;
    line-height: 1.7;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.alert ul {
    margin: 0;
    padding-inline-start: 1.25rem;
}

.danger-btn {
    background-color: #dc2626;
}

.danger-btn:hover {
    background-color: #b91c1c;
}

 
@media (max-width: 640px) {
    .profile-card {
        padding: 1.5rem;
    }

    .profile-title {
        font-size: 1.25rem;
        margin-bottom: 1.5rem;
    }

    .form-input {
        padding: 0.7rem 0.9rem;
    }

    .submit-btn {
        width: 100%;
        text-align: center;
    }
}
</style>
@endpush

@section('content')
<div class="profile-card">
    <h2 class="profile-title">{{ __('manager.profile_title') }}</h2>
    <p class="section-help">اسم المستخدم: {{ auth()->user()->login_username }}</p>

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

    <form method="POST" action="{{ route('manager.profile.update') }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label class="form-label">{{ __('manager.full_name') }}</label>
            <input type="text"
                   name="name"
                   value="{{ old('name', auth()->user()->name) }}"
                   class="form-input {{ $errors->has('name') ? 'is-invalid' : '' }}"
                   required>
            @error('name')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label">{{ __('manager.phone') }}</label>
            <input type="text"
                   name="phone"
                   value="{{ old('phone', auth()->user()->phone) }}"
                   class="form-input {{ $errors->has('phone') ? 'is-invalid' : '' }}"
                   required>
            @error('phone')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="submit-btn">
            {{ __('manager.save_changes') }}
        </button>
    </form>

    <form method="POST" action="{{ route('manager.profile.password.update') }}" class="profile-section">
        @csrf
        @method('PUT')

        <h2 class="section-title">{{ __('profile.change_password') }}</h2>

        <div class="form-group">
            <label class="form-label">{{ __('profile.current_password') }}</label>
            <input type="password" name="current_password" class="form-input" required autocomplete="current-password">
            @error('current_password')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label">{{ __('profile.new_password') }}</label>
            <input type="password" name="new_password" class="form-input" required autocomplete="new-password">
        </div>

        <div class="form-group">
            <label class="form-label">{{ __('profile.confirm_new_password') }}</label>
            <input type="password" name="new_password_confirmation" class="form-input" required autocomplete="new-password">
        </div>

        <button type="submit" class="submit-btn">
            {{ __('profile.change_password_action') }}
        </button>
    </form>

    <form method="POST" action="{{ route('manager.profile.pin.update') }}" class="profile-section">
        @csrf
        @method('PUT')

        <h2 class="section-title">{{ __('profile.change_pin') }}</h2>
        <p class="section-help">{{ __('profile.pin_help') }}</p>

        <div class="form-group">
            <label class="form-label">{{ __('profile.current_password') }}</label>
            <input type="password" name="current_password" class="form-input" required autocomplete="current-password">
        </div>

        @if ($user->hasPinCode())
            <div class="form-group">
                <label class="form-label">{{ __('profile.old_pin') }}</label>
                <input type="password" name="old_pin" class="form-input" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off">
                @error('old_pin')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        @endif

        <div class="form-group">
            <label class="form-label">{{ __('profile.new_pin') }}</label>
            <input type="password" name="new_pin" class="form-input" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off">
        </div>

        <div class="form-group">
            <label class="form-label">{{ __('profile.confirm_new_pin') }}</label>
            <input type="password" name="new_pin_confirmation" class="form-input" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off">
        </div>

        <button type="submit" class="submit-btn">
            {{ __('profile.change_pin_action') }}
        </button>
    </form>
</div>
@endsection
