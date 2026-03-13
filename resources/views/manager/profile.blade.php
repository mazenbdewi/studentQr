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
            <label class="form-label">{{ __('manager.email') }}</label>
            <input type="email"
                   name="email"
                   value="{{ old('email', auth()->user()->email) }}"
                   class="form-input {{ $errors->has('email') ? 'is-invalid' : '' }}"
                   required>
            @error('email')
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
</div>
@endsection