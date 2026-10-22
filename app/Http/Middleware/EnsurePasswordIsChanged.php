<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->user()?->must_change_password
            && ! in_array($request->route()?->getName(), [
                'password.first-login',
                'password.first-login.update',
                'logout',
            ], true)
        ) {
            return redirect()->route('password.first-login');
        }

        return $next($request);
    }
}
