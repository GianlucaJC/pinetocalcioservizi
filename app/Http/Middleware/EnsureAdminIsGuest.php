<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIsGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('admin_authenticated')) {
            return redirect()->route('admin.employees.index');
        }

        return $next($request);
    }
}
