@extends('teacher.layout')

@section('title', __('teacher.seminar_qr'))

@section('content')
<div class="flex flex-col items-center bg-white p-8 rounded-2xl shadow mt-8">
    <h2 class="text-xl font-bold mb-4">{{ $seminar->title }}</h2>

    @if(isset($expired) && $expired)
        <div class="flex flex-col items-center justify-center text-red-600 py-12">
            <p class="text-2xl font-bold mb-4">{{ __('teacher.seminar_qr_expired') }}</p>
            <p class="text-gray-500">{{ __('teacher.qr_expired_message') }}</p>
        </div>
    @else
        <div id="qr-container" class="qr-container mb-6">
            <img src="{{ $qr }}" alt="QR Code" class="qr-image w-64 h-64">
        </div>

        <p class="text-gray-500 text-sm mt-4">
            {{ __('teacher.seminar_qr_hint') }}
        </p>

        <p id="attendee-count" class="text-4xl font-bold text-blue-600 mt-2">
            {{ $seminar->attendances()->count() }}
        </p>
    @endif
</div>

@if(!isset($expired) || !$expired)
<script>
setInterval(function() {
    fetch('{{ route('teacher.seminars.status', $seminar) }}')
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (!data.active) {
                window.location.reload();
            }
        });
}, 5000);
</script>
@endif
@endsection
