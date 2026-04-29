<?php

namespace App\Mcp\Tools\Concerns;

use App\Services\Core\QueryService;
use Illuminate\Support\Facades\Auth;

/**
 * Trait ResolvesRbac
 *
 * Shared concern untuk semua MCP Tools.
 * Menyelesaikan allowed databases berdasarkan user yang sedang login
 * (diinject oleh McpAuthMiddleware dari Bearer token).
 */
trait ResolvesRbac
{
    /**
     * Resolve allowed databases untuk user yang sedang terautentikasi.
     * Memanfaatkan QueryService yang sudah ada — tidak ada logika duplikat.
     */
    protected function resolveAllowedDatabases(): array
    {
        $queryService = new QueryService();
        return $queryService->getAllowedTables();
    }

    /**
     * Resolve QueryService dengan allowed databases sudah diset.
     */
    protected function queryService(): QueryService
    {
        $qs = new QueryService();
        // getAllowedTables() sudah otomatis resolve dari Auth::user() via QueryService
        return $qs;
    }
}
