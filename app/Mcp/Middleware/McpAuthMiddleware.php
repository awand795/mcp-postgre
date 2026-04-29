<?php

namespace App\Mcp\Middleware;

use App\Mcp\Auth\McpTokenGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * McpAuthMiddleware
 *
 * Middleware untuk mengautentikasi semua request ke MCP Server.
 *
 * Cara kerja:
 *   1. Ambil Bearer token dari header Authorization
 *   2. Resolve user via McpTokenGuard (cache 5 menit)
 *   3. Inject user ke Auth::setUser() → RBAC di QueryService otomatis jalan
 *   4. Tolak request dengan 401 jika token tidak valid
 *
 * Cara registrasi di config/mcp.php:
 *   'middleware' => [App\Mcp\Middleware\McpAuthMiddleware::class]
 */
class McpAuthMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $token = $this->extractBearerToken($request);

        if (!$token) {
            Log::warning('[McpAuth] Request tanpa Bearer token ditolak. IP: ' . $request->ip());
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'MCP Server memerlukan Bearer token. Tambahkan header: Authorization: Bearer <mcp_api_token>',
            ], 401);
        }

        $user = McpTokenGuard::resolveUser($token);

        if (!$user) {
            Log::warning('[McpAuth] Token tidak valid atau user tidak aktif. IP: ' . $request->ip());
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Token MCP tidak valid atau user tidak aktif.',
            ], 401);
        }

        // Inject user ke Auth guard sehingga Auth::user() tersedia
        // di QueryService, SchemaService, dan seluruh RBAC layer
        Auth::setUser($user);

        Log::info('[McpAuth] User authenticated via MCP token: ' . $user->id . ' (' . $user->email . ')');

        return $next($request);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        // Fallback: cek query param ?token=... (untuk testing / Claude Desktop)
        return $request->query('token');
    }
}
