<?php

use App\Http\Controllers\Student\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::get('/student/attendance/{session}', [AttendanceController::class, 'verifySession'])
    ->whereNumber('session')
    ->name('student.attendance.verify.form');

Route::post('/student/attendance/store/{session}', [AttendanceController::class, 'store'])
    ->whereNumber('session')
    ->name('student.attendance.store');

Route::post('/student/attendance/store-sync/{session}', [AttendanceController::class, 'storeSync'])
    ->whereNumber('session')
    ->name('student.attendance.store.sync');

Route::get('/student/attendance/check-status/{session}', [AttendanceController::class, 'checkStatus'])
    ->whereNumber('session')
    ->name('student.attendance.check.status');

Route::get('/student/attendance/verify/{token}', [AttendanceController::class, 'verifyToken'])
    ->name('student.attendance.verify.token');
