
<?php

return [
    'code_required'    => 'The code field is required in row :row.',
    'code_unique'      => 'The value ":input" has already been taken for the code field in row :row.',
    'code_max'         => 'The code must not exceed 255 characters in row :row.',
    'name_required'    => 'The name field is required in row :row.',
    'name_max'         => 'The name must not exceed 255 characters in row :row.',
    'floor_integer'    => 'The floor must be an integer in row :row.',
    'floor_min'        => 'The floor must be at least 0 in row :row.',
    'capacity_integer' => 'The capacity must be an integer in row :row.',
    'capacity_min'     => 'The capacity must be at least 1 in row :row.',
    'has_projector_boolean' => 'The projector field must be a boolean (true/false) in row :row.',
    'has_computer_boolean'  => 'The computer field must be a boolean (true/false) in row :row.',
    'network_ssid_max'      => 'The network SSID must not exceed 255 characters in row :row.',
    'ip_range_start_ip'     => 'The IP range start must be a valid IP address in row :row.',
    'ip_range_end_ip'       => 'The IP range end must be a valid IP address in row :row.',
    'is_active_boolean'     => 'The active field must be a boolean (true/false) in row :row.',
    'import_failed'         => 'Import failed. Please check the provided data.',

    'lecturer_required' => 'Lecturer is required in row :row.',
    'lecturer_not_found' => 'Lecturer ":input" does not exist in row :row.',

    'department_required' => 'Department is required in row :row.',
    'department_not_found' => 'Department ":input" does not exist in row :row.',
    'subject' => 'Subject',
    'subject_required' => 'The subject field is required in row :row.',
    'subject_not_found' => 'The subject was not found in row :row.',

    'hall' => 'Hall',
    'hall_required' => 'The hall field is required in row :row.',
    'hall_not_found' => 'The hall was not found in row :row.',

    'session_date' => 'Session Date',
    'session_date_required' => 'The session date field is required in row :row.',
    'session_date_invalid' => 'The session date is invalid in row :row.',

    'start_time' => 'Start Time',
    'start_time_required' => 'The start time field is required in row :row.',
    'start_time_format' => 'The start time must be in HH:MM format in row :row.',

    'end_time' => 'End Time',
    'end_time_required' => 'The end time field is required in row :row.',
    'end_time_format' => 'The end time must be in HH:MM format in row :row.',
    'end_time_after_start' => 'The end time must be after start time in row :row.',

    'status' => 'Status',
    'status_invalid' => 'The status is invalid in row :row.',

    'attendance_mode' => 'Attendance Mode',
    'attendance_mode_invalid' => 'The attendance mode is invalid in row :row.',

    'qr_refresh_rate' => 'QR Refresh Rate',
    'qr_refresh_rate_integer' => 'The QR refresh rate must be an integer in row :row.',
    'qr_refresh_rate_min' => 'The QR refresh rate must be at least 10 in row :row.',

    'notes' => 'Notes',
    'notes_string' => 'The notes field must be a string in row :row.',
    'name' => 'Name',
    'student_number' => 'Student Number',
    'national_number' => 'National Number',
    'faculty' => 'Faculty',
    'department' => 'Department',

    'student_number_required' => 'The student number field is required.',

    'name_required' => 'The Name field is required in row :row.',
    'name_max' => 'The Name field must not exceed 255 characters in row :row.',

    'student_number_unique' => 'The Student Number ":input" has already been taken in row :row.',
    'national_number_unique' => 'The National Number ":input" has already been taken in row :row.',

    'faculty_required' => 'The Faculty field is required in row :row.',
    'faculty_not_found' => 'The Faculty ":input" does not exist in row :row.',
    'faculty_not_found_in_row' => 'Faculty not found in row :row.',

    'department_required' => 'The Department field is required in row :row.',
    'department_not_found' => 'The Department ":input" does not exist in row :row.',
    'department_not_found_in_row' => 'Department not found in row :row.',
];
