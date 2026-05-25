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
        // Mode 1: Bearer token (iframe HTTP cross-domain)
        $bearerToken = $request->bearerToken() ?: $request->query('token');
        if ($bearerToken) {
            $accessToken = PersonalAccessToken::findToken($bearerToken);

            if ($accessToken && $accessToken->tokenable) {
                Auth::setUser($accessToken->tokenable);
                // Update last_used_at
                $accessToken->forceFill(['last_used_at' => now()])->save();
                return $next($request);
            }

            // Token ada tapi tidak valid
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Token tidak valid atau sudah kadaluarsa.'], 401);
            }
            return redirect()->route('sso.expired');
        }

        // Mode 2: Session (akses langsung browser)
        if (Auth::check()) {
            return $next($request);
        }

        // Tidak ada keduanya
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        return redirect()->route('login');
    }
}
