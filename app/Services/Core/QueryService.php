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

        // ── LAYER 3: Blokir kata kunci berbahaya (driver-aware) ──────────────
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

        // ── LAYER 4.5: AUTO-FIX — Deteksi & ganti filter periode_bulan/periode_tahun ──
        // Jika AI masih menggunakan kolom periode_bulan/periode_tahun yang menyebabkan
        // full scan atau hasil kosong, otomatis konversi ke filter DATE BETWEEN.
        $trimmedSql = $this->autoFixPeriodFilter($trimmedSql, $databaseCode);

        // ── LAYER 5: Validasi akses tabel ────────────────────────────────────
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
            $identPattern = '(?:"([^"]+)"|([a-zA-Z0-9_]+))';
            $pattern = '/(?:from|join)\s+' . $identPattern . '(?:\s*\.\s*' . $identPattern . ')?/i';

            if (preg_match_all($pattern, $trimmedSql, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
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

                    // Validasi tabel
                    if (!$hasWildcardTable && !in_array($tbl, $allowedTablesForDb)) {
                        Log::warning("[ToolCallExecutor] Access denied to table '{$tbl}' in DB '{$databaseCode}'");
                        return $this->errorResponse("Akses ditolak: tabel '{$tbl}' tidak diizinkan atau tidak ditemukan.");
                    }

                    // Validasi schema
                    if ($schemaUsed && !$hasWildcardSchema && !in_array($schemaUsed, $allowedSchemasForDb)) {
                        Log::warning("[ToolCallExecutor] Access denied to schema '{$schemaUsed}' in DB '{$databaseCode}'");
                        return $this->errorResponse("Akses ditolak: schema '{$schemaUsed}' tidak diizinkan.");
                    }
                }
            }
        }

        // ── LAYER 6: Execute Query ────────────────────────────────────────────
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

            // Set driver-specific timeout: 300 detik untuk query agregasi pada view besar.
            if ($driver === 'pgsql') {
                DB::connection($connName)->statement('SET statement_timeout = 300000');
            } elseif ($driver === 'mysql' || $driver === 'mariadb') {
                DB::connection($connName)->statement('SET SESSION max_execution_time = 300000');
            }

            $rows = DB::connection($connName)->select($cleanSql);
        } catch (\Exception $e) {
            DB::purge($connName);
            Log::error("[ToolCallExecutor] Query failed on {$databaseCode}: " . $e->getMessage() . " | SQL: " . $cleanSql);

            $dbError = $e->getMessage();
            $msg = $this->formatDatabaseError($dbError, $driver, $cleanSql);

            return $this->safeJsonEncode(['error' => $msg]);
        }

        // FIX: Jika rows kosong, berikan konteks yang lebih informatif kepada AI
        // agar AI TIDAK langsung menyimpulkan "data tidak ada" — mungkin filter WHERE
        // menggunakan nama kolom yang salah (misalnya: periode_bulan yang tidak ada di schema).
        if (empty($rows)) {
            return $this->safeJsonEncode([
                'label'   => $label,
                'total'   => 0,
                'rows'    => [],
                'columns' => [],
                'MANDATORY_AI_ACTION' => implode(' ', [
                    'Query berhasil dieksekusi tetapi mengembalikan 0 baris.',
                    'JANGAN langsung simpulkan data tidak ada.',
                    'Kemungkinan penyebab:',
                    '(1) Nama kolom filter salah (misal: menggunakan periode_bulan/periode_tahun padahal kolom sebenarnya adalah DATE/TIMESTAMP seperti tgl_faktur).',
                    '(2) Format nilai filter tidak cocok (misal: periode_bulan = \'3\' padahal tipe kolom integer atau tidak ada).',
                    '(3) Nama cabang tidak cocok dengan nilai aktual di database.',
                    'LANGKAH WAJIB: Panggil describe_table untuk verifikasi nama kolom tanggal yang benar,',
                    'lalu retry execute_query dengan filter BETWEEN pada kolom DATE/TIMESTAMP yang tepat.',
                ]),
            ]);
        }

        $data = array_map(function ($row) {
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
                if (in_array(strtolower($col), $monetaryCols) && is_numeric($val) && (float) $val < 0) {
                    $validationNotes[] = "Warning: Found negative value in monetary column '{$col}'. Please verify if this is expected (e.g., returns or cancellations).";
                    break 2;
                }
            }
        }

        if (!empty($validationNotes)) {
            $result['business_validation_notes'] = $validationNotes;
        }

        $resultJson = $this->safeJsonEncode($result);

        // Cache the result for future identical queries
        Cache::put($cacheKey, $resultJson, $this->queryCacheTtl);

        return $resultJson;
    }

    /**
     * Format database error message based on driver type.
     *
     * FIX: Pesan MANDATORY_AI_ACTION untuk timeout diperkuat agar Mistral
     * tidak mengabaikan instruksi dan langsung menyimpulkan "data tidak ada".
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

        // Detect PHP/Laravel connection-level timeout
        if (!$isTimeout && (
            stripos($dbError, 'could not obtain lock') !== false ||
            stripos($dbError, 'SQLSTATE[HY000]') !== false ||
            stripos($dbError, 'server has gone away') !== false ||
            (stripos($dbError, 'SQLSTATE') !== false && stripos($dbError, 'timeout') !== false)
        )) {
            $isTimeout = true;
        }

        if ($isTimeout) {
            // FIX: Format sebagai string tunggal + langkah bernomor eksplisit agar lebih mudah
            // diproses oleh Mistral yang kadang mengabaikan nested JSON array.
            return json_encode([
                'error'  => 'QUERY_TIMEOUT',
                'detail' => 'Query melebihi batas waktu 120 detik.',
                'MANDATORY_AI_ACTION' => implode(' ', [
                    '*** PERINGATAN KRITIS: TIMEOUT BUKAN BERARTI DATA TIDAK ADA. ***',
                    'Anda WAJIB melakukan langkah berikut — DILARANG menyerah atau memberikan jawaban kepada user:',
                    'LANGKAH 1: Panggil describe_table untuk tabel yang sama guna mendapatkan nama kolom DATE/TIMESTAMP yang benar.',
                    'LANGKAH 2: Buat ulang query execute_query dengan perbaikan:',
                    '(a) Ganti periode_bulan/periode_tahun dengan filter BETWEEN pada kolom tanggal yang benar: kolom_tgl BETWEEN \'2025-03-01\' AND \'2025-03-31\'.',
                    '(b) Pastikan filter cabang pakai ILIKE: nama_cabang ILIKE \'%hm%\' AND nama_cabang ILIKE \'%yamin%\'.',
                    '(c) Hanya SELECT kolom yang dibutuhkan, jangan SELECT *.',
                    'LANGKAH 3: Jalankan execute_query dengan query yang sudah dioptimasi.',
                    'Ulangi minimal 3 kali sebelum menyatakan ada kendala teknis kepada user.',
                ]),
            ]);
        }

        // Undefined column / does not exist
        if (
            stripos($dbError, 'Undefined column') !== false
            || (stripos($dbError, 'column') !== false && stripos($dbError, 'does not exist') !== false)
        ) {
            return json_encode([
                'error'  => 'UNDEFINED_COLUMN',
                'detail' => $dbError,
                'MANDATORY_AI_ACTION' => implode(' ', [
                    'Nama kolom yang digunakan SALAH.',
                    'WAJIB: Panggil describe_table dengan database_code dan schema_name yang eksak untuk melihat daftar kolom yang benar.',
                    'Kemudian retry execute_query menggunakan nama kolom yang benar dari hasil describe_table.',
                    'DILARANG menebak nama kolom.',
                ]),
            ]);
        }

        // Relation / table does not exist
        if (stripos($dbError, 'does not exist') !== false || stripos($dbError, 'relation') !== false) {
            return json_encode([
                'error'  => 'RELATION_NOT_FOUND',
                'detail' => $dbError,
                'MANDATORY_AI_ACTION' => implode(' ', [
                    'Nama tabel atau schema SALAH.',
                    'WAJIB: Panggil get_database_schema_info untuk mendapatkan nama eksak tabel dan schema.',
                    'Kemudian retry execute_query dengan nama yang benar.',
                ]),
            ]);
        }

        return json_encode([
            'error'  => 'DATABASE_ERROR',
            'detail' => $dbError,
            'MANDATORY_AI_ACTION' => implode(' ', [
                'Terjadi error database.',
                'Jika disebabkan nama kolom salah: panggil describe_table dulu, lalu retry execute_query.',
                'JANGAN menyerah dan JANGAN simpulkan data tidak ada sebelum berhasil atau minimal 3x retry.',
            ]),
        ]);
    }

    /**
     * AUTO-FIX: Deteksi filter periode_bulan/periode_tahun yang dipakai AI
     * dan konversi otomatis ke filter DATE BETWEEN yang lebih efisien.
     *
     * Contoh input AI:
     *   WHERE periode_bulan = '03' AND periode_tahun = '2025'
     *   WHERE periode_bulan = 3 AND periode_tahun = 2025
     *
     * Output setelah fix:
     *   WHERE tgl_faktur BETWEEN '2025-03-01' AND '2025-03-31'
     *
     * Jika kolom tanggal aktual tidak diketahui, coba deteksi dari schema.
     */
    private function autoFixPeriodFilter(string $sql, string $databaseCode): string
    {
        // Cek apakah ada pola periode_bulan + periode_tahun
        $hasBulan = preg_match('/\bperiode_bulan\s*=\s*['"](\d{1,2})['"]|\bperiode_bulan\s*=\s*(\d{1,2})\b/i', $sql, $mBulan);
        $hasTahun = preg_match('/\bperiode_tahun\s*=\s*['"](\d{4})['"]|\bperiode_tahun\s*=\s*(\d{4})\b/i', $sql, $mTahun);

        if (!$hasBulan && !$hasTahun) {
            return $sql; // Tidak ada pola yang perlu difix
        }

        $bulan = !empty($mBulan[1]) ? (int)$mBulan[1] : (int)($mBulan[2] ?? 0);
        $tahun = !empty($mTahun[1]) ? (int)$mTahun[1] : (int)($mTahun[2] ?? 0);

        // Jika bulan atau tahun tidak valid, jangan ubah query
        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > 2099) {
            return $sql;
        }

        // Hitung tanggal awal dan akhir bulan
        $dateStart = sprintf('%04d-%02d-01', $tahun, $bulan);
        $lastDay   = (int) date('t', mktime(0, 0, 0, $bulan, 1, $tahun));
        $dateEnd   = sprintf('%04d-%02d-%02d', $tahun, $bulan, $lastDay);

        // Coba temukan nama kolom tanggal aktual dari schema
        $dateColumn = $this->detectDateColumn($databaseCode, $sql);

        Log::info("[QueryService] AutoFix: periode_bulan={$bulan} periode_tahun={$tahun} "
            . "-> BETWEEN '{$dateStart}' AND '{$dateEnd}' "
            . "using column: {$dateColumn}");

        // Hapus kondisi periode_bulan dan periode_tahun dari WHERE
        // Pattern: AND/OR periode_bulan = ... dan AND/OR periode_tahun = ...
        $patterns = [
            '/\s+AND\s+periode_bulan\s*=\s*[\'"](\d{1,2})[\'"]/i',
            '/\s+AND\s+periode_bulan\s*=\s*\d{1,2}\b/i',
            '/\s+OR\s+periode_bulan\s*=\s*[\'"](\d{1,2})[\'"]/i',
            '/\s+OR\s+periode_bulan\s*=\s*\d{1,2}\b/i',
            '/\bperiode_bulan\s*=\s*[\'"](\d{1,2})[\'"]/i',
            '/\bperiode_bulan\s*=\s*\d{1,2}\b/i',
            '/\s+AND\s+periode_tahun\s*=\s*[\'"](\d{4})[\'"]/i',
            '/\s+AND\s+periode_tahun\s*=\s*\d{4}\b/i',
            '/\s+OR\s+periode_tahun\s*=\s*[\'"](\d{4})[\'"]/i',
            '/\s+OR\s+periode_tahun\s*=\s*\d{4}\b/i',
            '/\bperiode_tahun\s*=\s*[\'"](\d{4})[\'"]/i',
            '/\bperiode_tahun\s*=\s*\d{4}\b/i',
        ];

        $cleanSql = $sql;
        foreach ($patterns as $pattern) {
            $cleanSql = preg_replace($pattern, '', $cleanSql);
        }

        // Tambahkan filter BETWEEN yang benar
        $betweenFilter = "{$dateColumn} BETWEEN '{$dateStart}' AND '{$dateEnd}'";

        // Cek apakah masih ada WHERE clause
        if (preg_match('/\bWHERE\b/i', $cleanSql)) {
            // Ada WHERE — append dengan AND
            $cleanSql = preg_replace('/\bWHERE\b/i', "WHERE {$betweenFilter} AND", $cleanSql, 1);
        } else {
            // Tidak ada WHERE — tambah sebelum GROUP BY / ORDER BY / LIMIT, atau di akhir
            if (preg_match('/\b(GROUP BY|ORDER BY|LIMIT|HAVING)\b/i', $cleanSql, $m, PREG_OFFSET_CAPTURE)) {
                $pos     = $m[0][1];
                $keyword = $m[0][0];
                $cleanSql = substr($cleanSql, 0, $pos) . "WHERE {$betweenFilter} " . substr($cleanSql, $pos);
            } else {
                $cleanSql = $cleanSql . " WHERE {$betweenFilter}";
            }
        }

        // Bersihkan whitespace berlebih
        $cleanSql = preg_replace('/\s+/', ' ', $cleanSql);
        $cleanSql = preg_replace('/WHERE\s+AND\s+/i', 'WHERE ', $cleanSql);
        $cleanSql = preg_replace('/WHERE\s+OR\s+/i', 'WHERE ', $cleanSql);
        $cleanSql = trim($cleanSql);

        Log::info("[QueryService] AutoFix SQL result: " . substr($cleanSql, 0, 400));

        return $cleanSql;
    }

    /**
     * Deteksi nama kolom tanggal aktual dari SQL dan schema database.
     * Urutan prioritas: kolom yang sudah ada di query > lookup schema > fallback default.
     */
    private function detectDateColumn(string $databaseCode, string $sql): string
    {
        // Kandidat nama kolom tanggal yang umum dipakai
        $commonDateColumns = [
            'tgl_faktur', 'tgl_transaksi', 'tgl_jual', 'tgl_penjualan',
            'tanggal', 'tgl', 'tanggal_faktur', 'tanggal_transaksi',
            'created_at', 'transaction_date', 'invoice_date', 'sale_date',
            'tgl_order', 'order_date', 'tgl_nota', 'tgl_dokumen',
        ];

        // Coba ekstrak nama tabel dari SQL untuk lookup schema
        if (preg_match('/FROM\s+["\`]?([\w]+)["\`]?\s*\.\s*["\`]?([\w]+)["\`]?/i', $sql, $m)) {
            $schemaName = $m[1];
            $tableName  = $m[2];

            try {
                $connName = "temp_conn_{$databaseCode}_detect";
                $dbModel  = \App\Models\DatabaseConnection::where('database', $databaseCode)->active()->first();
                if ($dbModel) {
                    DB::purge($connName);
                    config(["database.connections.{$connName}" => $dbModel->getConnectionConfig()]);

                    $adapter = $dbModel->getAdapter();
                    $cols    = DB::connection($connName)->select(
                        $adapter->describeTableQuery(),
                        [$tableName, $schemaName]
                    );
                    DB::purge($connName);

                    // Cari kolom bertipe DATE atau TIMESTAMP
                    foreach ($cols as $col) {
                        $colName = strtolower($col->column_name ?? '');
                        $colType = strtolower($col->data_type ?? '');
                        $isDate  = str_contains($colType, 'date') || str_contains($colType, 'timestamp');
                        if ($isDate && in_array($colName, $commonDateColumns)) {
                            return $col->column_name;
                        }
                    }

                    // Fallback: kolom DATE/TIMESTAMP pertama yang ditemukan
                    foreach ($cols as $col) {
                        $colType = strtolower($col->data_type ?? '');
                        if (str_contains($colType, 'date') || str_contains($colType, 'timestamp')) {
                            return $col->column_name;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning("[QueryService] detectDateColumn failed: " . $e->getMessage());
            }
        }

        // Fallback default — nama kolom paling umum di sistem MBI
        return 'tgl_faktur';
    }
}
