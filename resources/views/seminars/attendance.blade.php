<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('teacher.seminar_attendance') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Tajawal,sans-serif;background:#f4f7fb;color:#0f172a;display:flex;align-items:center;justify-content:center;padding:18px}
        .attendance-shell{width:100%;max-width:560px;background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 16px 40px rgba(15,23,42,.08);padding:24px}
        h1{font-size:1.45rem;line-height:1.5;margin:0 0 8px}.subtitle{color:#64748b;line-height:1.7;margin:0 0 20px}
        .form-grid{display:grid;gap:14px}.field{display:flex;flex-direction:column;gap:7px}.field label{font-weight:700;color:#334155}
        .field input,.field textarea{border:1px solid #cbd5e1;border-radius:12px;padding:12px 14px;font:inherit;background:#fff}.field textarea{min-height:92px;resize:vertical}
        .submit-btn{border:0;border-radius:12px;padding:13px 18px;background:#1d4ed8;color:#fff;font:inherit;font-weight:800;cursor:pointer;margin-top:6px}
        .error{color:#b91c1c;font-size:.9rem}.success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0;border-radius:14px;padding:18px;line-height:1.8}.notice{background:#fffbeb;color:#92400e;border:1px solid #fde68a;border-radius:14px;padding:18px;line-height:1.8}
    </style>
</head>
<body>
    <main class="attendance-shell">
        <h1>{{ $seminar->title }}</h1>
        <p class="subtitle">
            {{ $seminar->audience_type ?: __('teacher.general_audience') }}
            @if($seminar->location)
                <br>{{ $seminar->location }}
            @endif
        </p>

        @if($submitted)
            @if($alreadyRegistered ?? false)
                <div class="notice">{{ __('teacher.seminar_attendance_already_registered') }}</div>
            @else
                <div class="success">{{ __('teacher.seminar_attendance_recorded') }}</div>
            @endif
        @else
            <form class="form-grid" method="POST" action="{{ route('seminars.attendance.store', $seminar->qr_token) }}">
                @csrf

                <div class="field">
                    <label for="full_name">{{ __('teacher.full_name') }}</label>
                    <input id="full_name" name="full_name" value="{{ old('full_name') }}" required>
                    @error('full_name')<span class="error">{{ $message }}</span>@enderror
                </div>

                @if($seminar->collect_specialization)
                    <div class="field">
                        <label for="specialization">{{ __('teacher.specialization') }}</label>
                        <input id="specialization" name="specialization" value="{{ old('specialization') }}" placeholder="{{ __('teacher.specialization_placeholder') }}" required>
                        @error('specialization')<span class="error">{{ $message }}</span>@enderror
                    </div>
                @endif

                @if($seminar->collect_profession)
                    <div class="field">
                        <label for="profession">{{ __('teacher.profession') }}</label>
                        <input id="profession" name="profession" value="{{ old('profession') }}" placeholder="{{ __('teacher.profession_placeholder') }}" required>
                        @error('profession')<span class="error">{{ $message }}</span>@enderror
                    </div>
                @endif

                @if($seminar->collect_academic_rank)
                    <div class="field">
                        <label for="academic_rank">{{ __('teacher.academic_rank') }}</label>
                        <input id="academic_rank" name="academic_rank" value="{{ old('academic_rank') }}" placeholder="{{ __('teacher.academic_rank_placeholder') }}" required>
                        @error('academic_rank')<span class="error">{{ $message }}</span>@enderror
                    </div>
                @endif

                @if($seminar->collect_age)
                    <div class="field">
                        <label for="age">{{ __('teacher.age') }}</label>
                        <input id="age" type="number" min="1" max="120" name="age" value="{{ old('age') }}" required>
                        @error('age')<span class="error">{{ $message }}</span>@enderror
                    </div>
                @endif

                @if($seminar->collect_organization)
                    <div class="field">
                        <label for="organization">{{ __('teacher.organization') }}</label>
                        <input id="organization" name="organization" value="{{ old('organization') }}" required>
                        @error('organization')<span class="error">{{ $message }}</span>@enderror
                    </div>
                @endif

                @if($seminar->collect_phone)
                    <div class="field">
                        <label for="phone">{{ __('teacher.phone') }}</label>
                        <input id="phone" name="phone" value="{{ old('phone') }}" dir="ltr" required>
                        @error('phone')<span class="error">{{ $message }}</span>@enderror
                    </div>
                @endif

                @if($seminar->collect_email)
                    <div class="field">
                        <label for="email">{{ __('teacher.email') }}</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" dir="ltr" required>
                        @error('email')<span class="error">{{ $message }}</span>@enderror
                    </div>
                @endif

                @if($seminar->collect_notes)
                    <div class="field">
                        <label for="notes">{{ __('teacher.notes') }}</label>
                        <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
                        @error('notes')<span class="error">{{ $message }}</span>@enderror
                    </div>
                @endif

                <button class="submit-btn" type="submit">{{ __('teacher.register_attendance') }}</button>
            </form>
        @endif
    </main>
</body>
</html>
