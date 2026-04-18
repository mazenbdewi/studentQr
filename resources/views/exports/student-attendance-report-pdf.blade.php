<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    @php
        $reportTitle = $selectedSubject
            ? __('student.subject_attendance_report_for', ['name' => $student->name, 'subject' => $selectedSubject->name])
            : __('student.attendance_report_for', ['name' => $student->name]);
    @endphp
    <title>{{ $reportTitle }}</title>
    <style>
        @page {
            margin: 16mm 10mm;
        }

        body {
            font-family: dejavusans, sans-serif;
            font-size: 11pt;
            color: #1f2937;
            direction: {{ $isRtl ? 'rtl' : 'ltr' }};
            text-align: {{ $isRtl ? 'right' : 'left' }};
        }

        .page {
            width: 100%;
        }

        h1, h2, h3, p {
            margin: 0;
        }

        .header {
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 2px solid #0f766e;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: middle;
        }

        .header-logo-cell {
            width: 96px;
            text-align: center;
            {{ $isRtl ? 'padding-left' : 'padding-right' }}: 14px;
        }

        .header-content-cell {
            width: auto;
        }

        .header-logo {
            max-width: 82px;
            max-height: 82px;
            width: auto;
            height: auto;
            display: block;
        }

        .university-name {
            font-size: 11pt;
            font-weight: bold;
            color: #0f766e;
            margin-bottom: 4px;
        }

        .title {
            font-size: 18pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .subtitle {
            font-size: 10pt;
            color: #475569;
        }

        .section {
            margin-bottom: 16px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 13pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .info-table,
        .attendance-table,
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            width: 50%;
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            vertical-align: top;
            unicode-bidi: plaintext;
        }

        .summary-table td {
            width: 25%;
            padding: 8px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            text-align: center;
        }

        .summary-label {
            display: block;
            font-size: 9pt;
            color: #475569;
            margin-bottom: 4px;
        }

        .summary-value {
            font-size: 13pt;
            font-weight: bold;
            color: #0f172a;
        }

        .attendance-table th,
        .attendance-table td {
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
            unicode-bidi: plaintext;
        }

        .attendance-table th {
            background: #0f766e;
            color: #ffffff;
            font-weight: bold;
        }

        .attendance-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        .status {
            font-weight: bold;
            text-align: center;
        }

        .status-present {
            color: #166534;
        }

        .status-absent {
            color: #b91c1c;
        }

        .muted {
            color: #64748b;
        }

        .ltr {
            direction: ltr;
            text-align: left;
        }

        .center {
            text-align: center;
        }

        .empty-state {
            padding: 14px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <table class="header-table">
                <tr>
                    @if ($logoDataUri)
                        <td class="header-logo-cell">
                            <img src="{{ $logoDataUri }}" alt="{{ __('student.university_name') }}" class="header-logo">
                        </td>
                    @endif
                    <td class="header-content-cell">
                        <p class="university-name">{{ __('student.university_name') }}</p>
                        <h1 class="title">{{ $reportTitle }}</h1>
                        <p class="subtitle">
                            {{ __('student.generated_at') }}:
                            {{ $generatedAt->translatedFormat('Y-m-d H:i') }}
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h2 class="section-title">{{ __('student.student_info') }}</h2>
            <table class="info-table">
                <tr>
                    <td>
                        <strong>{{ __('student.name') }}:</strong>
                        {{ $student->name }}
                    </td>
                    <td>
                        <strong>{{ __('student.student_number') }}:</strong>
                        <span class="ltr">{{ $student->student_number ?: __('lecture-session.not_available') }}</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>{{ __('student.department_id') }}:</strong>
                        {{ $student->department?->name ?? __('lecture-session.not_available') }}
                    </td>
                    <td>
                        <strong>{{ __('student.faculty_id') }}:</strong>
                        {{ $student->faculty?->name ?? __('lecture-session.not_available') }}
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <strong>{{ __('lecture-session.subject') }}:</strong>
                        {{ $selectedSubject?->name ?? __('enrollments.enrolled_subjects') }}
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h2 class="section-title">{{ __('student.summary_stats') }}</h2>
            <table class="summary-table">
                <tr>
                    <td>
                        <span class="summary-label">{{ __('student.total_lectures') }}</span>
                        <span class="summary-value">{{ $summary['total_lectures'] }}</span>
                    </td>
                    <td>
                        <span class="summary-label">{{ __('student.total_present') }}</span>
                        <span class="summary-value">{{ $summary['total_present'] }}</span>
                    </td>
                    <td>
                        <span class="summary-label">{{ __('student.total_absent') }}</span>
                        <span class="summary-value">{{ $summary['total_absent'] }}</span>
                    </td>
                    <td>
                        <span class="summary-label">{{ __('student.overall_attendance') }}</span>
                        <span class="summary-value">{{ $summary['attendance_percentage'] }}%</span>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h2 class="section-title">{{ __('student.detailed_attendance_history') }}</h2>

            @if ($rows->isEmpty())
                <div class="empty-state">
                    {{ __('student.no_attendance_history') }}
                </div>
            @else
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>{{ __('student.lecture') }}</th>
                            <th>{{ __('student.day_date') }}</th>
                            <th>{{ __('student.time') }}</th>
                            <th>{{ __('attendance.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $startTime = $row->start_time ? \Illuminate\Support\Carbon::parse($row->start_time)->format('H:i') : null;
                                $endTime = $row->end_time ? \Illuminate\Support\Carbon::parse($row->end_time)->format('H:i') : null;
                            @endphp
                            <tr>
                                <td>{{ $row->subject?->name ?? __('lecture-session.not_available') }}</td>
                                <td>{{ $row->session_date?->translatedFormat('l, Y-m-d') ?? __('lecture-session.not_available') }}</td>
                                <td class="ltr center">
                                    {{ ($startTime && $endTime) ? "{$startTime} - {$endTime}" : __('lecture-session.not_available') }}
                                </td>
                                <td class="status status-{{ $row->report_status }}">
                                    {{ $row->report_status === 'present' ? __('attendance.status_present') : __('attendance.status_absent') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</body>
</html>
