<?php
return [
    'import_help' => [
        'template_download' => 'Download Ready Template',
        'import_with_template' => 'Import Using Template',
        
        'students' => [
            'help_title' => 'New Students Import Guide',
            'help_paragraph' => 'Follow these steps to successfully import students from Excel file. Use the provided template to avoid errors.',
            'required_columns_title' => 'Required File Columns',
            'required_columns' => 'Student Number (globally unique), Full Name, National ID (unique), Faculty Name (exact system match), Department Name (exact system match), Academic Year (1-4)',
            'warning_notes' => 'Warning: Faculty and Department names must match system records exactly. Student Number and National ID cannot duplicate. Phone, Status, Avatar optional.',
            'success_message' => ':count students imported successfully!',
            'failure_summary' => ':imported success | :errors errors. Check errors below:',
        ],
        'departments' => [
            'help_title' => 'Academic Departments Import Guide',
            'help_paragraph' => 'Prepare Excel file to add new departments. Each department needs unique code linked to existing faculty.',
            'required_columns_title' => 'Required Columns',
            'required_columns' => 'Code (unique), Arabic Name, Faculty Name (exact match in system)',
            'warning_notes' => 'Faculty name must exactly match registered faculties. English name and status optional (yes/no/1/0).',
            'success_message' => ':count departments imported successfully!',
            'failure_summary' => ':imported success | :errors errors. Verify faculty name matching.',
        ],
        'subjects' => [
            'help_title' => 'Course Subjects Import Guide',
            'help_paragraph' => 'Import courses with lecturer and department linking. Lecturer must have course_lecturer role.',
            'required_columns_title' => 'Required Columns',
            'required_columns' => 'Course Code (unique), Course Name, Lecturer Name, Department Name, Credit Hours',
            'warning_notes' => 'Lecturer must be registered with "course_lecturer" role. Level 1-5, Semester 1-2. Status optional.',
            'success_message' => ':count subjects imported successfully!',
            'failure_summary' => ':imported success | :errors errors. Check lecturer/department names.',
        ],
        'halls' => [
            'help_title' => 'Lecture Halls & Facilities Import',
            'help_paragraph' => 'Add halls with network and equipment details. Each hall requires unique code.',
            'required_columns_title' => 'File Structure',
            'required_columns' => 'Code (unique), Name, Floor, Capacity, Projector, Computer',
            'warning_notes' => 'Valid IP range only. Projector/Computer: yes/no/1/0. Network SSID optional.',
            'success_message' => ':count halls imported successfully!',
            'failure_summary' => ':imported success | :errors errors. Verify IP addresses.',
        ],
        'lecture_sessions' => [
            'help_title' => 'Lecture Sessions Scheduling Import',
            'help_paragraph' => 'Quickly schedule sessions linking subjects and halls. Use correct date/time format.',
            'required_columns_title' => 'Key Columns',
            'required_columns' => 'Subject Name, Hall Name, Session Date (15-10-2024), Start Time (09:00), End Time',
            'warning_notes' => 'Date: day-month-year. Time: HH:MM or Excel time. End after Start. Status: scheduled/active.',
            'success_message' => ':count sessions scheduled successfully!',
            'failure_summary' => ':imported success | :errors errors. Check date/time format.',
        ],
        'subject_students' => [
            'help_title' => 'Import Students for This Subject Only',
            'help_paragraph' => 'Link students to this specific subject. New students auto-created.',
            'required_columns_title' => 'Student Data',
            'required_columns' => 'Student Number, Full Name, Academic Semester, Academic Year',
            'warning_notes' => 'Student Number unique. National ID optional. Added to current subject only.',
            'success_message' => ':count students linked to this subject!',
            'failure_summary' => ':imported success | :errors errors. Verify student numbers.',
        ],
        
        'preview_title' => 'Template Content Preview',
        'tips_title' => 'Guaranteed Success Tips',
        'tips' => [
            'exact_match' => 'Faculty/Department/Subject/Hall names must exactly match system records',
            'date_format' => 'Date: 15-10-2024 (day-month-year)',
            'time_format' => 'Time: 09:00 (hour:minute) or Excel time directly',
            'boolean_format' => 'yes/1/true = enabled | no/0/false = disabled',
            'unique_required' => 'Code, Student Number, National ID must be unique',
            'file_limit' => 'Max file size 50 MB, CSV or XLSX',
        ],
        
        'stats' => [
            'imported' => ':count success',
            'errors' => ':count errors',
            'download_errors' => '📥 Download Full Errors File',
        ],
        
        'import_students' => 'Import Subject Students',
        'import_students_template' => ':subject Students Template',
    ],
];
?>

