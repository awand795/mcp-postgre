<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware yang mendukung dua mode autentikasi:
 *
 * 1. Bearer Token (Sanctum) — untuk iframe cross-domain via HTTP
 *    Header: Authorization: Bearer {token}
 *
 * 2. Session Cookie — untuk akses langsung browser (login biasa)
 *
 * Urutan pengecekan: Bearer token dulu, fallback ke session.
 */
class SanctumOrSession
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Coba autentikasi via Bearer token (iframe mode)
        $bearerToken = $request->bearerToken() ?: $request->query('token');
        
        \Illuminate\Support\Facades\Log::info('[SanctumOrSession] Path: ' . $request->path() . ' | Has Token: ' . ($bearerToken ? 'Yes (' . substr($bearerToken, 0, 10) . '...)' : 'No'));

        if ($bearerToken) {
            $accessToken = PersonalAccessToken::findToken($bearerToken);

            if ($accessToken && $accessToken->tokenable) {
                Auth::setUser($accessToken->tokenable);
                // Update last_used_at
                $accessToken->forceFill(['last_used_at' => now()])->save();
                \Illuminate\Support\Facades\Log::info('[SanctumOrSession] Authenticated via Bearer token: ' . $accessToken->tokenable->email);
                return $next($request);
            }
            \Illuminate\Support\Facades\Log::warning('[SanctumOrSession] Token present but invalid or expired.');
        }

        // 2. Coba autentikasi via Session (normal browser mode)
        if (Auth::check()) {
            \Illuminate\Support\Facades\Log::info('[SanctumOrSession] Authenticated via Session Cookie: ' . Auth::user()->email);
            return $next($request);
        }

        \Illuminate\Support\Facades\Log::warning('[SanctumOrSession] Authentication failed. No valid token or session.');

        // 3. Jika kedua mode autentikasi gagal:
        if ($bearerToken) {
            // Jika request membawa token tapi tidak valid
            if ($request->expectsJson()) {
                return response()->json(['error' => __('Token tidak valid atau sudah kadaluarsa.')], 401);
            }
            return redirect()->route('sso.expired');
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => __('Unauthenticated.')], 401);
        }

        return redirect()->route('login');
    }
}
