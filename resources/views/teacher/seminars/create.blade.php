@extends('teacher.layout')

@section('title', __('teacher.create_seminar'))

@push('styles')
<style>
.seminar-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
.form-field{display:flex;flex-direction:column;gap:7px}.form-field.full{grid-column:1/-1}
.form-field label{font-weight:700;color:#334155}
.form-field input,.form-field textarea{width:100%;border:1px solid #cbd5e1;border-radius:12px;padding:12px 14px;font:inherit;background:#fff}
.form-field textarea{min-height:120px;resize:vertical}
.field-options{grid-column:1/-1;border:1px solid #e2e8f0;border-radius:14px;padding:16px;background:#f8fafc}
.field-options-title{font-weight:800;margin-bottom:12px;color:#0f172a}
.checkbox-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
.checkbox-field{position:relative;display:flex;align-items:center;justify-content:center;gap:8px;min-height:48px;border:1px solid #d1d5db;border-radius:12px;padding:10px 16px;background:#fff;font-weight:800;color:#111827;cursor:pointer;text-align:center;transition:border-color .18s ease,background .18s ease,color .18s ease,box-shadow .18s ease,transform .18s ease}
.checkbox-field:hover{border-color:#93c5fd;background:#f8fafc}
.checkbox-field input{position:absolute;opacity:0;pointer-events:none}
.check-indicator{display:inline-flex;align-items:center;justify-content:center;width:0;opacity:0;overflow:hidden;color:currentColor;font-size:16px;font-weight:900;line-height:1;transition:width .18s ease,opacity .18s ease}
.option-text{color:currentColor;transition:color .18s ease}
.checkbox-field:has(input:checked){border-color:#60a5fa;background:linear-gradient(135deg,#1e40af,#2563eb);color:#fff;box-shadow:0 0 0 3px rgba(37,99,235,.22),0 10px 22px rgba(30,64,175,.22);transform:translateY(-1px)}
.checkbox-field:has(input:checked) .check-indicator{width:18px;opacity:1}
.checkbox-field.locked{cursor:default}
.field-error{color:#b91c1c;font-size:.9rem}
.form-actions{grid-column:1/-1;display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap;margin-top:8px}
.primary-action,.secondary-action{display:inline-flex;align-items:center;justify-content:center;border-radius:12px;padding:11px 18px;font-family:inherit;font-weight:700;text-decoration:none;cursor:pointer}
.primary-action{border:0;background:#1d4ed8;color:#fff}.secondary-action{border:1px solid #cbd5e1;background:#fff;color:#334155}
@media(max-width:760px){.seminar-form{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<form class="seminar-form" method="POST" action="{{ route('teacher.seminars.store') }}">
    @csrf

    <div class="form-field full">
        <label for="title">{{ __('teacher.seminar_title') }}</label>
        <input id="title" name="title" value="{{ old('title') }}" required>
        @error('title')<span class="field-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-field">
        <label for="audience_type">{{ __('teacher.audience_type') }}</label>
        <input id="audience_type" name="audience_type" value="{{ old('audience_type') }}" placeholder="{{ __('teacher.audience_placeholder') }}">
        @error('audience_type')<span class="field-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-field">
        <label for="location">{{ __('teacher.location') }}</label>
        <input id="location" name="location" value="{{ old('location') }}">
        @error('location')<span class="field-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-field">
        <label for="starts_at">{{ __('teacher.starts_at') }}</label>
        <input id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}">
        @error('starts_at')<span class="field-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-field">
        <label for="ends_at">{{ __('teacher.ends_at') }}</label>
        <input id="ends_at" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}">
        @error('ends_at')<span class="field-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-field full">
        <label for="description">{{ __('teacher.description') }}</label>
        <textarea id="description" name="description">{{ old('description') }}</textarea>
        @error('description')<span class="field-error">{{ $message }}</span>@enderror
    </div>

    <div class="field-options">
        <div class="field-options-title">{{ __('teacher.mobile_fields') }}</div>
        <div class="checkbox-grid">
            <label class="checkbox-field locked">
                <input type="checkbox" checked disabled>
                <span class="check-indicator">✓</span>
                <span class="option-text">{{ __('teacher.full_name') }}</span>
            </label>
            <label class="checkbox-field">
                <input type="checkbox" name="collect_specialization" value="1" @checked(old('collect_specialization', true))>
                <span class="check-indicator">✓</span>
                <span class="option-text">{{ __('teacher.specialization') }}</span>
            </label>
            <label class="checkbox-field">
                <input type="checkbox" name="collect_profession" value="1" @checked(old('collect_profession', true))>
                <span class="check-indicator">✓</span>
                <span class="option-text">{{ __('teacher.profession') }}</span>
            </label>
            <label class="checkbox-field">
                <input type="checkbox" name="collect_academic_rank" value="1" @checked(old('collect_academic_rank', true))>
                <span class="check-indicator">✓</span>
                <span class="option-text">{{ __('teacher.academic_rank') }}</span>
            </label>
            <label class="checkbox-field">
                <input type="checkbox" name="collect_age" value="1" @checked(old('collect_age'))>
                <span class="check-indicator">✓</span>
                <span class="option-text">{{ __('teacher.age') }}</span>
            </label>
            <label class="checkbox-field">
                <input type="checkbox" name="collect_organization" value="1" @checked(old('collect_organization'))>
                <span class="check-indicator">✓</span>
                <span class="option-text">{{ __('teacher.organization') }}</span>
            </label>
            <label class="checkbox-field">
                <input type="checkbox" name="collect_phone" value="1" @checked(old('collect_phone'))>
                <span class="check-indicator">✓</span>
                <span class="option-text">{{ __('teacher.phone') }}</span>
            </label>
            <label class="checkbox-field">
                <input type="checkbox" name="collect_email" value="1" @checked(old('collect_email'))>
                <span class="check-indicator">✓</span>
                <span class="option-text">{{ __('teacher.email') }}</span>
            </label>
            <label class="checkbox-field">
                <input type="checkbox" name="collect_notes" value="1" @checked(old('collect_notes'))>
                <span class="check-indicator">✓</span>
                <span class="option-text">{{ __('teacher.notes') }}</span>
            </label>
        </div>
    </div>

    <div class="form-actions">
        <a class="secondary-action" href="{{ route('teacher.seminars.index') }}">{{ __('teacher.cancel') }}</a>
        <button class="primary-action" type="submit">{{ __('teacher.save_changes') }}</button>
    </div>
</form>
@endsection
