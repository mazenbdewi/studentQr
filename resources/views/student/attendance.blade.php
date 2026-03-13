@extends('student.layout')

@section('title', __('student.confirm_attendance'))

@push('styles')
<style>
/* Session Info Card */
.session-info {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 16px;
    color: white;
    margin-bottom: 20px;
}

.session-info .subject-name {
    font-size: 1.25rem;
    font-weight: bold;
    margin-bottom: 8px;
    text-align: center;
}

.session-info .info-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    opacity: 0.95;
    margin-top: 6px;
}

.session-info .info-icon {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

/* Countdown Timer - Mobile First */
.countdown-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 16px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    margin-top: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.countdown-timer {
    font-size: 1.5rem;
    font-weight: bold;
    font-family: 'Courier New', monospace;
    color: #fff;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.countdown-timer.urgent {
    color: #fca5a5;
    animation: pulse 1s infinite;
}

.countdown-timer.expired {
    color: #fca5a5;
}

.countdown-label {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.9);
    font-weight: 500;
}

@keyframes pulse {

    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0.6;
    }
}

/* Progress Bar */
.progress-bar {
    width: 100%;
    height: 6px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
    overflow: hidden;
    margin-top: 14px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #fff, rgba(255, 255, 255, 0.8));
    transition: width 1s linear;
    border-radius: 3px;
}

.progress-fill.urgent {
    background: linear-gradient(90deg, #f59e0b, #ef4444);
}

/* Form Styling */
.form-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    margin-top: 16px;
}

.field {
    margin-bottom: 16px;
}

.label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    font-size: 0.95rem;
}

.label svg {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}

.input {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.2s;
    background: #f9fafb;
    height: 52px;
}

.input:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.input.is-invalid {
    border-color: #ef4444;
    background: #fef2f2;
}

.field-error {
    color: #ef4444;
    font-size: 0.875rem;
    margin-top: 6px;
}

/* Submit Button */
.submit-btn {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 8px;
}

.submit-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.submit-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Loading & Status */
.loading-container {
    text-align: center;
    padding: 24px;
}

.spinner-large {
    width: 48px;
    height: 48px;
    border: 4px solid #e5e7eb;
    border-top-color: #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 16px;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.loading-text {
    color: #6b7280;
    font-size: 1rem;
}

.loading-subtext {
    color: #9ca3af;
    font-size: 0.875rem;
    margin-top: 8px;
}

/* Status Messages */
.status-container {
    margin-top: 20px;
    padding: 20px;
    border-radius: 12px;
    text-align: center;
}

.status-container.success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.status-container.error {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.status-container.processing {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
}

.status-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 12px;
}

.status-title {
    font-size: 1.25rem;
    font-weight: bold;
    margin-bottom: 4px;
}

.status-message-text {
    font-size: 0.95rem;
    opacity: 0.95;
}

/* Connection Status */
.connection-status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 16px;
}

.connection-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
}

.connection-dot.offline {
    background: #ef4444;
}

/* Alerts */
.alert {
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 16px;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #34d399;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #f87171;
}

.alert ul {
    margin: 0;
    padding-left: 20px;
}

/* No Session State */
.no-session {
    text-align: center;
    padding: 48px 24px;
    color: #6b7280;
}

.no-session-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    opacity: 0.5;
}

.no-session h2 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 8px;
    color: #374151;
}

.no-session p {
    font-size: 0.95rem;
}

/* Attendance Success Details */
.success-details {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    padding: 12px;
    margin-top: 16px;
    font-size: 0.875rem;
}

.success-details .row {
    display: flex;
    justify-content: space-between;
}

/* Footer Note */
.footer-note {
    text-align: center;
    color: #6b7280;
    font-size: 0.8rem;
    margin-top: 12px;
}

/* Mobile Optimizations */
@media (max-width: 480px) {
    .session-info {
        padding: 14px;
    }

    .session-info .subject-name {
        font-size: 1.1rem;
    }

    .session-info .info-row {
        font-size: 0.85rem;
    }

    .countdown-container {
        padding: 12px 14px;
    }

    .countdown-timer {
        font-size: 1.3rem;
    }

    .countdown-label {
        font-size: 0.8rem;
    }

    .form-card {
        padding: 16px;
        border-radius: 12px;
    }

    .input {
        height: 48px;
        font-size: 16px; /* Prevents iOS zoom on focus */
    }

    .submit-btn {
        height: 50px;
        font-size: 1rem;
    }

    .label {
        font-size: 0.9rem;
    }

    .label svg {
        width: 16px;
        height: 16px;
    }

    .status-container {
        padding: 16px;
    }

    .status-icon {
        width: 40px;
        height: 40px;
    }

    .status-title {
        font-size: 1.1rem;
    }
}

/* Tablet and Desktop */
@media (min-width: 768px) {
    .session-info {
        padding: 24px;
    }

    .session-info .subject-name {
        font-size: 1.5rem;
    }

    .session-info .info-icon {
        width: 20px;
        height: 20px;
    }

    .countdown-timer {
        font-size: 1.75rem;
    }

    .form-card {
        padding: 32px;
    }

    .input {
        height: 56px;
    }

    .submit-btn {
        height: 56px;
    }
}
.countdown-timer {
    font-size: 1.5rem;
    font-weight: bold;
    font-family: 'Courier New', monospace;
    color: #fff;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    direction: ltr;
    unicode-bidi: isolate;
    display: inline-block;
    min-width: 5ch;
    text-align: center;
    letter-spacing: 0.04em;
}
.countdown-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 16px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    margin-top: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    direction: ltr;
}
</style>
@endpush

@section('content')
{{-- Header --}}
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

{{-- Session Details (if available) --}}
@if(isset($sessionDetails) && $sessionDetails)
<div class="session-info" id="sessionInfo">
    <div class="subject-name">
        {{ $sessionDetails->subject?->name ?? __('student.attendance_title') }}
    </div>

    @if($sessionDetails->lecturer)
    <div class="info-row">
        <svg class="info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
        <span>{{ $sessionDetails->lecturer->name }}</span>
    </div>
    @endif

    @if($sessionDetails->hall)
    <div class="info-row">
        <svg class="info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <span>{{ $sessionDetails->hall->name }}</span>
    </div>
    @endif

    @if($sessionDetails->session_date)
    <div class="info-row">
        <svg class="info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <span>{{ \Carbon\Carbon::parse($sessionDetails->session_date)->format('Y-m-d') }}</span>
    </div>
    @endif

    {{-- Countdown Timer --}}
    <div class="countdown-container" id="countdownContainer">
        <svg class="info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="countdown-timer" id="countdownTimer" dir="ltr">--:--</span>
        <span class="countdown-label">{{ __('student.time_remaining') }}</span>
    </div>

    <div class="progress-bar">
        <div class="progress-fill" id="progressFill"></div>
    </div>
</div>
@endif

{{-- Messages --}}
<div class="messages" id="messages">
    @if (session('success'))
    <div class="alert alert-success" data-session-success="true">
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

{{-- Attendance Form --}}
<form method="POST" action="{{ route('student.attendance.store.sync', ['session' => $sessionId ?? 0]) }}" class="form-card"
    id="attendanceForm" novalidate>
    @csrf

    <div class="field">
        <label for="student_number" class="label">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
            </svg>
            {{ __('student.student_number') }}
        </label>

        <input id="student_number" name="student_number" type="text" value="{{ old('student_number') }}"
            class="input {{ $errors->has('student_number') ? 'is-invalid' : '' }}"
            placeholder="{{ __('student.student_number') }}" autocomplete="off" inputmode="numeric" dir="ltr"
            aria-invalid="{{ $errors->has('student_number') ? 'true' : 'false' }}" autofocus required>

        @error('student_number')
        <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field">
        <label for="otp" class="label">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 7a2 2 0 012 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
            {{ __('student.verification_code') }}
        </label>

        <input id="otp" name="otp" type="text" value="{{ old('otp') }}"
            class="input {{ $errors->has('otp') ? 'is-invalid' : '' }}" placeholder="******"
            autocomplete="one-time-code" inputmode="numeric" dir="ltr" maxlength="6"
            aria-invalid="{{ $errors->has('otp') ? 'true' : 'false' }}" required>

        @error('otp')
        <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <button type="submit" class="submit-btn" id="submitBtn">
        <span id="submitText">{{ __('student.verify') }}</span>
    </button>

    <div class="footer-note">
        {{ __('student.enter_student_number_and_code') }}
    </div>

    {{-- Connection Status --}}
    <div class="connection-status" id="connectionStatus">
        <span class="connection-dot" id="connectionDot"></span>
        <span id="connectionText">{{ __('student.online') }}</span>
    </div>

    {{-- Loading Indicator --}}
    <div class="loading-container" id="loadingIndicator" style="display: none;">
        <div class="spinner-large"></div>
        <div class="loading-text">{{ __('student.checking') }}</div>
        <div class="loading-subtext" id="loadingSubtext">{{ __('student.please_wait') }}</div>
    </div>

    {{-- Status Message --}}
    <div class="status-container" id="statusContainer" style="display: none;">
        <div class="status-icon" id="statusIcon"></div>
        <div class="status-title" id="statusTitle"></div>
        <div class="status-message-text" id="statusMessageText"></div>
        <div class="success-details" id="successDetails" style="display: none;">
            <div class="row">
                <span>{{ __('student.attendance_time') }}:</span>
                <span id="attendanceTime"></span>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('attendanceForm');
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const loadingSubtext = document.getElementById('loadingSubtext');
    const statusContainer = document.getElementById('statusContainer');
    const statusIcon = document.getElementById('statusIcon');
    const statusTitle = document.getElementById('statusTitle');
    const statusMessageText = document.getElementById('statusMessageText');
    const successDetails = document.getElementById('successDetails');
    const attendanceTime = document.getElementById('attendanceTime');
    const messagesDiv = document.getElementById('messages');
    const studentNumberInput = document.getElementById('student_number');
    const otpInput = document.getElementById('otp');
    const connectionDot = document.getElementById('connectionDot');
    const connectionText = document.getElementById('connectionText');
    const countdownTimer = document.getElementById('countdownTimer');
    const progressFill = document.getElementById('progressFill');

    const sessionId = {{ $sessionId ?? 0 }};
    const qrRefreshRate = {{ $sessionDetails?->qr_refresh_rate ?? 120 }};
    
    // Safely handle remaining seconds - ensure it's a valid positive number
    let serverRemainingSeconds = {{ $remainingSeconds ?? 'null' }};
    
    // Validate and sanitize remaining seconds
    if (serverRemainingSeconds === null || serverRemainingSeconds === undefined || isNaN(serverRemainingSeconds) || serverRemainingSeconds < 0) {
        serverRemainingSeconds = qrRefreshRate;
    }
    
    const initialRemainingSeconds = serverRemainingSeconds;

    let isSubmitting = false;
    let pollingInterval = null;
    let countdownInterval = null;
    let remainingSeconds = initialRemainingSeconds;

    // Initialize countdown only if we have valid session data
    if (sessionId > 0 && qrRefreshRate > 0 && initialRemainingSeconds > 0) {
        initCountdown();
    } else if (sessionId > 0 && initialRemainingSeconds <= 0) {
        // Session exists but timer expired - show the form but with expired state
        if (countdownTimer) {
            countdownTimer.textContent = '00:00';
        }
        if (progressFill) {
            progressFill.style.width = '0%';
        }
    }

    function initCountdown() {
        remainingSeconds = initialRemainingSeconds;
        updateCountdownDisplay();

        countdownInterval = setInterval(() => {
            remainingSeconds--;
            updateCountdownDisplay();

            if (remainingSeconds <= 0) {
                clearInterval(countdownInterval);
                showStatus('{{ __('session.token_expired') }}', 'error', 'expired');
                submitBtn.disabled = true;
            }
        }, 1000);
    }

    function updateCountdownDisplay() {
        if (!countdownTimer) return;

        // Ensure remainingSeconds is a valid non-negative number
        if (isNaN(remainingSeconds) || remainingSeconds < 0) {
            remainingSeconds = 0;
        }

        const minutes = Math.floor(remainingSeconds / 60);
        const seconds = remainingSeconds % 60;
        
        // Format with leading zeros - ensure we don't get malformed output like "1-:1-"
        const formattedMinutes = isNaN(minutes) ? '00' : minutes.toString().padStart(2, '0');
        const formattedSeconds = isNaN(seconds) ? '00' : seconds.toString().padStart(2, '0');
        
        countdownTimer.textContent = `${formattedMinutes}:${formattedSeconds}`;

        if (progressFill && qrRefreshRate > 0) {
            const percentage = (remainingSeconds / qrRefreshRate) * 100;
            progressFill.style.width = Math.max(0, Math.min(100, percentage)) + '%';

            if (remainingSeconds <= 10) {
                countdownTimer.classList.add('urgent');
                progressFill.classList.add('urgent');
            } else {
                countdownTimer.classList.remove('urgent');
                progressFill.classList.remove('urgent');
            }
        }

        if (remainingSeconds <= 10) {
            countdownTimer.classList.add('urgent');
        } else {
            countdownTimer.classList.remove('urgent');
        }
    }

    function updateConnectionStatus(online) {
        if (connectionDot) {
            connectionDot.classList.toggle('offline', !online);
        }
        if (connectionText) {
        connectionText.textContent = online ? '{{ __('student.online') }}': '{{ __('student.offline') }}';
        }
    }

    window.addEventListener('online', () => updateConnectionStatus(true));
    window.addEventListener('offline', () => updateConnectionStatus(false));
    updateConnectionStatus(navigator.onLine);

    otpInput.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);

        if (this.value.length === 6 && studentNumberInput.value.length > 0) {
            // Optional: auto-submit
            // form.requestSubmit();
        }
    });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        if (isSubmitting) return;

        hideStatus();

        const studentNumber = studentNumberInput.value.trim();
        const otp = otpInput.value.trim();

        if (!studentNumber || !otp) {
            showStatus('{{ __('student.please_fill_all_fields') }}', 'error');
            return;
        }

        if (otp.length !== 6) {
            showStatus('{{ __('student.invalid_otp') }}', 'error');
            return;
        }

        isSubmitting = true;
        submitBtn.disabled = true;
        submitText.textContent = '{{ __('student.processing') }}';
        loadingIndicator.style.display = 'block';
        statusContainer.style.display = 'none';

        try {
            const formData = new FormData();
            formData.append('student_number', studentNumber);
            formData.append('otp', otp);
            formData.append('_token', '{{ csrf_token() }}');

            const response = await fetch('{{ route('student.attendance.store.sync', ['session' => $sessionId ?? 0]) }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'text/html'
                }
            });

            const html = await response.text();

            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            const successAlert = doc.querySelector('.alert-success');
            const errorAlert = doc.querySelector('.alert-error');

            loadingIndicator.style.display = 'none';
            submitBtn.disabled = false;
            submitText.textContent = '{{ __('student.verify') }}';

            if (successAlert && !errorAlert) {
                const successMessage = successAlert.textContent.trim();

             showStatus(successMessage, 'success');
studentNumberInput.value = '';
otpInput.value = '';
studentNumberInput.focus();
            } else if (errorAlert) {
                const errorMessage = errorAlert.textContent.trim();
                showStatus(errorMessage, 'error');
            } else {
                const sessionSuccess = doc.querySelector('[data-session-success]');
                if (sessionSuccess) {
                    showStatus(sessionSuccess.textContent.trim(), 'success');
                    studentNumberInput.value = '';
                    otpInput.value = '';
                } else {
                    showStatus('{{ __('student.connection_error') }}', 'error');
                }
            }

        } catch (error) {
            console.error('Error:', error);
            loadingIndicator.style.display = 'none';
            submitBtn.disabled = false;
            submitText.textContent = '{{ __('student.verify') }}';
            showStatus('{{ __('student.connection_error') }}', 'error');
            updateConnectionStatus(false);
        } finally {
            isSubmitting = false;
        }
    });

    function startPolling(studentNumber) {
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }

        let pollCount = 0;
        const maxPolls = 30;

        pollingInterval = setInterval(async () => {
            pollCount++;

            if (pollCount > maxPolls) {
                clearInterval(pollingInterval);
                showStatus('{{ __('student.timeout_waiting') }}', 'error');
                return;
            }

            try {
                const response = await fetch(
                    `{{ route('student.attendance.check.status', ['session' => $sessionId ?? 0]) }}?student_number=${encodeURIComponent(studentNumber)}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }
                );

                const data = await response.json();

                if (data.success) {
                    if (data.status === 'recorded') {
                        clearInterval(pollingInterval);
                        showSuccessStatus(data.message, data.attendance_time);
                        studentNumberInput.value = '';
                        otpInput.value = '';
                        studentNumberInput.focus();
                    } else if (data.status === 'failed') {
                        clearInterval(pollingInterval);
                        showStatus(data.message, 'error');
                    } else {
                        if (data.remaining_seconds !== undefined) {
                            remainingSeconds = data.remaining_seconds;
                            updateCountdownDisplay();
                        }
                        loadingSubtext.textContent =
                            `{{ __('student.checking') }} (${pollCount * 2}s)`;
                    }
                }
            } catch (error) {
                console.error('Polling error:', error);
            }
        }, 2000);
    }

    function showStatus(message, type) {
        statusContainer.style.display = 'block';
        statusContainer.className = 'status-container ' + type;

        let iconSvg = '';
        if (type === 'success') {
            iconSvg = `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>`;
        } else if (type === 'error') {
            iconSvg = `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>`;
        } else {
            iconSvg = `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>`;
        }

        statusIcon.innerHTML = iconSvg;
        statusTitle.textContent = type === 'success' ? '{{ __('student.success') }}':
            type === 'error' ? '{{ __('student.error_prefix') }}':
            '{{ __('student.processing') }}';
        statusMessageText.textContent = message;
        successDetails.style.display = 'none';
    }

    function showSuccessStatus(message, attendanceTimeIso) {
        statusContainer.style.display = 'block';
        statusContainer.className = 'status-container success';

        const iconSvg = `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>`;

        statusIcon.innerHTML = iconSvg;
        statusTitle.textContent = '{{ __('student.success') }}';
        statusMessageText.textContent = message;

        if (attendanceTimeIso) {
            const date = new Date(attendanceTimeIso);
            const timeStr = date.toLocaleTimeString();
            attendanceTime.textContent = timeStr;
            successDetails.style.display = 'block';
        }

        if (countdownInterval) {
            clearInterval(countdownInterval);
        }
    }

    function hideStatus() {
        statusContainer.style.display = 'none';
    }

    form.addEventListener('submit', function(e) {
        if (isSubmitting) {
            e.preventDefault();
            return false;
        }
    });

    window.addEventListener('beforeunload', function() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }
        if (countdownInterval) {
            clearInterval(countdownInterval);
        }
    });
});
</script>
@endpush
@endsection