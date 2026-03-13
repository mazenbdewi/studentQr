<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\CustomLoginController;
use App\Http\Controllers\Student\AttendanceController;
use App\Http\Controllers\Student\ProfileController;
use App\Http\Controllers\Student\DashboardController;
use App\Models\LectureSession;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;


Route::get('/lang/{locale}', function ($locale) {

    if (!in_array($locale, ['ar', 'en'])) {
        abort(400);
    }

    session(['locale' => $locale]);

    return back();
})->name('lang.switch');


Route::redirect('/admin/login', '/login');

Route::get('/login', [CustomLoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [CustomLoginController::class, 'login']);

Route::post('/logout', [CustomLoginController::class, 'logout'])
    // ->middleware('auth')
    ->name('logout');


// Public attendance page
Route::get('/attendance', [AttendanceController::class, 'index'])
    ->name('student.attendance');

// QR scan redirect
Route::post(
    '/student/attendance/scan/{session}',
    [AttendanceController::class, 'scan']
)->name('student.attendance.scan');

// QR code display for teachers
Route::get(
    '/lecture-session/{session}/qr',
    [AttendanceController::class, 'showQr']
)->name('teacher.lecture-session.qr');

// Student routes group
Route::prefix('student')
    ->name('student.')
    ->group(function () {

        // Dashboard
        Route::get('/', [DashboardController::class, 'index'])
            ->name('dashboard');

        // Attendance verification via session
        Route::get('/attendance/{session}', [AttendanceController::class, 'verifySession'])
            ->name('attendance.verify.form');

        // Store attendance
        Route::post('/attendance/store/{session}', [AttendanceController::class, 'store'])
            ->name('attendance.store');

        // Store attendance sync (fallback)
        Route::post('/attendance/store-sync/{session}', [AttendanceController::class, 'storeSync'])
            ->name('attendance.store.sync');

        // Check attendance status (AJAX)
        Route::get('/attendance/check-status/{session}', [AttendanceController::class, 'checkStatus'])
            ->name('attendance.check.status');

        // Verify token from QR
        Route::get('/attendance/verify/{token}', [AttendanceController::class, 'verifyToken'])
            ->name('attendance.verify.token');

    });

// Legacy routes for backward compatibility
Route::get('/student/attendance/{session}', [AttendanceController::class, 'verifySession'])
    ->name('student.attendance.verify.form');

Route::post('/student/attendance/store/{session}', [AttendanceController::class, 'store'])
    ->name('student.attendance.store');

Route::post('/student/attendance/store-sync/{session}', [AttendanceController::class, 'storeSync'])
    ->name('student.attendance.store.sync');

Route::get('/student/attendance/check-status/{session}', [AttendanceController::class, 'checkStatus'])
    ->name('student.attendance.check.status');

Route::get('/student/attendance/verify/{token}', [AttendanceController::class, 'verifyToken'])
    ->name('student.attendance.verify.token');

// Routes for manager
Route::middleware(['auth', 'role:manager'])
    ->prefix('manager')
    ->name('manager.')
    ->group(function () {

        Route::get('/', [\App\Http\Controllers\Manager\DashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/profile', [App\Http\Controllers\Manager\ProfileController::class, 'edit'])
            ->name('profile');

        Route::put('/profile', [App\Http\Controllers\Manager\ProfileController::class, 'update'])
            ->name('profile.update');

    });

// Routes for teachers
Route::middleware(['auth', 'role:course_lecturer'])
    ->prefix('teacher')
    ->name('teacher.')
    ->group(function () {

        Route::get('/', [App\Http\Controllers\Teacher\DashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/profile', [App\Http\Controllers\Teacher\ProfileController::class, 'edit'])
            ->name('profile');

        Route::put('/profile', [App\Http\Controllers\Teacher\ProfileController::class, 'update'])
            ->name('profile.update');

        // Session status check for QR page
        Route::get('/session/{session}/status', [App\Http\Controllers\Teacher\AttendanceController::class, 'sessionStatus'])
            ->name('session.status');

        // Mark QR as expired
        Route::post('/session/{session}/expire-qr', [App\Http\Controllers\Teacher\AttendanceController::class, 'expireQr'])
            ->name('session.expire-qr');
    });

// Department API
Route::get('/departments/{faculty}', function ($faculty) {

    return \App\Models\Department::where('faculty_id', $faculty)
        ->select('id', 'name')
        ->get();
});

// Home redirect
Route::get('/', function () {

    if (auth()->check()) {

        $user = auth()->user();

        return match (true) {
            $user->hasRole('super-admin') => redirect('/super-admin'),
            $user->hasRole('course_lecturer') => redirect('/teacher'),
            $user->hasRole('manager') => redirect('/manager'),
            default => redirect('/login')
        };
    }

    return redirect('/login');
});