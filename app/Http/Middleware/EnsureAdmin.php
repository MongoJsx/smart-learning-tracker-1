<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $isAdmin = false;

        if ($user) {
            if (property_exists($user, 'role') || isset($user->role)) {
                $isAdmin = $user->role === 'admin';
            }
            if (! $isAdmin && (property_exists($user, 'is_admin') || isset($user->is_admin))) {
                $isAdmin = (bool) $user->is_admin;
            }
        }

        if (! $isAdmin) {
            return response()->json(['message' => 'Admin only'], 403);
        }

        return $next($request);
    }
}
