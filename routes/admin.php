<?php

use Illuminate\Support\Facades\Route;
use App\Exports\Templates\StudentsTemplateExport;
use App\Exports\Templates\DepartmentsTemplateExport;
use App\Exports\Templates\SubjectsTemplateExport;
use App\Exports\Templates\HallsTemplateExport;
use App\Exports\Templates\LectureSessionsTemplateExport;
use Maatwebsite\Excel\Facades\Excel;

Route::get('/download-template/students', function () {
    return Excel::download(new StudentsTemplateExport(), 'students_template.xlsx');
})->name('admin.download-template.students');

Route::get('/download-template/departments', function () {
    return Excel::download(new DepartmentsTemplateExport(), 'departments_template.xlsx');
})->name('admin.download-template.departments');

Route::get('/download-template/subjects', function () {
    return Excel::download(new SubjectsTemplateExport(), 'subjects_template.xlsx');
})->name('admin.download-template.subjects');

Route::get('/download-template/halls', function () {
    return Excel::download(new HallsTemplateExport(), 'halls_template.xlsx');
})->name('admin.download-template.halls');

Route::get('/download-template/lecture-sessions', function () {
    return Excel::download(new LectureSessionsTemplateExport(), 'lecture_sessions_template.xlsx');
})->name('admin.download-template.lecture-sessions');

Route::get('/download-template/subject-students', function () {
    return Excel::download(new \App\Exports\Templates\SubjectStudentsTemplateExport(), 'subject_students_template.xlsx');
})->name('admin.download-template.subject-students');

