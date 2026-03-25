<?php

namespace App\Mcp\Tools;

use App\Services\ToolCallExecutor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpMcp\Server\Attributes\McpTool;

class ExecuteQuery
{
    /**
     * Execute a read-only PostgreSQL query (SELECT only).
     * Hanya tabel yang diizinkan berdasarkan role user yang bisa diakses.
     *
     * @param string $query The SQL query to execute.
     */
    #[McpTool(name: 'execute_query')]
    public function handle(string $query): array
    {
        // ── LAYER 1: Hanya SELECT ─────────────────────────────────────────────
        if (!preg_match('/^\s*select/i', $query)) {
            throw new \InvalidArgumentException('Only SELECT queries are allowed.');
        }

        // ── LAYER 2: Blokir keyword berbahaya ────────────────────────────────
        $forbidden = [
            'insert', 'update', 'delete', 'drop', 'truncate', 'alter',
            'create', 'grant', 'revoke', 'execute', 'exec', 'call', 'copy',
        ];
        $lower = strtolower($query);
        foreach ($forbidden as $kw) {
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/', $lower)) {
                throw new \InvalidArgumentException("Keyword '{$kw}' is not allowed.");
            }
        }

        // ── LAYER 3: RBAC — validasi akses tabel berdasarkan role ────────────
        $executor = new ToolCallExecutor();
        $allowed  = $executor->getAllowedTables();

        if (preg_match_all('/(?:from|join)\s+(?:sch_mbi\.)?([a-zA-Z0-9_]+)/i', $query, $matches)) {
            foreach ($matches[1] as $tbl) {
                $tbl = strtolower(trim($tbl));
                if (in_array($tbl, ['select', 'where', 'on', 'and', 'or', 'as', 'lateral'])) continue;
                if (!in_array($tbl, $allowed)) {
                    throw new \InvalidArgumentException("Access denied: table '{$tbl}' is not allowed for your role.");
                }
            }
        }

        // ── LAYER 4: Paksa LIMIT jika tidak ada ─────────────────────────────
        $cleanQuery = rtrim($query, '; ');
        if (!preg_match('/\blimit\b/i', $cleanQuery)) {
            $cleanQuery .= ' LIMIT 100';
        }

        return DB::connection('pgsql_mbi')->select($cleanQuery);
    }
}
