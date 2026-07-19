<?php

return [
    'title' => 'Weekly Schedule Reports',
    'types' => ['comprehensive' => 'Comprehensive timetable', 'by_lecturer' => 'By lecturer', 'by_hall' => 'By hall', 'by_subject' => 'By subject and section', 'by_weekday' => 'By weekday', 'reconciliation' => 'Reconciliation/status'],
    'worksheet_titles' => ['comprehensive' => 'Comprehensive', 'by_lecturer' => 'By lecturer', 'by_hall' => 'By hall', 'by_subject' => 'By subject', 'by_weekday' => 'By weekday', 'reconciliation' => 'Reconciliation'],
    'filters_title' => 'Report filters', 'all' => 'All', 'all_records' => 'All records', 'clear_filters' => 'Clear filters', 'show_report' => 'View report',
    'download_excel' => 'Download Excel', 'download_pdf' => 'Download PDF / Print', 'full_reconciliation' => 'View full review report',
    'generated_at' => 'Generated at', 'no_rows' => 'No records match the selected filters.',
    'select_batch' => 'Schedule import batch', 'select_batch_placeholder' => 'Select an import batch', 'select_batch_error' => 'Please select a valid import batch.', 'open_reconciliation' => 'View review report',
    'filters' => ['academic_term' => 'Academic term', 'import_batch' => 'Import batch', 'faculty' => 'Faculty', 'department' => 'Department', 'subject' => 'Subject', 'section_type' => 'Section type', 'subject_section' => 'Section', 'lecturer' => 'Lecturer', 'hall' => 'Hall', 'weekday' => 'Weekday'],
    'summary' => ['total' => 'Total weekly slots', 'subjects' => 'Matched subjects', 'theoretical_sections' => 'Theoretical sections', 'practical_sections' => 'Practical sections', 'lecturers' => 'Lecturers', 'halls' => 'Halls', 'needs_review' => 'Rows needing review', 'unscheduled' => 'Unscheduled rows'],
    'reconciliation' => ['needs_attention' => 'Needs attention', 'warnings' => 'Warnings', 'excluded' => 'Excluded from batch schedule', 'successful' => 'Successfully imported', 'missing_subjects' => 'Missing subjects', 'missing_sections' => 'Missing sections', 'missing_lecturers' => 'Missing lecturers', 'missing_halls' => 'Missing halls', 'no_weekly_time' => 'No weekly time', 'unscheduled' => 'Unscheduled'],
    'headings' => [
        'comprehensive' => ['Academic term', 'Faculty', 'Department', 'Subject code', 'Subject name', 'Section code', 'Section type', 'Lecturer', 'Hall', 'Weekday', 'Start time', 'End time', 'Section capacity', 'Expected students'],
        'by_lecturer' => ['Lecturer', 'Subject', 'Section', 'Weekday', 'Time', 'Hall', 'Academic term'],
        'by_hall' => ['Hall', 'Weekday', 'Time', 'Subject', 'Section', 'Lecturer', 'Expected students'],
        'by_subject' => ['Subject code', 'Subject name', 'Section', 'Lecturer', 'Hall', 'Weekday', 'Start time', 'End time', 'Expected students'],
        'by_weekday' => ['Weekday', 'Subject', 'Section', 'Lecturer', 'Hall', 'Start time', 'End time', 'Academic term'],
        'reconciliation' => ['Status', 'Count'],
    ],
];
