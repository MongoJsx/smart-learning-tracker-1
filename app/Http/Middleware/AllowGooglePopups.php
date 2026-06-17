<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AllowGooglePopups
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // ✅ ให้ Google popup ใช้ postMessage กลับมาได้
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

        // (ถ้าคุณเคยตั้ง COEP ไว้แล้วทำให้พัง ให้ปิดไว้ชั่วคราว)
        // $response->headers->remove('Cross-Origin-Embedder-Policy');

        return $response;
    }
}
