<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    @php
        $reportTitle = $selectedSubject
            ? __('student.subject_attendance_report_for', ['name' => $student->name, 'subject' => $selectedSubject->name])
            : __('student.attendance_report_for', ['name' => $student->name]);
        $percentage = (float) $summary['attendance_percentage'];
        $percentageClass = match (true) {
            $percentage >= 75 => 'percentage-high',
            $percentage >= 50 => 'percentage-medium',
            default => 'percentage-low',
        };
        $notAvailable = __('lecture-session.not_available');
        $subjectsText = count($subjectLabels) > 0 ? implode('، ', $subjectLabels) : $notAvailable;
    @endphp
    <title>{{ $reportTitle }}</title>
    <style>
        @page {
            margin: 16mm 10mm;
        }

        body {
            font-family: dejavusans, sans-serif;
            font-size: 10.5pt;
            color: #1f2937;
            direction: {{ $isRtl ? 'rtl' : 'ltr' }};
            text-align: {{ $isRtl ? 'right' : 'left' }};
            background: #ffffff;
        }

        .page {
            width: 100%;
        }

        h1, h2, h3, p {
            margin: 0;
        }

        .header {
            margin-bottom: 14px;
            padding-bottom: 12px;
            border-bottom: 2px solid #0f766e;
        }

        .header-table,
        .info-table,
        .summary-table,
        .attendance-table {
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
            margin-bottom: 14px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 12.5pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 7px;
        }

        .student-card {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            overflow: hidden;
            background: #ffffff;
        }

        .student-card-title {
            padding: 9px 10px;
            background: #f8fafc;
            border-bottom: 1px solid #cbd5e1;
            color: #0f172a;
            font-size: 13pt;
            font-weight: bold;
        }

        .info-table td {
            width: 25%;
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            unicode-bidi: plaintext;
        }

        .info-table tr:last-child td {
            border-bottom: 0;
        }

        .info-label {
            display: block;
            font-size: 8.5pt;
            color: #64748b;
            margin-bottom: 3px;
        }

        .info-value {
            display: block;
            font-size: 10.5pt;
            font-weight: bold;
            color: #0f172a;
            line-height: 1.6;
        }

        .summary-table {
            table-layout: fixed;
        }

        .summary-table td {
            width: 25%;
            padding: 9px 8px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            text-align: center;
            vertical-align: top;
        }

        .summary-total {
            border-top: 4px solid #2563eb !important;
        }

        .summary-present {
            border-top: 4px solid #16a34a !important;
        }

        .summary-absent {
            border-top: 4px solid #dc2626 !important;
        }

        .summary-percentage {
            border-top: 4px solid #d97706 !important;
        }

        .summary-label {
            display: block;
            font-size: 8.5pt;
            color: #475569;
            margin-bottom: 5px;
        }

        .summary-value {
            font-size: 16pt;
            font-weight: bold;
            color: #0f172a;
        }

        .summary-present .summary-value {
            color: #166534;
        }

        .summary-absent .summary-value {
            color: #b91c1c;
        }

        .progress-row {
            margin-top: 9px;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
        }

        .progress-label {
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .percentage-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-weight: bold;
        }

        .percentage-high {
            color: #166534;
            background: #dcfce7;
        }

        .percentage-medium {
            color: #92400e;
            background: #fef3c7;
        }

        .percentage-low {
            color: #991b1b;
            background: #fee2e2;
        }

        .progress-track {
            height: 9px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .progress-fill {
            height: 9px;
            border-radius: 999px;
        }

        .progress-fill.percentage-high {
            background: #16a34a;
        }

        .progress-fill.percentage-medium {
            background: #f59e0b;
        }

        .progress-fill.percentage-low {
            background: #ef4444;
        }

        .attendance-table {
            table-layout: fixed;
        }

        .attendance-table th,
        .attendance-table td {
            padding: 7px 8px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
            unicode-bidi: plaintext;
        }

        .attendance-table th {
            background: #0f766e;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
        }

        .attendance-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        .status {
            font-weight: bold;
            text-align: center;
            border-radius: 999px;
            padding: 3px 6px;
            display: inline-block;
        }

        .status-present {
            color: #166534;
            background: #dcfce7;
        }

        .status-absent {
            color: #991b1b;
            background: #fee2e2;
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
            border-radius: 8px;
            background: #f8fafc;
            color: #64748b;
            text-align: center;
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
                    <td>
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
            <div class="student-card">
                <div class="student-card-title">{{ __('student.student_info') }}</div>
                <table class="info-table">
                    <tr>
                        <td>
                            <span class="info-label">{{ __('student.student_name') }}</span>
                            <span class="info-value">{{ $student->name }}</span>
                        </td>
                        <td>
                            <span class="info-label">{{ __('student.student_number') }}</span>
                            <span class="info-value ltr">{{ $student->student_number ?: $notAvailable }}</span>
                        </td>
                        <td>
                            <span class="info-label">{{ __('student.faculty_id') }}</span>
                            <span class="info-value">{{ $student->faculty?->name ?? $notAvailable }}</span>
                        </td>
                        <td>
                            <span class="info-label">{{ __('student.department_id') }}</span>
                            <span class="info-value">{{ $student->department?->name ?? $notAvailable }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="info-label">{{ __('student.phone') }}</span>
                            <span class="info-value ltr">{{ $student->phone ?: $notAvailable }}</span>
                        </td>
                        <td colspan="3">
                            <span class="info-label">{{ __('lecture-session.subject') }}</span>
                            <span class="info-value">{{ $subjectsText }}</span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="section">
            <h2 class="section-title">{{ __('student.summary_stats') }}</h2>
            <table class="summary-table">
                <tr>
                    <td class="summary-total">
                        <span class="summary-label">{{ __('student.total_lectures') }}</span>
                        <span class="summary-value">{{ $summary['total_lectures'] }}</span>
                    </td>
                    <td class="summary-present">
                        <span class="summary-label">{{ __('student.total_present') }}</span>
                        <span class="summary-value">{{ $summary['total_present'] }}</span>
                    </td>
                    <td class="summary-absent">
                        <span class="summary-label">{{ __('student.total_absent') }}</span>
                        <span class="summary-value">{{ $summary['total_absent'] }}</span>
                    </td>
                    <td class="summary-percentage">
                        <span class="summary-label">{{ __('student.overall_attendance') }}</span>
                        <span class="summary-value">{{ $percentage }}%</span>
                    </td>
                </tr>
            </table>

            <div class="progress-row">
                <div class="progress-label">
                    {{ __('student.overall_attendance') }}:
                    <span class="percentage-badge {{ $percentageClass }}">{{ $percentage }}%</span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill {{ $percentageClass }}" style="width: {{ min(100, max(0, $percentage)) }}%;"></div>
                </div>
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="section">
                <div class="empty-state">
                    <strong>{{ __('student.no_attendance_data') }}</strong><br>
                    {{ __('student.no_attendance_records') }}
                </div>
            </div>
        @endif

        <div class="section">
            <h2 class="section-title">{{ __('student.detailed_attendance_history') }}</h2>

            @if ($rows->isEmpty())
                <div class="empty-state">
                    {{ __('student.no_attendance_records') }}
                </div>
            @else
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th style="width: 25%;">{{ __('student.lecture') }}</th>
                            <th style="width: 22%;">{{ __('student.day_date') }}</th>
                            <th style="width: 17%;">{{ __('student.time') }}</th>
                            <th style="width: 16%;">{{ __('attendance.status') }}</th>
                            <th style="width: 20%;">{{ __('attendance.recorded_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $startTime = $row->start_time ? \Illuminate\Support\Carbon::parse($row->start_time)->format('H:i') : null;
                                $endTime = $row->end_time ? \Illuminate\Support\Carbon::parse($row->end_time)->format('H:i') : null;
                                $recordedAt = $row->attendance_recorded_at
                                    ? \Illuminate\Support\Carbon::parse($row->attendance_recorded_at)->translatedFormat('Y-m-d H:i')
                                    : $notAvailable;
                            @endphp
                            <tr>
                                <td>{{ $row->subject?->name ?? $notAvailable }}</td>
                                <td>{{ $row->session_date?->translatedFormat('l, Y-m-d') ?? $notAvailable }}</td>
                                <td class="ltr center">
                                    {{ ($startTime && $endTime) ? "{$startTime} - {$endTime}" : $notAvailable }}
                                </td>
                                <td class="center">
                                    <span class="status status-{{ $row->report_status }}">
                                        {{ $row->report_status === 'present' ? __('attendance.status_present') : __('attendance.status_absent') }}
                                    </span>
                                </td>
                                <td class="ltr center">{{ $recordedAt }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</body>
</html>
