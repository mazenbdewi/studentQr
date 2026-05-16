<table>
    <thead>
        <tr>
            <th style="background-color: #4CAF50; color: white; font-weight: bold;">{{ __('subjects.code') }}</th>
            <th style="background-color: #4CAF50; color: white; font-weight: bold;">{{ __('subjects.name') }}</th>
            <th style="background-color: #4CAF50; color: white; font-weight: bold;">{{ __('subjects.subject_type') }}</th>
            <th style="background-color: #4CAF50; color: white; font-weight: bold;">{{ __('subjects.section_code') }}</th>
            <th style="background-color: #4CAF50; color: white; font-weight: bold;">{{ __('lecture-session.hall') }}</th>
            <th style="background-color: #4CAF50; color: white; font-weight: bold;">{{ __('student.name') }}</th>
            <th style="background-color: #4CAF50; color: white; font-weight: bold;">{{ __('student.student_number') }}
            </th>
            <th style="background-color: #4CAF50; color: white; font-weight: bold;">{{ __('student.status') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($students as $student)
        <tr>
            <td>{{ $student['subject_code'] }}</td>
            <td>{{ $student['subject_name'] }}</td>
            <td>{{ $student['subject_type'] }}</td>
            <td>{{ $student['section_code'] }}</td>
            <td>{{ $student['hall_name'] }}</td>
            <td>{{ $student['name'] }}</td>
            <td>{{ $student['student_number'] }}</td>
            <td>
                @if($student['status'] === 'present')
                {{ __('attendance.status_present') }}
                @else
                {{ __('attendance.status_absent') }}
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
