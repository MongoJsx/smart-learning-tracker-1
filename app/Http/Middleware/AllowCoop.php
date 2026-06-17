<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AllowCoop
{
    /**
     * Add COOP header to responses to allow popup-based OAuth postMessage.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Allow popups to communicate back via postMessage
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

        // Optional: if you need Cross-Origin-Embedder-Policy for strict embedding, set it explicitly.
        // $response->headers->set('Cross-Origin-Embedder-Policy', 'unsafe-none');

        return $response;
    }
}
