public function handle($request, Closure $next)
{
    return $next($request)
        ->header('Access-Control-Allow-Origin', env('FRONTEND_URL'))
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->header('Cross-Origin-Opener-Policy', 'same-origin-allow-popups')
        ->header('Cross-Origin-Embedder-Policy', 'require-corp');
}
