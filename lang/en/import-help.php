<?php

return [
    'template_download' => 'Download Template',
    'import_with_template' => 'Import Using Template',
    
    'help_title' => [
        'students' => 'Students Import Instructions',
        'departments' => 'Departments Import Instructions',
        'subjects' => 'Subjects Import Instructions',
        'halls' => 'Halls Import Instructions',
        'lecture_sessions' => 'Lecture Sessions Import Instructions',
        'subject_students' => 'Subject Students Import Instructions',
    ],
    
    'help_content' => [
        'students' => 'Fill the template with: <strong>Student Number (unique)</strong>, <strong>Name</strong>, <strong>National ID (unique)</strong>, <strong>Faculty Name (exact match)</strong>, <strong>Department Name (exact match)</strong>. Year (1-4), phone optional. No duplicates.',
        'departments' => '<strong>Name</strong>, <strong>Existing Faculty Name</strong>. Status optional: yes/no/1/0/true/false.',
        'subjects' => '<strong>Subject Code (unique)</strong>, <strong>Subject Name</strong>, <strong>Lecturer Name (course_lecturer)</strong>, <strong>Department Name</strong>, <strong>Semester</strong>. Semester accepts only: first, second, summer. Academic year is optional (1-6).',
        'halls' => '<strong>Code (unique)</strong>, <strong>Name</strong>, <strong>Floor</strong>, <strong>Capacity</strong>, Projector (yes/no), Computer (yes/no), Network SSID, IP Range Start/End.',
        'lecture_sessions' => '<strong>Subject Name</strong>, <strong>Hall Name</strong>, <strong>Date (2026-04-28)</strong>, <strong>Start (08:30)</strong>, <strong>End (10:00)</strong>. Date must use YYYY-MM-DD and time must use HH:MM. Status: scheduled/active/completed/cancelled.',
        'subject_students' => 'For current subject: <strong>Student Number</strong>, <strong>Name</strong>, National ID optional, Semester/Year. Student auto-created if not exists.',
    ],
    
    'preview_title' => 'Template Preview (first rows)',
    'tips_title' => 'Important Tips Before Upload',
    'tips' => [
        'match_names' => 'Faculty/Department/Subject/Hall names must <strong>exactly match</strong> system records',
        'date_format' => 'Date: YYYY-MM-DD, example 2026-04-28',
        'time_format' => 'Time: HH:MM in 24-hour format, example 08:30',
        'boolean_values' => 'yes/no/1/0/true/false for boolean fields',
        'unique_fields' => 'Code/Student Number/National ID must be unique',
        'file_size' => 'Max file size: 50 MB',
    ],
    
    'stats' => [
        'imported' => ':count successfully imported',
        'errors' => ':count import errors',
        'download_errors' => 'Download Errors File',
    ],
    
    'import_students_template' => 'Subject Students Template',
    'import_students' => 'Import Subject Students',

    'example_title' => 'Example Excel Format',
    'required_columns' => 'Required columns:',
    'optional_columns' => 'Optional columns:',
    'exact_match' => ':field must exactly match existing :type names in the system',
    'boolean_values_note' => 'Boolean: yes/no/true/false/1/0',
    'date_format_note' => 'Date: YYYY-MM-DD',
    'time_format_note' => 'Time: HH:MM in 24-hour format',
    'column_order_note' => 'Column order does not matter',
    'extra_columns_note' => 'Extra columns ignored if unused',
    'xlsx_only_note' => 'Accepted: xlsx/xls only',
    'modal_title' => [
        'students' => 'Import Students from Excel',
        'departments' => 'Import Departments from Excel',
        'subjects' => 'Import Subjects from Excel',
        'halls' => 'Import Halls from Excel',
        'lecture_sessions' => 'Import Lecture Sessions from Excel',
        'subject_students' => 'Import Subject Students from Excel',
    ],
    'intro_text' => 'Upload Excel file matching the example or download template.',
    'instructions_title' => 'Important Instructions',
    'use_headers' => 'Use the same column headers shown below',
    'modal_download_label' => 'Download Template',
    'modal_download_sentence' => 'Download the template, fill it using the same headers, then upload it here.',
    'simple_instructions' => [
        'Download the template and fill it using the same column names',
        'Do not change the headers',
        'Only xlsx/xls files are accepted',
        'Some fields must exactly match existing records in the system'
    ],
];
