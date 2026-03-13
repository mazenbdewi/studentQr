<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', __('manager.dashboard_title'))</title>

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

    @media (max-width: 1199px) {
        .layout {
            grid-template-columns: 250px 1fr;
        }

        .content {
            padding: 24px;
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
    }
    </style>

    @stack('styles')
</head>

<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="brand">
                <img src="{{ asset('images/logo.png') }}" alt="logo" class="brand-logo">

                <h2 class="brand-title">
                    {{ __('manager.university') }}
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
                <a href="{{ route('manager.dashboard') }}"
                    class="nav-link {{ request()->routeIs('manager.dashboard') ? 'active' : '' }}">
                    {{ __('manager.dashboard') }}
                </a>

                <a href="{{ route('manager.profile') }}"
                    class="nav-link {{ request()->routeIs('manager.profile') ? 'active' : '' }}">
                    {{ __('manager.profile_title') }}
                </a>

                <form method="POST" action="{{ route('logout') }}" class="logout-form">
                    @csrf

                    <button type="submit" class="logout-btn">
                        {{ __('manager.logout') }}
                    </button>
                </form>
            </nav>
        </aside>

        <main class="content">
            <div class="content-shell">
                <div class="topbar">
                    <h1 class="topbar-title">
                        @yield('title', __('manager.dashboard_title'))
                    </h1>

                    <p class="topbar-subtitle">
                        {{ __('manager.university') }}
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

    @stack('scripts')
</body>

</html>