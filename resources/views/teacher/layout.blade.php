<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $direction ?? 'rtl' }}">

@php
$isQrPresentation = request()->routeIs('teacher.lecture-session.qr');
@endphp

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', __('teacher.dashboard_title'))</title>

    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">

    <style>
    :root {
        --bg: #f4f7fb;
        --card: #ffffff;
        --sidebar-start: #0f3d91;
        --sidebar-end: #0a2c69;
        --primary: #1d4ed8;
        --text: #0f172a;
        --muted: #64748b;
        --border: #e2e8f0;
        --success-bg: #ecfdf5;
        --success-text: #166534;
        --success-border: #bbf7d0;
        --error-bg: #fef2f2;
        --error-text: #b91c1c;
        --error-border: #fecaca;
        --shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        --radius-lg: 22px;
        --radius-md: 14px;
    }

    * {
        box-sizing: border-box;
    }

    html,
    body {
        margin: 0;
        padding: 0;
        min-height: 100%;
    }

    body {
        font-family: 'Tajawal', sans-serif;
        background:
            radial-gradient(circle at top right, rgba(29, 78, 216, 0.08), transparent 25%),
            linear-gradient(180deg, #f8fbff 0%, var(--bg) 100%);
        color: var(--text);
    }

    .layout {
        min-height: 100vh;
        display: grid;
        grid-template-columns: 290px 1fr;
    }

    .sidebar {
        background: linear-gradient(180deg, var(--sidebar-start) 0%, var(--sidebar-end) 100%);
        color: #fff;
        padding: 28px 22px;
        display: flex;
        flex-direction: column;
        box-shadow: 0 0 30px rgba(2, 6, 23, 0.12);
    }

    .brand {
        text-align: center;
        padding-bottom: 24px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        margin-bottom: 24px;
    }

    .brand-logo {
        width: 88px;
        height: 88px;
        object-fit: contain;
        display: block;
        margin: 0 auto 14px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 20px;
        padding: 10px;
    }

    .brand-title {
        margin: 0;
        font-size: 1.3rem;
        font-weight: 800;
        line-height: 1.4;
    }

    .brand-user {
        margin: 8px 0 0;
        font-size: 0.95rem;
        opacity: 0.9;
    }

    .lang-switch {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin-bottom: 22px;
        font-size: 0.95rem;
    }

    .lang-switch a {
        color: #fff;
        text-decoration: none;
        opacity: 0.92;
    }

    .lang-switch a:hover {
        opacity: 1;
        text-decoration: underline;
    }

    .sidebar-nav {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 4px;
    }

    .nav-link,
    .logout-btn {
        display: block;
        width: 100%;
        border: 0;
        background: transparent;
        color: #fff;
        text-decoration: none;
        padding: 13px 15px;
        border-radius: 14px;
        font-family: inherit;
        font-size: 1rem;
        font-weight: 600;
        transition: background-color 0.2s ease, transform 0.15s ease;
        cursor: pointer;
    }

    .nav-link:hover,
    .logout-btn:hover {
        background: rgba(255, 255, 255, 0.12);
    }

    .nav-link.active {
        background: rgba(255, 255, 255, 0.18);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
    }

    .logout-form {
        margin-top: 8px;
    }

    .logout-btn {
        text-align: start;
    }

    .content {
        padding: 32px;
    }

    .content-shell {
        max-width: 1400px;
        margin: 0 auto;
    }

    .topbar {
        background: rgba(255, 255, 255, 0.72);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 18px;
        padding: 18px 22px;
        margin-bottom: 24px;
        box-shadow: var(--shadow);
    }

    .topbar-title {
        margin: 0;
        font-size: 1.55rem;
        font-weight: 800;
        line-height: 1.4;
    }

    .topbar-subtitle {
        margin: 6px 0 0;
        color: var(--muted);
        font-size: 0.98rem;
    }

    .alert {
        border-radius: var(--radius-md);
        padding: 14px 16px;
        margin-bottom: 16px;
        border: 1px solid transparent;
        font-size: 0.97rem;
        line-height: 1.7;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
    }

    .alert-success {
        background: var(--success-bg);
        color: var(--success-text);
        border-color: var(--success-border);
    }

    .alert-error {
        background: var(--error-bg);
        color: var(--error-text);
        border-color: var(--error-border);
    }

    .page-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 28px;
        box-shadow: var(--shadow);
        min-height: 300px;
    }

    /* ===== QR Presentation Mode ===== */
    .qr-presentation-body {
        background:
            radial-gradient(circle at top right, rgba(37, 99, 235, 0.10), transparent 28%),
            linear-gradient(180deg, #f8fbff 0%, #eef4ff 100%);
    }

    .qr-presentation-layout {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
    }

    .qr-presentation-content {
        width: 100%;
        max-width: 1500px;
        margin: 0 auto;
    }

    .qr-presentation-card {
        min-height: calc(100vh - 48px);
        display: flex;
        align-items: center;
        justify-content: center;
        background: #ffffff;
        border: 1px solid #dbeafe;
        border-radius: 28px;
        box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
        padding: 36px;
    }

    .qr-presentation-card .qr-page,
    .qr-presentation-card .qr-card,
    .qr-presentation-card .qr-wrapper,
    .qr-presentation-card .qr-container {
        width: 100%;
    }

    .qr-presentation-card .qr-box,
    .qr-presentation-card .qr-image,
    .qr-presentation-card .qr-code {
        max-width: 760px !important;
        margin-left: auto !important;
        margin-right: auto !important;
    }

    .qr-presentation-card svg,
    .qr-presentation-card canvas {
        display: block;
        width: min(70vh, 760px) !important;
        height: auto !important;
        max-width: 100% !important;
        margin: 0 auto !important;
    }

    .qr-presentation-card img {
        display: block;
        width: min(70vh, 760px) !important;
        height: auto !important;
        max-width: 100% !important;
        margin: 0 auto !important;
    }

    .qr-presentation-card .otp-code,
    .qr-presentation-card .otp,
    .qr-presentation-card .session-otp {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        min-width: 340px;
        padding: 22px 40px;
        border-radius: 22px;
        background: linear-gradient(135deg, #1d4ed8 0%, #1e3a8a 100%);
        color: #fff;
        font-size: 3.2rem !important;
        font-weight: 800;
        letter-spacing: 12px;
        direction: ltr;
        box-shadow: 0 14px 30px rgba(29, 78, 216, 0.22);
        margin: 20px auto 0 !important;
    }

    .qr-presentation-card h1,
    .qr-presentation-card h2,
    .qr-presentation-card h3 {
        text-align: center;
    }

    .qr-presentation-card p,
    .qr-presentation-card .text-center,
    .qr-presentation-card .otp-label {
        text-align: center;
    }

    @media (max-width: 1199px) {
        .layout {
            grid-template-columns: 250px 1fr;
        }

        .content {
            padding: 24px;
        }

        .qr-presentation-card svg,
        .qr-presentation-card canvas,
        .qr-presentation-card img {
            width: min(62vh, 620px) !important;
        }

        .qr-presentation-card .otp-code,
        .qr-presentation-card .otp,
        .qr-presentation-card .session-otp {
            font-size: 2.5rem !important;
            letter-spacing: 10px;
        }
    }

    @media (max-width: 991px) {
        .layout {
            grid-template-columns: 1fr;
        }

        .sidebar {
            padding: 22px 18px;
        }

        .content {
            padding: 18px;
        }

        .page-card {
            padding: 22px 18px;
        }

        .qr-presentation-layout {
            padding: 16px;
        }

        .qr-presentation-card {
            min-height: calc(100vh - 32px);
            padding: 20px;
            border-radius: 22px;
        }

        .qr-presentation-card svg,
        .qr-presentation-card canvas,
        .qr-presentation-card img {
            width: min(58vh, 500px) !important;
        }

        .qr-presentation-card .otp-code,
        .qr-presentation-card .otp,
        .qr-presentation-card .session-otp {
            min-width: 260px;
            font-size: 2rem !important;
            letter-spacing: 8px;
            padding: 18px 26px;
        }
    }

    @media (max-width: 640px) {
        .brand-logo {
            width: 72px;
            height: 72px;
        }

        .topbar-title {
            font-size: 1.25rem;
        }

        .topbar-subtitle {
            font-size: 0.92rem;
        }

        .qr-presentation-card svg,
        .qr-presentation-card canvas,
        .qr-presentation-card img {
            width: min(82vw, 340px) !important;
        }

        .qr-presentation-card .otp-code,
        .qr-presentation-card .otp,
        .qr-presentation-card .session-otp {
            width: 100%;
            min-width: 0;
            font-size: 1.45rem !important;
            letter-spacing: 6px;
            padding: 16px 18px;
        }
    }
    </style>

    @stack('styles')
</head>

<body class="{{ $isQrPresentation ? 'qr-presentation-body' : '' }}">

    @if($isQrPresentation)
    <main class="qr-presentation-layout">
        <div class="qr-presentation-content">
            @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
            @endif

            @if(session('error'))
            <div class="alert alert-error">
                {{ session('error') }}
            </div>
            @endif

            <div class="qr-presentation-card">
                @yield('content')
            </div>
        </div>
    </main>
    @else
    <div class="layout">
        <aside class="sidebar">
            <div class="brand">
                <img src="{{ asset('images/logo.png') }}" alt="logo" class="brand-logo">

                <h2 class="brand-title">
                    {{ __('teacher.university') }}
                </h2>

                <p class="brand-user">
                    {{ auth()->user()->name }}
                </p>
            </div>

            <div class="lang-switch">
                <a href="{{ route('lang.switch', ['locale' => 'ar']) }}">
                    {{ __('student.arabic') }}
                </a>

                <span>|</span>

                <a href="{{ route('lang.switch', ['locale' => 'en']) }}">
                    {{ __('student.english') }}
                </a>
            </div>

            <nav class="sidebar-nav">
                <a href="{{ route('teacher.dashboard') }}"
                    class="nav-link {{ request()->routeIs('teacher.dashboard') ? 'active' : '' }}">
                    {{ __('teacher.dashboard') }}
                </a>

                <a href="{{ route('teacher.profile') }}"
                    class="nav-link {{ request()->routeIs('teacher.profile') ? 'active' : '' }}">
                    {{ __('teacher.profile_title') }}
                </a>

                <form method="POST" action="{{ route('logout') }}" class="logout-form">
                    @csrf

                    <button type="submit" class="logout-btn">
                        {{ __('teacher.logout') }}
                    </button>
                </form>
            </nav>
        </aside>

        <main class="content">
            <div class="content-shell">
                <div class="topbar">
                    <h1 class="topbar-title">
                        @yield('title', __('teacher.dashboard_title'))
                    </h1>

                    <p class="topbar-subtitle">
                        {{ __('teacher.university') }}
                    </p>
                </div>

                @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
                @endif

                @if(session('error'))
                <div class="alert alert-error">
                    {{ session('error') }}
                </div>
                @endif

                <div class="page-card">
                    @yield('content')
                </div>
            </div>
        </main>
    </div>
    @endif

    @stack('scripts')
</body>

</html>