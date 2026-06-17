<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceCorsHeaders
{
    private function allowedOrigins(): array
    {
        $raw = env('FRONTEND_ORIGINS', env('FRONTEND_URL', ''));
        $items = array_filter(array_map('trim', explode(',', (string) $raw)));

        if (! $items) {
            $items = ['http://localhost:5173', 'http://127.0.0.1:5173'];
        }

        return array_values(array_unique($items));
    }

    private function isAllowedOrigin(string $origin): bool
    {
        return in_array($origin, $this->allowedOrigins(), true);
    }

    private function applyHeaders(Response $response, string $origin): void
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Vary', 'Origin');
        $response->headers->set(
            'Access-Control-Allow-Methods',
            'GET, POST, PUT, PATCH, DELETE, OPTIONS'
        );
        $response->headers->set(
            'Access-Control-Allow-Headers',
            'Origin, Content-Type, Accept, Authorization, X-Requested-With'
        );
        $response->headers->set('Access-Control-Max-Age', '86400');
    }

    public function handle(Request $request, Closure $next)
    {
        $origin = $request->headers->get('Origin');

        if ($request->isMethod('OPTIONS')) {
            $response = response('', 204);
            if ($origin && $this->isAllowedOrigin($origin)) {
                $this->applyHeaders($response, $origin);
            }
            return $response;
        }

        $response = $next($request);

        if ($origin && $this->isAllowedOrigin($origin)) {
            $this->applyHeaders($response, $origin);
        }

        return $response;
    }
}
