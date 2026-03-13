<table>
    <thead>
        <tr>
            <th style="background-color: #4CAF50; color: white; font-weight: bold;">{{ __('student.name') }}</th>
            <th style="background-color: #4CAF50; color: white; font-weight: bold;">{{ __('student.student_number') }}
            </th>
            <th style="background-color: #4CAF50; color: white; font-weight: bold;">{{ __('student.status') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($students as $student)
        <tr>
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