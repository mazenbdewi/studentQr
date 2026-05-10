@extends('teacher.layout')

@section('title', __('teacher.seminars'))

@push('styles')
<style>
.seminar-header{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:22px;flex-wrap:wrap}
.primary-action,.secondary-action{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:12px;padding:11px 18px;font-family:inherit;font-weight:700;text-decoration:none;cursor:pointer}
.primary-action{background:#1d4ed8;color:#fff}
.secondary-action{background:#eef2ff;color:#1e3a8a;border:1px solid #c7d2fe}
.seminar-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
.seminar-card{border:1px solid #e2e8f0;border-radius:14px;padding:18px;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,.05)}
.seminar-title{font-size:1.1rem;font-weight:800;margin:0 0 8px}
.seminar-meta{color:#64748b;line-height:1.7;font-size:.95rem;margin:0 0 14px}
.status-pill{display:inline-flex;border-radius:999px;padding:5px 11px;font-size:.85rem;font-weight:700;background:#f1f5f9;color:#334155}
.status-pill.active{background:#dcfce7;color:#166534}.status-pill.completed{background:#e0e7ff;color:#3730a3}.status-pill.cancelled{background:#fee2e2;color:#991b1b}
.card-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
.empty-state{border:1px dashed #cbd5e1;border-radius:16px;padding:32px;text-align:center;color:#64748b}
.pagination-wrap{margin-top:20px}
</style>
@endpush

@section('content')
<div class="seminar-header">
    <div>
        <strong>{{ __('teacher.seminars') }}</strong>
        <p class="seminar-meta">{{ __('teacher.seminars_hint') }}</p>
    </div>

    <a class="primary-action" href="{{ route('teacher.seminars.create') }}">
        {{ __('teacher.create_seminar') }}
    </a>
</div>

@if($seminars->count())
    <div class="seminar-grid">
        @foreach($seminars as $seminar)
            <article class="seminar-card">
                <h2 class="seminar-title">{{ $seminar->title }}</h2>
                <p class="seminar-meta">
                    {{ $seminar->audience_type ?: __('teacher.general_audience') }}<br>
                    {{ $seminar->location ?: __('teacher.location_not_set') }}<br>
                    {{ __('teacher.attendees_count', ['count' => $seminar->attendances_count]) }}
                </p>

                <span class="status-pill {{ $seminar->status }}">
                    {{ __('teacher.seminar_status_'.$seminar->status) }}
                </span>

                <div class="card-actions">
                    <a class="secondary-action" href="{{ route('teacher.seminars.show', $seminar) }}">
                        {{ __('teacher.view') }}
                    </a>

                    @if($seminar->status === 'active' && ! $seminar->qr_expired && $seminar->qr_token)
                        <a class="primary-action" href="{{ route('teacher.seminars.qr', $seminar) }}" target="_blank" rel="noopener">
                            {{ __('teacher.show_qr') }}
                        </a>
                    @else
                        <form method="POST" action="{{ route('teacher.seminars.start', $seminar) }}" target="_blank">
                            @csrf
                            <button class="primary-action" type="submit">
                                {{ __('teacher.start_qr') }}
                            </button>
                        </form>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    <div class="pagination-wrap">
        {{ $seminars->links() }}
    </div>
@else
    <div class="empty-state">
        {{ __('teacher.no_seminars_yet') }}
    </div>
@endif
@endsection
