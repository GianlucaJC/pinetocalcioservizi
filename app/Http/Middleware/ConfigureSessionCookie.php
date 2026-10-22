<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigureSessionCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $sessionCookie = $request->is('admin', 'admin/*')
            ? config('admin.session_cookie')
            : config('session.base_cookie');

        config(['session.cookie' => $sessionCookie]);
        app('session')->driver()->setName($sessionCookie);

        return $next($request);
    }
}
