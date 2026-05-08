<?php

use App\Http\Middleware\ForceJsonResponseMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
//        web: __DIR__.'/../routes/web.php',
        web: [
            __DIR__ . '/../routes/web.php',
            __DIR__ . '/../routes/auth.php',
            __DIR__ . '/../routes/student.php',
            __DIR__ . '/../routes/teacher.php',
            __DIR__ . '/../routes/lecture.php',
            __DIR__ . '/../routes/admin.php',
        ],

        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            Route::group([], base_path('routes/attendance-stateless.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->alias([
            //    'student' => \App\Http\Middleware\StudentMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'pin.verified' => \App\Http\Middleware\EnsurePinIsVerified::class,
        ])->web(append: [
            \App\Http\Middleware\SetAdminLocale::class,
            \App\Http\Middleware\EnsurePinIsVerified::class,
        ])
            ->throttleApi()
            ->api(
                prepend: ForceJsonResponseMiddleware::class
            );

    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $exception, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return response(__('auth.access_denied_page'), 403);
        });
    })->create();
