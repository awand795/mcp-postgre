<?php

namespace App\Mcp\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * McpTokenGuard
 *
 * Authenticates MCP requests menggunakan Bearer token (API key personal user).
 *
 * Flow:
 *   1. Client MCP kirim request dengan header: Authorization: Bearer <token>
 *   2. McpTokenGuard cari user dari tabel users / personal_access_tokens
 *   3. Inject user ke Auth::setUser() agar RBAC di QueryService/SchemaService bisa berjalan normal
 *   4. Semua tool MCP langsung memakai getAllowedTables() dari QueryService — tidak ada logika duplikat
 *
 * Token dibuat via Admin Dashboard → User Management → "Generate MCP Token"
 * dan disimpan di kolom mcp_api_token (hashed) di tabel users.
 */
class McpTokenGuard
{
    /**
     * Resolve user dari Bearer token di request.
     * Return null jika token tidak valid.
     */
    public static function resolveUser(string $token): ?User
    {
        if (empty($token)) {
            return null;
        }

        // Cache resolusi token selama 5 menit (hindari DB hit setiap request MCP)
        $cacheKey = 'mcp_token_user_' . hash('sha256', $token);

        $userId = Cache::remember($cacheKey, 300, function () use ($token) {
            // Cari user dengan mcp_api_token yang cocok
            $user = User::where('mcp_api_token', hash('sha256', $token))
                ->where('is_active', true)
                ->first();

            return $user?->id;
        });

        if (!$userId) {
            Log::warning('[McpTokenGuard] Invalid or expired MCP token.');
            return null;
        }

        return User::find($userId);
    }

    /**
     * Invalidate cached token (panggil saat token di-revoke).
     */
    public static function invalidateToken(string $token): void
    {
        $cacheKey = 'mcp_token_user_' . hash('sha256', $token);
        Cache::forget($cacheKey);
    }
}
