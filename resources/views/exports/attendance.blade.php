<table>
    <thead>
        <tr>
            <th>Name</th>
            <th>Student Number</th>
            <th>Attendance Time</th>
        </tr>
    </thead>
    <tbody>
@foreach($records as $attendance)
    <tr>
        <td>{{ $attendance->student->name }}</td>
        <td>{{ $attendance->student->student_number }}</td>
        <td>{{ $attendance->attendance_time }}</td>
    </tr>
    @endforeach
    </tbody>
    </table>
