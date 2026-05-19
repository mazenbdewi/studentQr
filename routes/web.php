<?php

use App\Http\Controllers\Auth\CustomLoginController;
use App\Http\Controllers\Auth\PinVerificationController;
use App\Http\Controllers\SeminarAttendanceController;
use App\Http\Controllers\Student\AttendanceController;
use App\Http\Controllers\Student\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/lang/{locale}', function ($locale) {

    if (! in_array($locale, ['ar', 'en'])) {
        abort(400);
    }

    session(['locale' => $locale]);

    return back();
})->name('lang.switch');

Route::redirect('/admin/dashboard', '/admin')
    ->middleware(['auth', 'pin.verified'])
    ->name('admin.dashboard.redirect');

Route::get('/login', [CustomLoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [CustomLoginController::class, 'login']);

Route::post('/logout', [CustomLoginController::class, 'logout'])
    // ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/pin/set', [PinVerificationController::class, 'showSet'])
        ->name('pin.set.form');

    Route::post('/pin/set', [PinVerificationController::class, 'set'])
        ->name('pin.set');

    Route::get('/pin/verify', [PinVerificationController::class, 'show'])
        ->name('pin.verify.form');

    Route::post('/pin/verify', [PinVerificationController::class, 'verify'])
        ->name('pin.verify');
});

// Public attendance page
Route::get('/attendance', [AttendanceController::class, 'index'])
    ->name('student.attendance');

Route::get('/seminar-attendance/{token}', [SeminarAttendanceController::class, 'scan'])
    ->name('seminars.attendance.scan');

Route::post('/seminar-attendance/{token}', [SeminarAttendanceController::class, 'store'])
    ->name('seminars.attendance.store');

// QR scan redirect
Route::post(
    '/student/attendance/scan/{session}',
    [AttendanceController::class, 'scan']
)->name('student.attendance.scan');

// QR code display for teachers
Route::get(
    '/lecture-session/{session}/qr',
    [AttendanceController::class, 'showQr']
)->middleware(['auth', 'role:super-admin|course_lecturer'])
    ->name('teacher.lecture-session.qr');

// Student routes group
Route::middleware(['auth', 'role:student', 'pin.verified'])
    ->prefix('student')
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

// Routes for manager
Route::middleware(['auth', 'role:manager', 'pin.verified'])
    ->prefix('manager')
    ->name('manager.')
    ->group(function () {

        Route::get('/', [\App\Http\Controllers\Manager\DashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/profile', [App\Http\Controllers\Manager\ProfileController::class, 'edit'])
            ->name('profile');

        Route::put('/profile', [App\Http\Controllers\Manager\ProfileController::class, 'update'])
            ->name('profile.update');

        Route::put('/profile/password', [App\Http\Controllers\Manager\ProfileController::class, 'updatePassword'])
            ->name('profile.password.update');

        Route::put('/profile/pin', [App\Http\Controllers\Manager\ProfileController::class, 'updatePin'])
            ->name('profile.pin.update');

    });

// Routes for teachers
Route::middleware(['auth', 'role:course_lecturer', 'pin.verified'])
    ->prefix('teacher')
    ->name('teacher.')
    ->group(function () {

        Route::redirect('/', '/admin')
            ->name('dashboard');

        Route::get('/profile', [App\Http\Controllers\Teacher\ProfileController::class, 'edit'])
            ->name('profile');

        Route::put('/profile', [App\Http\Controllers\Teacher\ProfileController::class, 'update'])
            ->name('profile.update');

        Route::put('/profile/password', [App\Http\Controllers\Teacher\ProfileController::class, 'updatePassword'])
            ->name('profile.password.update');

        Route::put('/profile/pin', [App\Http\Controllers\Teacher\ProfileController::class, 'updatePin'])
            ->name('profile.pin.update');

    });

Route::middleware(['auth', 'role:super-admin|course_lecturer', 'pin.verified'])
    ->prefix('teacher')
    ->name('teacher.')
    ->group(function () {
        // Session status check for QR page
        Route::get('/session/{session}/status', [App\Http\Controllers\Teacher\AttendanceController::class, 'sessionStatus'])
            ->name('session.status');

        // Mark QR as expired
        Route::post('/session/{session}/expire-qr', [App\Http\Controllers\Teacher\AttendanceController::class, 'expireQr'])
            ->name('session.expire-qr');

        Route::get('/seminars', [App\Http\Controllers\Teacher\SeminarController::class, 'index'])
            ->name('seminars.index');

        Route::get('/seminars/create', [App\Http\Controllers\Teacher\SeminarController::class, 'create'])
            ->name('seminars.create');

        Route::post('/seminars', [App\Http\Controllers\Teacher\SeminarController::class, 'store'])
            ->name('seminars.store');

        Route::get('/seminars/{seminar}', [App\Http\Controllers\Teacher\SeminarController::class, 'show'])
            ->name('seminars.show');

        Route::post('/seminars/{seminar}/start', [App\Http\Controllers\Teacher\SeminarController::class, 'start'])
            ->name('seminars.start');

        Route::get('/seminars/{seminar}/open-qr', [App\Http\Controllers\Teacher\SeminarController::class, 'openQr'])
            ->name('seminars.open-qr');

        Route::get('/seminars/{seminar}/qr', [App\Http\Controllers\Teacher\SeminarController::class, 'qr'])
            ->name('seminars.qr');

        Route::get('/seminars/{seminar}/status', [App\Http\Controllers\Teacher\SeminarController::class, 'status'])
            ->name('seminars.status');

        Route::post('/seminars/{seminar}/expire-qr', [App\Http\Controllers\Teacher\SeminarController::class, 'expireQr'])
            ->name('seminars.expire-qr');

        Route::get('/seminars/{seminar}/export', [App\Http\Controllers\Teacher\SeminarController::class, 'export'])
            ->name('seminars.export');
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
            $user->hasAnyRole(['super-admin', 'admin']) => redirect('/admin'),
            $user->hasRole('course_lecturer') => redirect('/admin'),
            $user->hasRole('manager') => redirect('/manager'),
            default => redirect('/login')
        };
    }

    return redirect('/login');
});
