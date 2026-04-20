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
        $hasWildcardTable  = false; // true jika ada entri '*' di tabel
        $hasWildcardSchema = false; // true jika ada key schema '*'

        foreach ($allowedDbs[$databaseCode] as $sch => $tbls) {
            if ($sch === '*') {
                $hasWildcardSchema = true;
            } else {
                $allowedSchemasForDb[] = strtolower($sch);
            }
            foreach ($tbls as $tbl) {
                // tbl bisa berupa string atau ['name'=>..., 'description'=>...]
                $tblName = is_array($tbl) ? ($tbl['name'] ?? '') : (string) $tbl;
                if ($tblName === '*') {
                    $hasWildcardTable = true;
                } else {
                    $allowedTablesForDb[] = strtolower($tblName);
                }
            }
        }
        $allowedTablesForDb = array_filter(array_unique($allowedTablesForDb));

        // Jika ada wildcard schema DAN wildcard table, izinkan semua — skip RBAC tabel
        $skipTableRbac = $hasWildcardSchema && $hasWildcardTable;

        if (!$skipTableRbac) {
            // Ekstrak nama schema dan tabel dari query.
            // Support format: schema.table, "schema"."table", schema."table", "schema".table
            // Regex menangkap identifier biasa ATAU quoted ("...") sebagai satu token.
            $identPattern = '(?:"([^"]+)"|([a-zA-Z0-9_]+))';
            $pattern = '/(?:from|join)\s+' . $identPattern . '(?:\s*\.\s*' . $identPattern . ')?/i';

            if (preg_match_all($pattern, $trimmedSql, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    // match[1] = quoted schema atau quoted lone-table
                    // match[2] = unquoted schema atau unquoted lone-table
                    // match[3] = quoted table (jika ada dot)
                    // match[4] = unquoted table (jika ada dot)
                    $hasDot   = !empty($match[3]) || !empty($match[4]);
                    $firstId  = strtolower(!empty($match[1]) ? $match[1] : $match[2]);
                    $secondId = $hasDot ? strtolower(!empty($match[3]) ? $match[3] : $match[4]) : null;

                    if ($hasDot) {
                        $schemaUsed = $firstId;
                        $tbl        = $secondId;
                    } else {
                        $schemaUsed = null;
                        $tbl        = $firstId;
                    }

                    // Skip SQL keywords yang bisa muncul setelah FROM/JOIN
                    $sqlKeywords = ['select', 'where', 'on', 'and', 'or', 'as', 'lateral',
                                    'join', 'inner', 'left', 'right', 'outer', 'cross', 'full'];
                    if (in_array($tbl, $sqlKeywords)) continue;

                    // Validasi tabel — izinkan jika ada wildcard '*' di list tabel schema ini
                    if (!$hasWildcardTable && !in_array($tbl, $allowedTablesForDb)) {
                        Log::warning("[ToolCallExecutor] Access denied to table '{$tbl}' in DB '{$databaseCode}'");
                        return $this->errorResponse("Akses ditolak: tabel '{$tbl}' tidak diizinkan atau tidak ditemukan.");
                    }

                    // Validasi schema — izinkan jika ada wildcard schema
                    if ($schemaUsed && !$hasWildcardSchema && !in_array($schemaUsed, $allowedSchemasForDb)) {
                        Log::warning("[ToolCallExecutor] Access denied to schema '{$schemaUsed}' in DB '{$databaseCode}'");
                        return $this->errorResponse("Akses ditolak: schema '{$schemaUsed}' tidak diizinkan.");
                    }
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

            // Set driver-specific timeout:
            // Gunakan 120 detik (120000ms) agar query berat pada view tetap bisa selesai.
            // Ini cukup untuk agregasi data besar, namun masih ada batas agar tidak hang selamanya.
            if ($driver === 'pgsql') {
                DB::connection($connName)->statement('SET statement_timeout = 120000'); // 120 detik
            } elseif ($driver === 'mysql' || $driver === 'mariadb') {
                DB::connection($connName)->statement('SET SESSION max_execution_time = 120000'); // 120 detik
            }
            // sqlsrv: handled via connection config options

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
            'pgsql'   => ['statement timeout', 'canceling statement due to statement timeout'],
            'mysql'   => ['Statement timeout', 'max_execution_time'],
            'mariadb' => ['Statement timeout', 'max_execution_time'],
            'sqlsrv'  => ['Timeout expired', 'execution timeout'],
            'sqlite'  => ['database is locked'],
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

        // Detect PHP/Laravel connection-level timeout (query took too long before DB responded)
        if (!$isTimeout && (
            stripos($dbError, 'could not obtain lock') !== false ||
            stripos($dbError, 'SQLSTATE[HY000]') !== false ||
            stripos($dbError, 'server has gone away') !== false ||
            // Laravel HTTP client timeout via PDO
            (stripos($dbError, 'SQLSTATE') !== false && stripos($dbError, 'timeout') !== false)
        )) {
            $isTimeout = true;
        }

        if ($isTimeout) {
            return json_encode([
                'error'  => 'QUERY_TIMEOUT',
                'detail' => 'Query melebihi batas waktu eksekusi (120 detik).',
                'MANDATORY_AI_ACTION' => [
                    'step1' => 'JANGAN menyimpulkan data tidak ada. Timeout berarti query terlalu berat, BUKAN data kosong.',
                    'step2' => 'Optimasi query dengan salah satu strategi berikut:',
                    'strategies' => [
                        'A: Tambahkan filter tahun/bulan yang lebih spesifik menggunakan kolom bertipe date/timestamp yang ada di schema.',
                        'B: Ganti fungsi EXTRACT() dengan range langsung: tgl_fak_jl BETWEEN \'2025-03-01\' AND \'2025-03-31\'',
                        'C: Jika filter nama_cabang lambat, coba filter dengan kode_cabang jika tersedia (lebih cepat karena biasanya diindex).',
                        'D: Kurangi kolom SELECT — hanya ambil kolom yang benar-benar dibutuhkan.',
                    ],
                    'step3' => 'Jalankan ulang execute_query dengan query yang sudah dioptimasi.',
                ]
            ]);
        }

        // Undefined column / relation does not exist
        if (stripos($dbError, 'Undefined column') !== false || stripos($dbError, 'column') !== false && stripos($dbError, 'does not exist') !== false) {
            return json_encode([
                'error'  => 'UNDEFINED_COLUMN',
                'detail' => $dbError,
                'MANDATORY_AI_ACTION' => 'Nama kolom salah. WAJIB panggil describe_table dengan database_code dan schema_name yang eksak untuk melihat daftar kolom yang benar, kemudian retry execute_query.',
            ]);
        }

        // Relation / table does not exist
        if (stripos($dbError, 'does not exist') !== false || stripos($dbError, 'relation') !== false) {
            return json_encode([
                'error'  => 'RELATION_NOT_FOUND',
                'detail' => $dbError,
                'MANDATORY_AI_ACTION' => 'Nama tabel atau schema salah. WAJIB panggil get_database_schema_info untuk mendapatkan nama eksak, kemudian retry execute_query.',
            ]);
        }

        return json_encode([
            'error'  => 'DATABASE_ERROR',
            'detail' => $dbError,
            'MANDATORY_AI_ACTION' => 'Jika error disebabkan nama kolom atau tabel yang salah, WAJIB panggil describe_table untuk verifikasi struktur, kemudian retry. Jangan menyerah dan jangan bilang data tidak ada sebelum berhasil atau mencapai 3x retry.',
        ]);
    }
}
