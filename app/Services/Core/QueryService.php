<?php

namespace App\Services\Core;

use App\Models\RolePermission;
use App\Services\BaseService;
use App\Services\Database\DriverFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * QueryService
 *
 * Handles SQL query execution with 6-layer security validation,
 * RBAC table access control, and currency column detection.
 */
class QueryService extends BaseService
{
    /**
     * Cached allowed databases for RBAC.
     */
    private ?array $cachedAllowedDatabases = null;

    /**
     * Query result cache TTL in seconds.
     * Short TTL to avoid stale data but reduce duplicate queries.
     */
    private int $queryCacheTtl = 60;

    /**
     * Set cached allowed databases (used before session_write_close).
     */
    public function setAllowedTables(array $databases): void
    {
        $this->cachedAllowedDatabases = $databases;
    }

    /**
     * Get allowed databases, schemas, and tables for current user (RBAC).
     */
    public function getAllowedTables(): array
    {
        if ($this->cachedAllowedDatabases !== null) {
            return $this->cachedAllowedDatabases;
        }

        if (!Auth::check()) {
            return [];
        }

        $user = Auth::user();

        // Admin sees all active databases
        if ($user->is_admin) {
            return cache()->remember('agentic_all_dbs_admin_v3', 600, function () {
                $connections = \App\Models\DatabaseConnection::where('is_active', true)->get();
                $result = [];
                foreach ($connections as $conn) {
                    $tables = $conn->getTables();
                    // Use $conn->database as the key — this is the identifier passed by AI
                    // in tool calls (database_code). All service lookups use ->where('database', ...)
                    $dbIdentifier = $conn->database;
                    foreach ($tables as $t) {
                        $sch  = $t['schema_name'];
                        $tbl  = $t['table_name'];
                        $desc = $t['description'] ?? '';
                        $result[$dbIdentifier][$sch][] = [
                            'name'        => $tbl,
                            'description' => $desc,
                        ];
                    }
                }
                return $result;
            });
        }

        $roleId = $user->role;
        return cache()->remember("agentic_allowed_dbs_role_v2_{$roleId}", 600, function () use ($roleId, $user) {
            $permissions = RolePermission::where('role_id', $roleId)->get();
            $result = [];
            foreach ($permissions as $p) {
                // To get description for regular roles, we might need a lookup, 
                // but for now let's at least structure it consistently.
                $result[$p->database_code][$p->schema_name][] = [
                    'name' => $p->table_name,
                    'description' => '' // Roles usually have predefined tables
                ];
            }
            return $result;
        });
    }

    /**
     * Execute SQL SELECT query with 6-layer security validation.
     */
    public function executeQuery(string $databaseCode, string $sql, string $label, array $currencyColumns = []): string
    {
        if (empty($sql)) {
            return $this->errorResponse('sql is required');
        }

        // ── LAYER 1: Strip comments ──────────────────────────────────────────
        $sqlStripped = preg_replace('/--[^\n]*/', '', $sql);
        $sqlStripped = preg_replace('/\/\*.*?\*\//s', '', $sqlStripped);
        $sqlStripped = trim($sqlStripped);

        // ── LAYER 2: Harus diawali SELECT ────────────────────────────────────
        if (!preg_match('/^\s*SELECT\b/i', $sqlStripped)) {
            Log::warning("[ToolCallExecutor] Rejected non-SELECT query: " . substr($sql, 0, 200));
            return $this->errorResponse('Hanya query SELECT yang diizinkan.');
        }

        // ── LAYER 3: Blokir kata kunci berbahaya (driver-aware) ─────────────────────────────
        $dbModel = \App\Models\DatabaseConnection::where('database', $databaseCode)->active()->first();
        $driver = $dbModel ? $dbModel->driver : 'pgsql';

        $forbidden = [
            'insert', 'update', 'delete', 'merge', 'upsert',
            'drop', 'truncate', 'alter', 'create', 'rename',
            'grant', 'revoke', 'execute', 'exec', 'call', 'do',
            'vacuum', 'pg_read_file', 'pg_write_file',
            'lo_import', 'lo_export', 'dblink', 'dblink_exec',
        ];

        // Add driver-specific forbidden keywords
        if ($driver === 'pgsql') {
            $forbidden[] = 'copy'; // PostgreSQL COPY command
        } elseif ($driver === 'sqlsrv') {
            $forbidden[] = 'bulk'; // SQL Server BULK operations
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            $forbidden[] = 'load'; // MySQL LOAD DATA
            $forbidden[] = 'into'; // SELECT ... INTO
        }

        $lowerSql = strtolower($sqlStripped);
        foreach ($forbidden as $kw) {
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/', $lowerSql)) {
                Log::warning("[ToolCallExecutor] Forbidden keyword '{$kw}'");
                return $this->errorResponse("Perintah '{$kw}' tidak diizinkan.");
            }
        }

        // ── LAYER 4: Blokir multiple statements ──────────────────────────────
        $trimmedSql = rtrim($sqlStripped, '; ');
        if (str_contains($trimmedSql, ';')) {
            return $this->errorResponse('Hanya satu query per panggilan.');
        }

        // ── LAYER 5: Validasi akses tabel ─────────────────────────────────────
        $allowedDbs = $this->getAllowedTables();
        
        if (!isset($allowedDbs[$databaseCode])) {
            return $this->errorResponse("Akses ditolak: Anda tidak memiliki akses ke database '{$databaseCode}'.");
        }

        // Kumpulkan semua tabel yang diizinkan untuk database ini dari semua schema
        $allowedTablesForDb = [];
        $allowedSchemasForDb = [];
        foreach ($allowedDbs[$databaseCode] as $sch => $tbls) {
            $allowedSchemasForDb[] = $sch;
            foreach ($tbls as $tbl) {
                // tbl bisa berupa string atau ['name'=>..., 'description'=>...]
                $allowedTablesForDb[] = is_array($tbl) ? ($tbl['name'] ?? '') : (string) $tbl;
            }
        }
        $allowedTablesForDb = array_filter(array_unique($allowedTablesForDb));

        // Ekstrak nama schema (opsional) dan tabel dari query
        // Format umum: from schema.table atau join schema.table
        if (preg_match_all('/(?:from|join)\s+(?:([a-zA-Z0-9_]+)\.)?([a-zA-Z0-9_]+)/i', $trimmedSql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $schemaUsed = !empty($match[1]) ? strtolower(trim($match[1])) : null;
                $tbl = strtolower(trim($match[2]));
                
                if (in_array($tbl, ['select', 'where', 'on', 'and', 'or', 'as', 'lateral', 'join', 'inner', 'left', 'right', 'outer'])) continue;
                
                if (!in_array($tbl, $allowedTablesForDb)) {
                    Log::warning("[ToolCallExecutor] Access denied to table '{$tbl}' in DB '{$databaseCode}'");
                    return $this->errorResponse("Akses ditolak: tabel '{$tbl}' tidak diizinkan atau tidak ditemukan.");
                }

                if ($schemaUsed && !in_array($schemaUsed, $allowedSchemasForDb)) {
                     Log::warning("[ToolCallExecutor] Access denied to schema '{$schemaUsed}' in DB '{$databaseCode}'");
                     return $this->errorResponse("Akses ditolak: schema '{$schemaUsed}' tidak diizinkan.");
                }
            }
        }

        // ── LAYER 6: Execute Query ─────────────────────────────────────────────
        $cleanSql = $trimmedSql;
        Log::info("[ToolCallExecutor] Executing SQL on DB {$databaseCode}: " . substr($cleanSql, 0, 300));

        // ── QUERY RESULT CACHING ──────────────────────────────────────────────
        $cacheKey = 'query_result_' . md5($cleanSql . '_' . $databaseCode . '_' . Auth::id());
        
        $cachedResult = Cache::get($cacheKey);
        if ($cachedResult !== null) {
            Log::info("[ToolCallExecutor] Using cached query result (saved DB call)");
            return $cachedResult;
        }

        // Siapkan koneksi dinamis
        $connName = "temp_conn_{$databaseCode}";
        try {
            if (!$dbModel) {
                $dbModel = \App\Models\DatabaseConnection::where('database', $databaseCode)->active()->first();
            }
            if (!$dbModel) {
                Log::error("[QueryService] Database config not found for database='{$databaseCode}'.");
                return $this->errorResponse("Database configuration for '{$databaseCode}' not found or inactive.");
            }

            DB::purge($connName);
            config(["database.connections.{$connName}" => $dbModel->getConnectionConfig()]);

            // Set driver-specific timeout and limits
            if ($driver === 'pgsql') {
                DB::connection($connName)->statement('SET statement_timeout = 0');
            } elseif ($driver === 'mysql' || $driver === 'mariadb') {
                DB::connection($connName)->statement('SET SESSION max_execution_time = 0');
            } elseif ($driver === 'sqlsrv') {
                // SQL Server timeout is handled in connection config
            }

            $rows = DB::connection($connName)->select($cleanSql);
        } catch (\Exception $e) {
            DB::purge($connName);
            Log::error("[ToolCallExecutor] Query failed on {$databaseCode}: " . $e->getMessage() . " | SQL: " . $cleanSql);

            $dbError = $e->getMessage();

            // Driver-specific error messages
            $msg = $this->formatDatabaseError($dbError, $driver, $cleanSql);

            return $this->safeJsonEncode(['error' => $msg]);
        }

        if (empty($rows)) {
            return $this->safeJsonEncode([
                'label'   => $label,
                'total'   => 0,
                'message' => 'Tidak ada data untuk query ini.',
                'columns' => [],
                'rows'    => [],
            ]);
        }

        $data = array_map(function($row) {
            $r = (array) $row;
            foreach ($r as $k => $v) {
                if (is_string($v) && preg_match('/^-?\d+\.\d+$/', $v)) {
                    if (preg_match('/\.0+$/', $v)) {
                        $r[$k] = (int) $v;
                    } else {
                        $r[$k] = (float) $v;
                    }
                }
            }
            return $r;
        }, $rows);

        $returned = count($data);

        // AI DECISION: Use only the columns explicitly identified by the AI as currency_columns
        $detectedCurrencyCols = array_unique($currencyColumns);

        $result = [
            'label'            => $label,
            'rows_returned'    => $returned,
            'columns'          => array_keys($data[0]),
            'currency_columns' => $detectedCurrencyCols,
            'rows'             => $data,
        ];

        // ── LAYER 7: Business Validation Note (Common Sense Check) ───────────
        $validationNotes = [];
        $monetaryCols = ['total_netto', 'total_dpp', 'harga', 'gpn', 'hpp', 'nominal'];

        foreach ($data as $row) {
            foreach ($row as $col => $val) {
                if (in_array(strtolower($col), $monetaryCols) && is_numeric($val) && (float)$val < 0) {
                    $validationNotes[] = "Warning: Found negative value in monetary column '{$col}'. Please verify if this is expected (e.g., returns or cancellations).";
                    break 2; // Only need one warning of this type
                }
            }
        }

        if (!empty($validationNotes)) {
            $result['business_validation_notes'] = $validationNotes;
        }

        // ANTI-LIMIT: Note removed for cleaner large data output
        $resultJson = $this->safeJsonEncode($result);
        
        // Cache the result for future identical queries
        Cache::put($cacheKey, $resultJson, $this->queryCacheTtl);
        
        return $resultJson;
    }


    /**
     * Format database error message based on driver type
     */
    private function formatDatabaseError(string $dbError, string $driver, string $sql): string
    {
        // Timeout detection per driver
        $timeoutPatterns = [
            'pgsql' => ['statement timeout', 'canceling statement due to statement timeout'],
            'mysql' => ['Statement timeout', 'max_execution_time'],
            'mariadb' => ['Statement timeout', 'max_execution_time'],
            'sqlsrv' => ['Timeout expired', 'execution timeout'],
            'sqlite' => ['database is locked'],
        ];

        $isTimeout = false;
        if (isset($timeoutPatterns[$driver])) {
            foreach ($timeoutPatterns[$driver] as $pattern) {
                if (stripos($dbError, $pattern) !== false) {
                    $isTimeout = true;
                    break;
                }
            }
        }

        if ($isTimeout) {
            return 'Query memakan waktu terlalu lama. Coba persempit data dengan menambahkan filter tahun, bulan, atau wilayah (misal: WHERE periode_tahun = EXTRACT(YEAR FROM NOW())).';
        }

        return "DATABASE_ERROR: {$dbError}. \n\nHINT UNTUK AI: Jika kesalahan disebabkan oleh nama kolom atau tabel yang tidak ditemukan, Anda WAJIB memanggil tool 'get_database_schema_info' atau 'describe_table' (dengan parameter database_code dan schema_name) untuk memverifikasi struktur tabel yang benar sebelum mencoba query lagi. Jangan menebak nama kolom atau schema.";
    }
}
