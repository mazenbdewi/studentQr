@extends('teacher.layout')

@section('title', $seminar->title)

@push('styles')
<style>
.seminar-toolbar{display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:center;margin-bottom:22px}
.seminar-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:22px}
.meta-item{border:1px solid #e2e8f0;border-radius:12px;padding:14px;background:#f8fafc}
.meta-label{display:block;color:#64748b;font-size:.9rem;margin-bottom:5px}.meta-value{font-weight:800;color:#0f172a}
.primary-action,.secondary-action,.danger-action{display:inline-flex;align-items:center;justify-content:center;border-radius:12px;padding:11px 16px;font-family:inherit;font-weight:700;text-decoration:none;cursor:pointer}
.primary-action{border:0;background:#1d4ed8;color:#fff}.secondary-action{border:1px solid #cbd5e1;background:#fff;color:#334155}.danger-action{border:0;background:#dc2626;color:#fff}
.actions{display:flex;gap:10px;flex-wrap:wrap}
.attendance-table{width:100%;border-collapse:collapse;overflow:hidden;border-radius:12px}
.attendance-table th,.attendance-table td{border-bottom:1px solid #e2e8f0;padding:12px;text-align:start;vertical-align:top}
.attendance-table th{background:#f1f5f9;color:#334155;font-weight:800}
.empty-state{border:1px dashed #cbd5e1;border-radius:16px;padding:28px;text-align:center;color:#64748b}
.pagination-wrap{margin-top:18px}
@media(max-width:760px){.attendance-table{display:block;overflow-x:auto;white-space:nowrap}}
</style>
@endpush

@section('content')
<div class="seminar-toolbar">
    <div>
        <strong>{{ $seminar->title }}</strong>
        <div style="color:#64748b;margin-top:6px">{{ $seminar->description }}</div>
    </div>

    <div class="actions">
        <a class="secondary-action" href="{{ route('teacher.seminars.index') }}">{{ __('teacher.back') }}</a>
        <a class="secondary-action" href="{{ route('teacher.seminars.export', $seminar) }}">{{ __('teacher.export_csv') }}</a>

        @if($seminar->status === 'active' && ! $seminar->qr_expired && $seminar->qr_token)
            <a class="primary-action" href="{{ route('teacher.seminars.qr', $seminar) }}" target="_blank" rel="noopener">{{ __('teacher.show_qr') }}</a>
            <button class="danger-action" type="button" onclick="stopSeminarQr()">{{ __('teacher.stop_qr') }}</button>
        @else
            <form method="POST" action="{{ route('teacher.seminars.start', $seminar) }}" target="_blank">
                @csrf
                <button class="primary-action" type="submit">{{ __('teacher.start_qr') }}</button>
            </form>
        @endif
    </div>
</div>

<div class="seminar-meta">
    <div class="meta-item">
        <span class="meta-label">{{ __('teacher.audience_type') }}</span>
        <span class="meta-value">{{ $seminar->audience_type ?: __('teacher.general_audience') }}</span>
    </div>
    <div class="meta-item">
        <span class="meta-label">{{ __('teacher.location') }}</span>
        <span class="meta-value">{{ $seminar->location ?: __('teacher.location_not_set') }}</span>
    </div>
    <div class="meta-item">
        <span class="meta-label">{{ __('teacher.status') }}</span>
        <span class="meta-value">{{ __('teacher.seminar_status_'.$seminar->status) }}</span>
    </div>
    <div class="meta-item">
        <span class="meta-label">{{ __('teacher.attendees') }}</span>
        <span class="meta-value">{{ $seminar->attendances()->count() }}</span>
    </div>
</div>

@if($attendances->count())
    <table class="attendance-table">
        <thead>
            <tr>
                <th>{{ __('teacher.full_name') }}</th>
                @if($seminar->collect_specialization)<th>{{ __('teacher.specialization') }}</th>@endif
                @if($seminar->collect_profession)<th>{{ __('teacher.profession') }}</th>@endif
                @if($seminar->collect_academic_rank)<th>{{ __('teacher.academic_rank') }}</th>@endif
                @if($seminar->collect_age)<th>{{ __('teacher.age') }}</th>@endif
                @if($seminar->collect_organization)<th>{{ __('teacher.organization') }}</th>@endif
                @if($seminar->collect_phone)<th>{{ __('teacher.phone') }}</th>@endif
                @if($seminar->collect_email)<th>{{ __('teacher.email') }}</th>@endif
                <th>{{ __('teacher.attended_at') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($attendances as $attendance)
                <tr>
                    <td>{{ $attendance->full_name }}</td>
                    @if($seminar->collect_specialization)<td>{{ $attendance->specialization }}</td>@endif
                    @if($seminar->collect_profession)<td>{{ $attendance->profession }}</td>@endif
                    @if($seminar->collect_academic_rank)<td>{{ $attendance->academic_rank }}</td>@endif
                    @if($seminar->collect_age)<td>{{ $attendance->age }}</td>@endif
                    @if($seminar->collect_organization)<td>{{ $attendance->organization }}</td>@endif
                    @if($seminar->collect_phone)<td dir="ltr">{{ $attendance->phone }}</td>@endif
                    @if($seminar->collect_email)<td dir="ltr">{{ $attendance->email }}</td>@endif
                    <td>{{ optional($attendance->attended_at)->format('Y-m-d H:i') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="pagination-wrap">
        {{ $attendances->links() }}
    </div>
@else
    <div class="empty-state">{{ __('teacher.no_seminar_attendance') }}</div>
@endif

@if($seminar->status === 'active' && ! $seminar->qr_expired)
<script>
function stopSeminarQr() {
    fetch('{{ route('teacher.seminars.expire-qr', $seminar) }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    }).then(function() {
        window.location.reload();
    });
}
</script>
@endif
@endsection
