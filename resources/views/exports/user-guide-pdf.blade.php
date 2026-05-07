<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ __('user-guide.pdf.document_title', locale: 'ar') }}</title>
    <style>
        @page {
            margin: 16mm 12mm;
        }

        body {
            font-family: dejavusans, sans-serif;
            font-size: 11pt;
            line-height: 1.85;
            color: #1e293b;
            direction: rtl;
            text-align: right;
        }

        * {
            box-sizing: border-box;
        }

        h1, h2, h3, p, ul {
            margin: 0;
        }

        .cover {
            min-height: 245mm;
            padding-top: 20mm;
            text-align: center;
        }

        .cover-logo {
            max-width: 88px;
            max-height: 88px;
            margin: 0 auto 16px;
            display: block;
        }

        .cover-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 11pt;
            margin-bottom: 16px;
        }

        .cover-organization {
            font-size: 16pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 10px;
        }

        .cover-title {
            font-size: 26pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 12px;
        }

        .cover-subtitle {
            max-width: 120mm;
            margin: 0 auto 14px;
            color: #475569;
        }

        .cover-meta {
            color: #64748b;
            font-size: 10pt;
        }

        .page-break {
            page-break-after: always;
        }

        .toc {
            page-break-after: always;
        }

        .toc-title,
        .section-title {
            font-size: 18pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 14px;
        }

        .toc-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .toc-item {
            margin-bottom: 10px;
            padding: 10px 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }

        .section {
            margin-bottom: 18px;
            page-break-inside: avoid;
        }

        .section-heading {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }

        .section-label,
        .section-title-text {
            display: table-cell;
            vertical-align: middle;
        }

        .section-label {
            width: 34px;
            color: #1d4ed8;
            font-size: 16pt;
            font-weight: bold;
        }

        .section-title-text {
            color: #0f172a;
            font-size: 16pt;
            font-weight: bold;
        }

        .section-paragraph {
            margin-bottom: 8px;
            color: #334155;
        }

        .section-list {
            margin: 8px 0 0;
            padding-right: 18px;
        }

        .section-list li {
            margin-bottom: 6px;
        }

        .subsection {
            margin-top: 12px;
            padding: 12px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #ffffff;
        }

        .subsection-title {
            font-size: 12pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .issue-card {
            margin-top: 12px;
            padding: 12px 14px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .issue-title {
            font-size: 12pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .issue-solution {
            color: #334155;
        }
    </style>
</head>
<body>
    <section class="cover">
        @if ($branding['logo_data_uri'])
            <img src="{{ $branding['logo_data_uri'] }}" alt="{{ $branding['organization_name'] }}" class="cover-logo">
        @endif

        <div class="cover-badge">{{ $branding['system_name'] }}</div>
        <p class="cover-organization">{{ $branding['organization_name'] }}</p>
        <h1 class="cover-title">{{ __('user-guide.pdf.document_title', locale: 'ar') }}</h1>
        <p class="cover-subtitle">{{ __('user-guide.pdf.document_subtitle', locale: 'ar') }}</p>
        <p class="cover-meta">
            {{ __('user-guide.pdf.generated_at', locale: 'ar') }}:
            {{ $generatedAt->translatedFormat('Y/m/d') }}
        </p>
    </section>

    <div class="page-break"></div>

    <section class="toc">
        <h2 class="toc-title">{{ __('user-guide.pdf.toc_title', locale: 'ar') }}</h2>

        <ul class="toc-list">
            @foreach ($sections as $section)
                <li class="toc-item">
                    {{ $section['label'] }}. {{ $section['title'] }}
                </li>
            @endforeach
        </ul>
    </section>

    @foreach ($sections as $section)
        <section class="section">
            <div class="section-heading">
                <div class="section-label">{{ $section['label'] }}.</div>
                <div class="section-title-text">{{ $section['title'] }}</div>
            </div>

            @foreach ($section['paragraphs'] ?? [] as $paragraph)
                <p class="section-paragraph">{{ $paragraph }}</p>
            @endforeach

            @if (! empty($section['bullets']))
                <ul class="section-list">
                    @foreach ($section['bullets'] as $bullet)
                        <li>{{ $bullet }}</li>
                    @endforeach
                </ul>
            @endif

            @foreach ($section['subsections'] ?? [] as $subsection)
                <div class="subsection">
                    <h3 class="subsection-title">{{ $subsection['title'] }}</h3>

                    @foreach ($subsection['paragraphs'] ?? [] as $paragraph)
                        <p class="section-paragraph">{{ $paragraph }}</p>
                    @endforeach

                    @if (! empty($subsection['bullets']))
                        <ul class="section-list">
                            @foreach ($subsection['bullets'] as $bullet)
                                <li>{{ $bullet }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach

            @foreach ($section['issues'] ?? [] as $issue)
                <div class="issue-card">
                    <h3 class="issue-title">{{ $issue['problem'] }}</h3>
                    <p class="issue-solution">{{ $issue['solution'] }}</p>
                </div>
            @endforeach
        </section>
    @endforeach
</body>
</html>
