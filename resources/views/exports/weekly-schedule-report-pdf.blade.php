<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 12mm 8mm 15mm; }
        body { font-family: dejavusans, sans-serif; direction: rtl; color: #1f2937; font-size: 8pt; }
        .header { border-bottom: 2px solid #1e40af; margin-bottom: 10px; padding-bottom: 8px; }
        .header-table, .report-table, .filters-table { width: 100%; border-collapse: collapse; }
        .logo { width: 58px; max-height: 58px; }
        h1 { margin: 0 0 4px; color: #172554; font-size: 16pt; }
        .meta { color: #475569; font-size: 8pt; }
        .filters { margin-bottom: 9px; padding: 6px; border: 1px solid #cbd5e1; background: #f8fafc; }
        .filters-table td { padding: 3px 5px; vertical-align: top; }
        .filter-label { color: #64748b; }
        .filter-value { font-weight: bold; }
        .report-table th, .report-table td { border: 1px solid #cbd5e1; padding: 4px 5px; vertical-align: middle; }
        .report-table th { background: #1e40af; color: #fff; text-align: center; font-weight: bold; }
        .report-table tr:nth-child(even) td { background: #f8fafc; }
        .empty { padding: 18px; text-align: center; color: #64748b; border: 1px solid #cbd5e1; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                @if ($logoDataUri)
                    <td style="width:68px"><img src="{{ $logoDataUri }}" class="logo" alt="{{ config('app.name') }}"></td>
                @endif
                <td>
                    <h1>{{ $title }}</h1>
                    <div class="meta">{{ config('app.name') }} — {{ __('weekly-schedule-reports.generated_at') }}: {{ $generatedAt->translatedFormat('Y-m-d H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="filters">
        @if ($filterLabels !== [])
            <table class="filters-table">
                @foreach ($filterLabels as $label => $value)
                    <tr><td class="filter-label">{{ $label }}</td><td class="filter-value">{{ $value }}</td></tr>
                @endforeach
            </table>
        @else
            {{ __('weekly-schedule-reports.all_records') }}
        @endif
    </div>

    @if ($rows->isEmpty())
        <div class="empty">{{ __('weekly-schedule-reports.no_rows') }}</div>
    @else
        <table class="report-table">
            <thead><tr>@foreach ($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>@foreach ($row as $value)<td>{{ $value }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
