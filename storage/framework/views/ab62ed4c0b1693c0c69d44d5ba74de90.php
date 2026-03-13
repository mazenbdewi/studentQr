<!DOCTYPE html>
<html lang="<?php echo e(app()->getLocale()); ?>" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1d4ed8">

    <title><?php echo $__env->yieldContent('title', __('student.confirm_attendance')); ?></title>

    <style>
    :root {
        --bg: #f3f7ff;
        --bg-soft: #eef4ff;
        --card: #ffffff;
        --text: #0f172a;
        --muted: #64748b;
        --border: #dbe3f0;
        --primary: #1d4ed8;
        --primary-dark: #1e3a8a;
        --primary-soft: rgba(29, 78, 216, .10);
        --danger: #b91c1c;
        --danger-bg: #fff1f2;
        --danger-border: #fecdd3;
        --success: #166534;
        --success-bg: #ecfdf3;
        --success-border: #bbf7d0;
        --shadow: 0 10px 30px rgba(15, 23, 42, .08);
        --radius: 22px;
    }

    * {
        box-sizing: border-box;
        -webkit-tap-highlight-color: transparent;
    }

    html {
        -webkit-text-size-adjust: 100%;
        text-size-adjust: 100%;
    }

    html,
    body {
        margin: 0;
        padding: 0;
        min-height: 100%;
    }

    body {
        font-family:
            system-ui,
            -apple-system,
            BlinkMacSystemFont,
            "Segoe UI",
            Tahoma,
            Arial,
            sans-serif;
        color: var(--text);
        background:
            radial-gradient(circle at top right, rgba(59, 130, 246, .10), transparent 30%),
            radial-gradient(circle at bottom left, rgba(29, 78, 216, .08), transparent 28%),
            linear-gradient(180deg, #f8fbff 0%, var(--bg) 100%);
    }

    .shell {
        min-height: 100svh;
        display: grid;
        place-items: center;
        padding:
            max(16px, env(safe-area-inset-top)) max(16px, env(safe-area-inset-right)) max(16px, env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left));
    }

    .container {
        width: 100%;
        margin-inline: auto;
    }

    .card {
        width: 100%;
        background: var(--card);
        border: 1px solid rgba(219, 227, 240, .95);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
    }

    .card-body {
        padding: 22px 18px;
    }

    .header {
        text-align: center;
        margin-bottom: 22px;
    }

    .logo-box {
        width: 88px;
        height: 88px;
        margin: 0 auto 14px;
        border-radius: 24px;
        background: linear-gradient(180deg, #f8fbff 0%, var(--bg-soft) 100%);
        border: 1px solid #dbeafe;
        display: grid;
        place-items: center;
        overflow: hidden;
    }

    .logo-image {
        width: 64px;
        height: 64px;
        object-fit: contain;
        display: block;
    }

    .logo-fallback {
        width: 56px;
        height: 56px;
        display: none;
        align-items: center;
        justify-content: center;
        color: var(--primary);
    }

    .title {
        margin: 0;
        font-size: 1.35rem;
        line-height: 1.3;
        font-weight: 800;
        letter-spacing: -.02em;
    }

    .subtitle {
        margin: 8px 0 0;
        color: var(--muted);
        font-size: .95rem;
        line-height: 1.7;
    }

    .messages {
        margin-bottom: 16px;
    }

    .alert {
        border-radius: 16px;
        border: 1px solid transparent;
        padding: 13px 14px;
        font-size: .95rem;
        line-height: 1.7;
        margin-bottom: 12px;
    }

    .alert:last-child {
        margin-bottom: 0;
    }

    .alert-success {
        background: var(--success-bg);
        border-color: var(--success-border);
        color: var(--success);
    }

    .alert-error {
        background: var(--danger-bg);
        border-color: var(--danger-border);
        color: var(--danger);
    }

    .alert ul {
        margin: 0;
        padding: 0 18px 0 0;
    }

    .form {
        display: grid;
        gap: 16px;
    }

    .field {
        display: grid;
        gap: 8px;
    }

    .label {
        font-size: .96rem;
        font-weight: 700;
        color: #334155;
    }

    .input {
        width: 100%;
        height: 52px;
        border: 1px solid var(--border);
        border-radius: 16px;
        background: #fff;
        color: var(--text);
        padding: 0 14px;
        font-size: 16px;
        outline: none;
        transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease;
    }

    .input::placeholder {
        color: #94a3b8;
    }

    .input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px var(--primary-soft);
    }

    .input.is-invalid {
        border-color: #ef4444;
        background: #fffafb;
    }

    .field-error {
        color: var(--danger);
        font-size: .88rem;
        line-height: 1.6;
    }

    .submit-btn {
        width: 100%;
        height: 54px;
        border: 0;
        border-radius: 16px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: #fff;
        font-size: 1rem;
        font-weight: 800;
        cursor: pointer;
        transition: transform .15s ease, box-shadow .2s ease, opacity .2s ease;
        box-shadow: 0 10px 24px rgba(29, 78, 216, .22);
    }

    .submit-btn:hover {
        transform: translateY(-1px);
    }

    .submit-btn:active {
        transform: translateY(0);
    }

    .submit-btn:disabled {
        opacity: .7;
        cursor: not-allowed;
    }

    .footer-note {
        margin-top: 12px;
        text-align: center;
        color: var(--muted);
        font-size: .88rem;
        line-height: 1.7;
    }

    /* Mobile */
    .container {
        max-width: 100%;
    }

    /* Tablet */
    @media (min-width: 768px) {
        .container {
            max-width: 560px;
        }

        .card-body {
            padding: 28px 24px;
        }

        .logo-box {
            width: 100px;
            height: 100px;
        }

        .logo-image {
            width: 72px;
            height: 72px;
        }

        .title {
            font-size: 1.5rem;
        }

        .subtitle {
            font-size: .98rem;
        }
    }

    /* Desktop */
    @media (min-width: 1024px) {
        .container {
            max-width: 600px;
        }

        .card-body {
            padding: 32px 28px;
        }

        .logo-box {
            width: 108px;
            height: 108px;
        }

        .logo-image {
            width: 78px;
            height: 78px;
        }

        .title {
            font-size: 1.65rem;
        }
    }
    </style>

    <?php echo $__env->yieldPushContent('styles'); ?>
</head>

<body>
    <div class="shell">
        <div class="container">
            <div class="card">
                <div class="card-body">
                    <?php echo $__env->yieldContent('content'); ?>
                </div>
            </div>
        </div>
    </div>

    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>

</html><?php /**PATH /home/mazen/Documents/programes/StudentAttendenceSystem/resources/views/student/layout.blade.php ENDPATH**/ ?>