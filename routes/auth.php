<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\CustomLoginController;


Route::get('/lang/{locale}', function ($locale) {

    if (!in_array($locale, ['ar', 'en'])) {
        abort(400);
    }

    session(['locale' => $locale]);

    return back();

})->name('lang.switch');

Route::get('/login', [CustomLoginController::class, 'showLoginForm'])
    ->name('login');

Route::post('/login', [CustomLoginController::class, 'login']);
Route::get('/register', [CustomLoginController::class,'showRegister'])
    ->name('register');

Route::post('/register', [CustomLoginController::class,'register']);
Route::post('/logout', [CustomLoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');
