<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequireBearerToken
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->bearerToken()) {
            return response()->json([
                'message' => 'Missing Bearer token. Send header: Authorization: Bearer <token>',
            ], 401);
        }

        Auth::shouldUse('sanctum');

        return $next($request);
    }
}
