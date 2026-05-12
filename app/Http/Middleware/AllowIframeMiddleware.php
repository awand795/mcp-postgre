<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowIframeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 1. Remove X-Frame-Options to allow iframing (or set to ALLOW-FROM but it's deprecated)
        $response->headers->remove('X-Frame-Options');

        // 2. Set Content-Security-Policy to allow being embedded by any ancestor
        // For production, you might want to replace '*' with specific ERP domains
        $response->headers->set('Content-Security-Policy', "frame-ancestors *", false);

        return $response;
    }
}
