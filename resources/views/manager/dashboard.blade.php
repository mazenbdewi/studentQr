@extends('manager.layout')

@section('title', __('manager.dashboard_title'))

@push('styles')
<style>
:root {
    --primary: #2563eb;
    --primary-dark: #1d4ed8;
    --success: #16a34a;
    --success-dark: #15803d;
    --indigo: #4f46e5;
    --indigo-dark: #4338ca;
    --gray-50: #f8fafc;
    --gray-100: #f1f5f9;
    --gray-200: #e2e8f0;
    --gray-300: #cbd5e1;
    --gray-600: #475569;
    --gray-700: #334155;
    --gray-800: #1e293b;
    --gray-900: #0f172a;
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
    --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
    --radius-lg: 1rem;
    --radius-xl: 1.5rem;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px;
    margin-bottom: 32px;
}

.stat-card {
    background: white;
    border-radius: var(--radius-xl);
    padding: 24px 20px;
    box-shadow: var(--shadow-md);
    transition: all 0.3s ease;
    border: 1px solid var(--gray-200);
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-xl);
    border-color: var(--gray-300);
}

.stat-label {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.02em;
    margin-bottom: 8px;
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 800;
    line-height: 1.2;
}

.stat-value.blue {
    color: var(--primary);
}

.stat-value.green {
    color: var(--success);
}

.stat-value.indigo {
    color: var(--indigo);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 9999px;
    font-size: 0.9rem;
    font-weight: 600;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
}

.status-badge .dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: currentColor;
}

 
.admin-button {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 14px 32px;
    background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
    color: white;
    border-radius: 9999px;
    font-weight: 700;
    font-size: 1rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: var(--shadow-md);
    text-decoration: none;
}

.admin-button:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-xl);
    background: linear-gradient(135deg, #1d4ed8 0%, #1e3a8a 100%);
}

.admin-button svg {
    width: 22px;
    height: 22px;
    transition: transform 0.2s ease;
}

.admin-button:hover svg {
    transform: rotate(90deg);
}
 
[dir="rtl"] .admin-button svg {
    transform: scaleX(-1);
}

[dir="rtl"] .admin-button:hover svg {
    transform: rotate(-90deg) scaleX(-1);
}
 
@media (max-width: 1024px) {
    .dashboard-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
}

@media (max-width: 640px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .stat-value {
        font-size: 2rem;
    }

    .admin-button {
        width: 100%;
        justify-content: center;
    }
}

.mt-8 {
    margin-top: 32px;
}

.flex {
    display: flex;
}

.justify-end {
    justify-content: flex-end;
}
</style>
@endpush

@section('content')
<div class="dashboard-grid">
    
    <div class="stat-card">
        <div class="stat-label">{{ __('manager.my_sessions') }}</div>
        <div class="stat-value blue">{{ $sessionsCount ?? 0 }}</div>
    </div>

    
    <div class="stat-card">
        <div class="stat-label">{{ __('manager.today_attendance') }}</div>
        <div class="stat-value green">{{ $todayAttendance ?? 0 }}</div>
    </div>

    
    <div class="stat-card">
        <div class="stat-label">{{ __('manager.total_students') }}</div>
        <div class="stat-value indigo">{{ $totalStudents ?? 0 }}</div>
    </div>
 
    <div class="stat-card">
        <div class="stat-label">{{ __('manager.active_session') }}</div>
        <div class="mt-4">
         
            <span class="status-badge">
                {{ __('manager.none') ?? 'لا توجد' }}
            </span>
        </div>
    </div>
</div>

@if(auth()->user()->hasAnyRole(['super_admin', 'course_lecturer', 'manager']))
    <div class="flex justify-end mt-8">
        <a href="/admin" class="admin-button">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                </path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z">
                </path>
            </svg>
            {{ __('manager.go_admin_panel') }}
        </a>
    </div>
@endif
@endsection