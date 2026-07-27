<?php

use App\Exports\Templates\DepartmentsTemplateExport;
use App\Exports\Templates\HallsTemplateExport;
use App\Exports\Templates\LectureSessionsTemplateExport;
use App\Exports\Templates\StudentsTemplateExport;
use App\Exports\Templates\SubjectsTemplateExport;
use App\Http\Controllers\Admin\BlockedWeeklySlotsCompatibilityRedirectController;
use App\Http\Controllers\Admin\DatabaseBackupDownloadController;
use App\Http\Controllers\Admin\ManaraEnrollmentImportErrorDownloadController;
use App\Http\Controllers\Admin\ManaraScheduleImportErrorDownloadController;
use App\Http\Controllers\Admin\ScheduleImportReconciliationExportController;
use App\Http\Controllers\Admin\UserGuideDownloadController;
use App\Http\Controllers\Admin\WeeklyScheduleReportExcelController;
use App\Http\Controllers\Admin\WeeklyScheduleReportPdfController;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;

Route::get('/download-template/students', function () {
    return Excel::download(new StudentsTemplateExport, 'students_template.xlsx');
})->name('admin.download-template.students');

Route::get('/download-template/departments', function () {
    return Excel::download(new DepartmentsTemplateExport, 'departments_template.xlsx');
})->name('admin.download-template.departments');

Route::get('/download-template/subjects', function () {
    return Excel::download(new SubjectsTemplateExport, 'subjects_template.xlsx');
})->name('admin.download-template.subjects');

Route::get('/download-template/halls', function () {
    return Excel::download(new HallsTemplateExport, 'halls_template.xlsx');
})->name('admin.download-template.halls');

Route::get('/download-template/lecture-sessions', function () {
    return Excel::download(new LectureSessionsTemplateExport, 'lecture_sessions_template.xlsx');
})->name('admin.download-template.lecture-sessions');

Route::get('/download-template/subject-students', function () {
    return Excel::download(new \App\Exports\Templates\SubjectStudentsTemplateExport, 'subject_students_template.xlsx');
})->name('admin.download-template.subject-students');

Route::middleware('auth')
    ->get('/admin/database-backups/download/{fileName}', DatabaseBackupDownloadController::class)
    ->where('fileName', '[A-Za-z0-9._-]+')
    ->name('admin.database-backups.download');

Route::middleware(['auth', 'pin.verified'])
    ->get('/admin/user-guide/download', UserGuideDownloadController::class)
    ->name('admin.user-guide.download');

Route::middleware(['auth', 'role:super-admin|admin', 'pin.verified'])
    ->get('/admin/manara-enrollment-import/errors/{fileName}', ManaraEnrollmentImportErrorDownloadController::class)
    ->where('fileName', 'manara-enrollment-errors-[0-9]{8}-[0-9]{6}\.xlsx')
    ->name('admin.manara-enrollment-import.errors.download');

Route::middleware(['auth', 'role:super-admin|admin', 'pin.verified'])
    ->get('/admin/manara-schedule-import/errors/{fileName}', ManaraScheduleImportErrorDownloadController::class)
    ->where('fileName', 'manara-schedule-errors-[0-9]{8}-[0-9]{6}-[A-Fa-f0-9-]{36}\.xlsx')
    ->name('admin.manara-schedule-import.errors.download');

Route::middleware(['auth', 'pin.verified'])
    ->get('/admin/blocked-weekly-slots', BlockedWeeklySlotsCompatibilityRedirectController::class)
    ->name('admin.blocked-weekly-slots.compatibility-redirect');

Route::middleware(['auth', 'pin.verified'])
    ->get('/admin/weekly-schedule-reports/{type}/excel', WeeklyScheduleReportExcelController::class)
    ->where('type', 'comprehensive|by_lecturer|by_hall|by_subject|by_weekday|reconciliation')
    ->name('admin.weekly-schedule-reports.excel');

Route::middleware(['auth', 'pin.verified'])
    ->get('/admin/weekly-schedule-reports/{type}/pdf', WeeklyScheduleReportPdfController::class)
    ->where('type', 'comprehensive|by_lecturer|by_hall|by_subject|by_weekday|reconciliation')
    ->name('admin.weekly-schedule-reports.pdf');
