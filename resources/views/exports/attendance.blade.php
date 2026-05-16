<table>
    <thead>
        <tr>
            <th>{{ __('subjects.code') }}</th>
            <th>{{ __('subjects.name') }}</th>
            <th>{{ __('subjects.subject_type') }}</th>
            <th>{{ __('subjects.section_code') }}</th>
            <th>{{ __('lecture-session.hall') }}</th>
            <th>{{ __('attendance.student_name') }}</th>
            <th>{{ __('attendance.student_number') }}</th>
            <th>{{ __('attendance.attendance_time') }}</th>
        </tr>
    </thead>
    <tbody>
@foreach($records as $attendance)
    <tr>
        <td>{{ $attendance->lectureSession?->subject?->code }}</td>
        <td>{{ $attendance->lectureSession?->subject?->name }}</td>
        <td>{{ $attendance->lectureSession?->subjectSection?->section_type_label ?? $attendance->lectureSession?->subject?->subject_type_label }}</td>
        <td>{{ $attendance->lectureSession?->subjectSection?->code }}</td>
        <td>{{ $attendance->lectureSession?->hall?->name }}</td>
        <td>{{ $attendance->student->name }}</td>
        <td>{{ $attendance->student->student_number }}</td>
        <td>{{ $attendance->attendance_time }}</td>
    </tr>
    @endforeach
    </tbody>
    </table>
