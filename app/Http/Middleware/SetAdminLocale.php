<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAdminLocale
{

    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale', config('app.locale'));

        app()->setLocale($locale);


        view()->share('direction', in_array($locale, ['ar']) ? 'rtl' : 'ltr');

        return $next($request);
//        app()->setLocale(session('locale', config('app.locale')));

    }

}
