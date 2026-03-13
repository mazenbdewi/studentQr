<?php

use App\Http\Middleware\ForceJsonResponseMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
    )
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->alias([
            //    'student' => \App\Http\Middleware\StudentMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        ])->web(append: [
            \App\Http\Middleware\SetAdminLocale::class,
        ])
            ->throttleApi()
            ->api(
                prepend: ForceJsonResponseMiddleware::class
            );

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
