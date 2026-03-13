<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ __('student.absent_students') }}</title>
    <!-- <style>
        body { font-family: DejaVu Sans, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style> -->

    <style>
        body {
            font-family: 'DejaVu Sans', 'Tahoma', 'Arial', sans-serif;
            direction: {{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }};
            text-align: {{ app()->getLocale() == 'ar' ? 'right' : 'left' }};
        }
        h2 {
            text-align: {{ app()->getLocale() == 'ar' ? 'right' : 'left' }};
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #333;
            padding: 8px;
            text-align: {{ app()->getLocale() == 'ar' ? 'right' : 'left' }};
            vertical-align: middle;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        
        .arabic-number {
            unicode-bidi: embed;
        }
    </style>
</head>
<body>
<h2>{{ __('student.absent_students') }}</h2>

    <table>
        <thead>
            <tr>
            <th>{{ __('student.serial') }}</th>
                <th>{{ __('student.name') }}</th>
                <th>{{ __('student.student_number') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $student)
                <tr>
                <td class="arabic-number">{{ $index + 1 }}</td>
                    <td>{{ $student->name }}</td>
                    <td dir="ltr" style="text-align: left;">{{ $student->student_number }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>