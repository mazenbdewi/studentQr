@extends('teacher.layout')

@section('title', __('teacher.qr_code'))

@section('content')

<div class="flex flex-col items-center bg-white p-8 rounded-2xl shadow mt-8">

    <h2 class="text-xl font-bold mb-4">
        {{ __('teacher.session_qr') }}
    </h2>

    @if(isset($expired) && $expired)
    <div class="flex flex-col items-center justify-center text-red-600 py-12">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-24 h-24 mb-4" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01m-5.07 2h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
        </svg>
        <p class="text-2xl font-bold mb-4">{{ __('teacher.qr_expired') }}</p>
        <p class="text-gray-500">{{ __('teacher.qr_expired_message') }}</p>
    </div>
    @else
    <!-- Countdown Timer - positioned above QR code -->
    <div id="floating-countdown"
        class="mb-4 bg-gradient-to-r from-orange-50 to-red-50 border-2 border-orange-400 rounded-xl shadow-lg px-6 py-3">
        <div class="flex flex-col items-center">
            <span class="text-sm font-medium text-gray-600">{{ __('teacher.qr_expires_in') }}</span>
            <span id="timer"
                class="text-4xl font-extrabold text-orange-600 tabular-nums">{{ $session->qr_refresh_rate }}</span>
        </div>
    </div>

    <div id="qr-container" class="mb-6">
        <img src="{{ $qr }}" alt="QR Code" class="w-64 h-64">
    </div>

    <p id="otp-label" class="text-gray-500 text-sm mt-4">
        {{ __('teacher.verification_code') }}
    </p>

    <p id="otp-code" class="text-8xl font-bold tracking-widest text-blue-600 mt-2">
        {{ $otp }}
    </p>
    @endif

</div>

@if(!isset($expired) || !$expired)
<script>
var timeLeft = {{ json_encode($session->qr_refresh_rate) }};
var timerElement = document.getElementById('timer');
var countdownInterval;

function startCountdown() {
    countdownInterval = setInterval(function() {
        timeLeft--;
        timerElement.textContent = timeLeft;

        if (timeLeft <= 10) {
            timerElement.classList.add('text-red-600');
            timerElement.classList.remove('text-orange-600');
        }

        if (timeLeft <= 0) {
            clearInterval(countdownInterval);

            // Replace QR code with expired message
            document.getElementById('qr-container').innerHTML =
                '<div class="flex flex-col items-center justify-center text-red-600 py-8">' +
                '<svg xmlns="http://www.w3.org/2000/svg" class="w-24 h-24 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">' +
                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-5.07 2h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />' +
                '</svg>' +
                '<p class="text-2xl font-bold">{{ __("teacher.qr_expired") }}</p>' +
                '<p class="text-gray-500 mt-2">{{ __("teacher.qr_expired_message") }}</p>' +
                '</div>';

            document.getElementById('otp-label').style.display = 'none';
            document.getElementById('otp-code').style.display = 'none';
            document.getElementById('floating-countdown').style.display = 'none';

            // Mark QR as expired on the server so it won't be generated again on refresh
            fetch('/teacher/session/{{ $session->id }}/expire-qr', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            }).then(function() {
                // Wait 2 seconds then try to close window or redirect
                setTimeout(function() {
                    window.close();
                    window.location.href =
                        '{{ \App\Filament\Resources\LectureSessions\LectureSessionResource::getUrl("index") }}';
                }, 2000);
            }).catch(function(err) {
                console.log('Failed to mark QR as expired:', err);
                setTimeout(function() {
                    window.close();
                    window.location.href =
                        '{{ \App\Filament\Resources\LectureSessions\LectureSessionResource::getUrl("index") }}';
                }, 2000);
            });
        }
    }, 1000);
}

function stopCountdown() {
    clearInterval(countdownInterval);
}

startCountdown();

// Also check session status periodically
setInterval(function() {
    fetch('/teacher/session/{{ $session->id }}/status')
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (!data.active) {
                stopCountdown();
                document.getElementById('qr-container').innerHTML =
                    '<div class="flex flex-col items-center justify-center text-red-600 py-8">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" class="w-24 h-24 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-5.07 2h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />' +
                    '</svg>' +
                    '<p class="text-2xl font-bold">{{ __("teacher.qr_expired") }}</p>' +
                    '<p class="text-gray-500 mt-2">{{ __("teacher.qr_expired_message") }}</p>' +
                    '</div>';
                document.getElementById('otp-label').style.display = 'none';
                document.getElementById('otp-code').style.display = 'none';
                document.getElementById('floating-countdown').style.display = 'none';

                fetch('/teacher/session/{{ $session->id }}/expire-qr', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    }
                }).then(function() {
                    setTimeout(function() {
                        window.close();
                        window.location.href =
                            '{{ \App\Filament\Resources\LectureSessions\LectureSessionResource::getUrl("index") }}';
                    }, 2000);
                }).catch(function(err) {
                    console.log('Failed to mark QR as expired:', err);
                    setTimeout(function() {
                        window.close();
                        window.location.href =
                            '{{ \App\Filament\Resources\LectureSessions\LectureSessionResource::getUrl("index") }}';
                    }, 2000);
                });
            }
        })
        .catch(function(err) {
            console.log('Session check failed:', err);
        });
}, 5000);
</script>
@endif

@endsection