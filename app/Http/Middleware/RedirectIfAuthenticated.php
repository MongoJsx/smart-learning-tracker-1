<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Route;

class RedirectIfAuthenticated
{
    public function handle($request, Closure $next, ...$guards)
    {
        if ($request->user()) {
            return redirect('/');
        }

        return $next($request);
    }
}
