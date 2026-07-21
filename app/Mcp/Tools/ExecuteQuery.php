<?php

namespace App\Mcp\Tools;

use App\Services\ToolCallExecutor;
use Illuminate\Support\Facades\DB;
use PhpMcp\Server\Attributes\McpTool;

class ExecuteQuery
{
    /**
     * Execute a read-only SQL SELECT query on any configured database.
     * Access is restricted to tables allowed by the user role.
     * Always call describe_table first to get exact column names.
     */
    #[McpTool(
        name: 'execute_query',
        description: 'Execute a read-only SQL SELECT query on any configured database. Access is restricted to tables allowed by the user role. Always call describe_table first to get exact column names.'
    )]
    public function handle(string $database_code, string $sql, string $label = ''): array
    {
        // Layer 1: SELECT only
        $stripped = preg_replace('/--[^\n]*/', '', $sql);
        $stripped = preg_replace('/\/\*.*?\*\//s', '', $stripped);
        $stripped = trim($stripped);

        if (!preg_match('/^\s*SELECT\b/i', $stripped)) {
            throw new \InvalidArgumentException('Only SELECT queries are allowed.');
        }

        // Layer 2: Forbidden keywords
        $dbModel = \App\Models\DatabaseConnection::where('database', $database_code)->active()->first();
        if (!$dbModel) {
            throw new \InvalidArgumentException("Database '{$database_code}' not found or inactive.");
        }

        $driver    = $dbModel->driver;
        $forbidden = [
            'insert','update','delete','merge','upsert','drop','truncate','alter','create',
            'rename','grant','revoke','execute','exec','call','do','vacuum',
            'pg_read_file','pg_write_file','lo_import','lo_export','dblink','dblink_exec',
        ];
        if ($driver === 'pgsql')                         $forbidden[] = 'copy';
        elseif ($driver === 'sqlsrv')                    $forbidden[] = 'bulk';
        elseif (in_array($driver, ['mysql','mariadb']))  { $forbidden[] = 'load'; $forbidden[] = 'into'; }
        elseif ($driver === 'clickhouse')                 { $forbidden[] = 'optimize'; $forbidden[] = 'attach'; $forbidden[] = 'detach'; $forbidden[] = 'system'; }

        $lower = strtolower($stripped);
        foreach ($forbidden as $kw) {
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/', $lower)) {
                throw new \InvalidArgumentException("Keyword '{$kw}' is not allowed.");
            }
        }

        // Layer 3: Single statement
        $clean = rtrim($stripped, '; ');
        if (str_contains($clean, ';')) {
            throw new \InvalidArgumentException('Only one query per call is allowed.');
        }

        // Layer 4: RBAC
        $executor = new ToolCallExecutor();
        $allowed  = $executor->getAllowedTables();

        if (!isset($allowed[$database_code])) {
            throw new \InvalidArgumentException("Access denied: no access to database '{$database_code}'.");
        }

        $allowedTables  = [];
        $allowedSchemas = [];
        $hasWildcard    = false;
        foreach ($allowed[$database_code] as $sch => $tbls) {
            if ($sch !== '*') $allowedSchemas[] = strtolower($sch);
            foreach ($tbls as $t) {
                $n = is_array($t) ? ($t['name'] ?? '') : (string) $t;
                if ($n === '*') { $hasWildcard = true; } else { $allowedTables[] = strtolower($n); }
            }
        }

        if (!$hasWildcard) {
            $identPat = '(?:"([^"]+)"|([a-zA-Z0-9_]+))';
            if (preg_match_all('/(?:from|join)\s+' . $identPat . '(?:\s*\.\s*' . $identPat . ')?/i', $clean, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $hasDot = !empty($m[3]) || !empty($m[4]);
                    $first  = strtolower(!empty($m[1]) ? $m[1] : $m[2]);
                    $tbl    = $hasDot ? strtolower(!empty($m[3]) ? $m[3] : $m[4]) : $first;
                    $sqlKw  = ['select','where','on','and','or','as','lateral','join','inner','left','right','outer','cross','full'];
                    if (in_array($tbl, $sqlKw)) continue;
                    if (!in_array($tbl, $allowedTables)) {
                        throw new \InvalidArgumentException("Access denied: table '{$tbl}' is not allowed.");
                    }
                }
            }
        }

        // Layer 5: Execute
        $connName = "mcp_query_{$database_code}";
        try {
            DB::purge($connName);
            config(["database.connections.{$connName}" => $dbModel->getConnectionConfig()]);

            if ($driver === 'pgsql') {
                DB::connection($connName)->statement('SET statement_timeout = 0');
            } elseif (in_array($driver, ['mysql','mariadb'])) {
                DB::connection($connName)->statement('SET SESSION max_execution_time = 0');
            }
            // ClickHouse: tidak support SET timeout commands — skip

            $rows = DB::connection($connName)->select($clean);
            DB::purge($connName);

            $data = array_map(fn($r) => (array) $r, $rows);

            return [
                'database_code' => $database_code,
                'driver'        => $driver,
                'label'         => $label,
                'rows_returned' => count($data),
                'columns'       => !empty($data) ? array_keys($data[0]) : [],
                'rows'          => $data,
            ];
        } catch (\Exception $e) {
            DB::purge($connName);
            throw new \RuntimeException("Query failed: " . $e->getMessage());
        }
    }
}
