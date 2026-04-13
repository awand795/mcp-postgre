<?php

namespace App\Mcp\Tools;

use App\Services\ToolCallExecutor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpMcp\Server\Attributes\McpTool;

class ExecuteQuery
{
    /**
     * Execute a read-only SQL query (SELECT only) on any configured database.
     * Only tables allowed by the user's role can be accessed.
     *
     * @param string $database_code The database code (e.g., 'mbi_prod')
     * @param string $query The SQL query to execute
     */
    #[McpTool(name: 'execute_query')]
    public function handle(string $database_code, string $query): array
    {
        // ── LAYER 1: Hanya SELECT ─────────────────────────────────────────────
        if (!preg_match('/^\s*select/i', $query)) {
            throw new \InvalidArgumentException('Only SELECT queries are allowed.');
        }

        // ── LAYER 2: Blokir keyword berbahaya (driver-aware) ─────────────────
        // Get driver type for driver-specific forbidden keywords
        $dbModel = \App\Models\DatabaseConnection::where('code', $database_code)->active()->first();
        if (!$dbModel) {
            throw new \InvalidArgumentException("Database '{$database_code}' not found or inactive.");
        }

        $driver = $dbModel->driver;

        $forbidden = [
            'insert', 'update', 'delete', 'merge', 'upsert',
            'drop', 'truncate', 'alter', 'create', 'rename',
            'grant', 'revoke', 'execute', 'exec', 'call', 'do',
            'vacuum', 'pg_read_file', 'pg_write_file',
            'lo_import', 'lo_export', 'dblink', 'dblink_exec',
        ];

        // Add driver-specific forbidden keywords
        if ($driver === 'pgsql') {
            $forbidden[] = 'copy';
        } elseif ($driver === 'sqlsrv') {
            $forbidden[] = 'bulk';
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            $forbidden[] = 'load';
            $forbidden[] = 'into';
        }

        $lower = strtolower($query);
        foreach ($forbidden as $kw) {
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/', $lower)) {
                throw new \InvalidArgumentException("Keyword '{$kw}' is not allowed.");
            }
        }

        // ── LAYER 3: Blokir multiple statements ──────────────────────────────
        $cleanQuery = rtrim($query, '; ');
        if (str_contains($cleanQuery, ';')) {
            throw new \InvalidArgumentException('Only one query per call is allowed.');
        }

        // ── LAYER 4: RBAC — validasi akses tabel berdasarkan role ────────────
        $executor = new ToolCallExecutor();
        $allowed = $executor->getAllowedTables();

        if (!isset($allowed[$database_code])) {
            throw new \InvalidArgumentException("Access denied: You don't have access to database '{$database_code}'.");
        }

        // Collect all allowed tables and schemas for this database
        $allowedTablesForDb = [];
        $allowedSchemasForDb = [];
        foreach ($allowed[$database_code] as $sch => $tbls) {
            $allowedSchemasForDb[] = strtolower($sch);
            foreach ($tbls as $tbl) {
                $allowedTablesForDb[] = strtolower($tbl);
            }
        }

        // Extract table names from query
        if (preg_match_all('/(?:from|join)\s+(?:([a-zA-Z0-9_]+)\.)?([a-zA-Z0-9_]+)/i', $cleanQuery, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $schemaUsed = !empty($match[1]) ? strtolower(trim($match[1])) : null;
                $tbl = strtolower(trim($match[2]));

                // Skip SQL keywords
                if (in_array($tbl, ['select', 'where', 'on', 'and', 'or', 'as', 'lateral', 'join', 'inner', 'left', 'right', 'outer'])) {
                    continue;
                }

                if (!in_array($tbl, $allowedTablesForDb)) {
                    throw new \InvalidArgumentException("Access denied: table '{$tbl}' is not allowed for your role.");
                }

                if ($schemaUsed && !in_array($schemaUsed, $allowedSchemasForDb)) {
                    throw new \InvalidArgumentException("Access denied: schema '{$schemaUsed}' is not allowed.");
                }
            }
        }

        // ── LAYER 5: Execute Query ───────────────────────────────────────────
        $connName = "temp_conn_{$database_code}";
        try {
            $adapter = $dbModel->getAdapter();

            DB::purge($connName);
            config(["database.connections.{$connName}" => $dbModel->getConnectionConfig()]);

            // Set driver-specific timeout
            if ($driver === 'pgsql') {
                DB::connection($connName)->statement('SET statement_timeout = 0');
            } elseif ($driver === 'mysql' || $driver === 'mariadb') {
                DB::connection($connName)->statement('SET SESSION max_execution_time = 0');
            }

            $rows = DB::connection($connName)->select($cleanQuery);
            DB::purge($connName);

            // Transform result to standard format
            $result = [];
            foreach ($rows as $row) {
                $result[] = (array) $row;
            }

            return [
                'database_code' => $database_code,
                'driver' => $driver,
                'rows_returned' => count($result),
                'columns' => !empty($result) ? array_keys($result[0]) : [],
                'rows' => $result,
            ];
        } catch (\Exception $e) {
            DB::purge($connName);
            throw new \RuntimeException("Query execution failed: " . $e->getMessage());
        }
    }
}
