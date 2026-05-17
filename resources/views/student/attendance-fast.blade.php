@php
    $isRtl = app()->getLocale() === 'ar';
    $submitUrl = route('student.attendance.store.sync', ['session' => $sessionId ?? 0]);
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('student.attendance') }}</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f4f7fb; color: #172033; }
        main { width: min(440px, calc(100vw - 32px)); background: #fff; border: 1px solid #d9e2ef; border-radius: 8px; padding: 24px; box-shadow: 0 16px 40px rgba(23, 32, 51, .08); }
        h1 { margin: 0 0 8px; font-size: 24px; line-height: 1.25; }
        .meta { margin: 0 0 20px; color: #526274; font-size: 14px; line-height: 1.6; }
        .timer { display: inline-flex; align-items: center; justify-content: center; min-width: 76px; min-height: 36px; margin-bottom: 18px; border-radius: 8px; background: #eaf2ff; color: #0f4c9f; font-weight: 700; direction: ltr; }
        label { display: block; margin: 14px 0 6px; font-weight: 700; font-size: 14px; }
        input { width: 100%; height: 46px; border: 1px solid #c8d3e1; border-radius: 8px; padding: 0 12px; font: inherit; background: #fff; color: #172033; }
        input:focus { outline: 3px solid #b9d7ff; border-color: #2f80ed; }
        button { width: 100%; height: 48px; margin-top: 18px; border: 0; border-radius: 8px; background: #1769d1; color: #fff; font: inherit; font-weight: 800; cursor: pointer; }
        button:disabled { opacity: .62; cursor: not-allowed; }
        .message { display: none; margin-top: 16px; padding: 12px; border-radius: 8px; font-size: 14px; line-height: 1.5; }
        .message.success { display: block; background: #e9f8ef; color: #176437; border: 1px solid #bce8cb; }
        .message.error { display: block; background: #fff0f0; color: #9f1f1f; border: 1px solid #f2c1c1; }
    </style>
</head>
<body>
    <main>
        <h1>{{ __('student.attendance') }}</h1>
        <p class="meta">
            {{ $sessionDetails?->subject?->name ?? __('student.attendance') }}
        </p>

        <div class="timer" id="timer">{{ gmdate('i:s', max(0, (int) ($remainingSeconds ?? 0))) }}</div>

        <form id="attendanceForm" method="POST" action="{{ $submitUrl }}">
            <input type="hidden" name="submission_token" value="{{ $submissionToken ?? '' }}">
            <input type="hidden" id="device_fingerprint" name="device_fingerprint" value="">

            <label for="student_number">{{ __('student.student_number') }}</label>
            <input id="student_number" name="student_number" type="text" inputmode="numeric" autocomplete="off" dir="ltr" required autofocus>

            <label for="otp">{{ __('student.verification_code') }}</label>
            <input id="otp" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" dir="ltr" required>

            <button id="submitBtn" type="submit">{{ __('student.verify') }}</button>
        </form>

        <div id="message" class="message"></div>
    </main>

    <script>
        const form = document.getElementById('attendanceForm');
        const button = document.getElementById('submitBtn');
        const message = document.getElementById('message');
        const timer = document.getElementById('timer');
        const otp = document.getElementById('otp');
        const studentNumber = document.getElementById('student_number');
        const deviceFingerprint = document.getElementById('device_fingerprint');
        const sessionId = {{ (int) ($sessionId ?? 0) }};
        const completedKey = `attendance_completed_${sessionId}`;
        let remaining = {{ max(0, (int) ($remainingSeconds ?? 0)) }};
        let submitted = false;

        const labels = {
            verify: @json(__('student.verify')),
            processing: @json(__('student.processing')),
            fill: @json(__('student.please_fill_all_fields')),
            invalidOtp: @json(__('student.invalid_otp')),
            connection: @json(__('student.connection_error')),
            expired: @json(__('session.token_expired')),
            completed: @json(__('student.attendance_already_submitted')),
        };

        function show(text, type) {
            message.textContent = text;
            message.className = `message ${type}`;
        }

        function lockForm(text) {
            submitted = true;
            studentNumber.disabled = true;
            otp.disabled = true;
            button.disabled = true;
            button.textContent = text;
            show(text, 'success');
        }

        try {
            if (sessionId > 0 && localStorage.getItem(completedKey)) {
                lockForm(labels.completed);
            }
        } catch (error) {
            // Storage can be unavailable in private or restricted browser modes.
        }

        function fallbackDeviceFingerprint() {
            const source = [
                navigator.userAgent || '',
                navigator.platform || '',
                navigator.language || '',
                Intl.DateTimeFormat().resolvedOptions().timeZone || '',
                screen ? `${screen.width}x${screen.height}x${screen.colorDepth}` : '',
            ].join('|');

            let hash = 2166136261;
            for (let index = 0; index < source.length; index++) {
                hash = Math.imul(hash ^ source.charCodeAt(index), 16777619);
            }

            return `web-fallback-${(hash >>> 0).toString(16)}`;
        }

        function resolveDeviceFingerprint() {
            const storageKey = 'student_attendance_device_id';

            try {
                let stored = localStorage.getItem(storageKey);

                if (!stored) {
                    stored = self.crypto && crypto.randomUUID
                        ? crypto.randomUUID()
                        : `device-${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;

                    localStorage.setItem(storageKey, stored);
                }

                return stored;
            } catch (error) {
                return fallbackDeviceFingerprint();
            }
        }

        deviceFingerprint.value = resolveDeviceFingerprint();

        function renderTimer() {
            const safe = Math.max(0, remaining);
            const minutes = String(Math.floor(safe / 60)).padStart(2, '0');
            const seconds = String(safe % 60).padStart(2, '0');
            timer.textContent = `${minutes}:${seconds}`;
        }

        setInterval(() => {
            if (submitted || remaining <= 0) return;
            remaining -= 1;
            renderTimer();
            if (remaining <= 0) {
                button.disabled = true;
                show(labels.expired, 'error');
            }
        }, 1000);

        otp.addEventListener('input', () => {
            otp.value = otp.value.replace(/\D/g, '').slice(0, 6);
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (submitted || button.disabled) return;

            const formData = new FormData(form);
            const studentNumberValue = String(formData.get('student_number') || '').trim();
            const code = String(formData.get('otp') || '').trim();

            if (!studentNumberValue || !code) {
                show(labels.fill, 'error');
                return;
            }

            if (code.length !== 6) {
                show(labels.invalidOtp, 'error');
                return;
            }

            button.disabled = true;
            button.textContent = labels.processing;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json().catch(() => null);

                if (response.ok && data && data.success) {
                    try {
                        if (sessionId > 0) {
                            localStorage.setItem(completedKey, JSON.stringify({
                                student_number: studentNumberValue,
                                completed_at: new Date().toISOString()
                            }));
                        }
                    } catch (error) {
                        // The server-side cookie remains the source of truth.
                    }

                    lockForm(data.message);
                    return;
                }

                show((data && data.message) || labels.connection, 'error');
                button.disabled = false;
                button.textContent = labels.verify;
            } catch (error) {
                show(labels.connection, 'error');
                button.disabled = false;
                button.textContent = labels.verify;
            }
        });
    </script>
</body>
</html>
