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
     * Probe query (SELECT DISTINCT) di-cache lebih lama karena nilai enum
     * seperti nama propinsi/kabupaten sangat jarang berubah.
     * Agregasi (SUM, GROUP BY) di-cache 5 menit — cukup untuk session normal.
     */
    private int $queryCacheTtl = 300;  // default: 5 menit
    private int $probeQueryCacheTtl = 3600; // probe SELECT DISTINCT: 1 jam

    /**
     * Query execution timeout: 0 = UNLIMITED.
     *
     * Cara set UNLIMITED di PHP/PDO level untuk PostgreSQL:
     *   1. options DSN  : tidak set "-c statement_timeout" sama sekali (kosong / tidak di-pass)
     *   2. PDO::ATTR_TIMEOUT tidak di-set (default PDO = unlimited)
     *   3. SET statement_timeout = 0  di awal sesi PostgreSQL
     *      → 0 berarti tidak ada batas waktu di sisi server
     *
     * Cara set UNLIMITED di PHP/PDO level untuk MySQL:
     *   1. Tidak set PDO::ATTR_TIMEOUT
     *   2. SET SESSION max_execution_time = 0  → unlimited di sisi server
     *
     * Catatan penting: PDO::ATTR_TIMEOUT di PHP hanya mengontrol
     * berapa lama PDO menunggu KONEKSI terbuka (connect timeout),
     * BUKAN durasi eksekusi query. Setelah koneksi terbuka, PHP
     * akan menunggu hasil query tanpa batas kecuali ada timeout
     * di sisi database server (statement_timeout / max_execution_time).
     * Jadi untuk unlimited: cukup set statement_timeout = 0 di server.
     */
    private int $queryTimeoutSeconds = 0; // 0 = unlimited

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

        // Only Super Admin sees all active databases.
        // Regular admin (is_admin = true) must follow role-based permissions.
        if ($user->is_super_admin) {
            $connections = \App\Models\DatabaseConnection::where('is_active', true)->get();
            $result = [];
            foreach ($connections as $conn) {
                $tables = $conn->getTables();
                $dbIdentifier = $conn->database;
                foreach ($tables as $t) {
                    $sch = $t['schema_name'];
                    $tbl = $t['table_name'];
                    $desc = $t['description'] ?? '';
                    $result[$dbIdentifier][$sch][] = [
                        'name' => $tbl,
                        'description' => $desc,
                    ];
                }
            }
            return $result;
        }

        $roleId = $user->role;
        $permissions = RolePermission::with('databaseConnection')->where('role_id', $roleId)->get();
        $result = [];
        foreach ($permissions as $p) {
            $conn = $p->databaseConnection;
            if (!$conn || !$conn->is_active)
                continue;

            $dbCode = $conn->database;

            $result[$dbCode][$p->schema_name][] = [
                'name' => $p->table_name,
                'description' => ''
            ];
        }
        return $result;
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
            'insert',
            'update',
            'delete',
            'merge',
            'upsert',
            'drop',
            'truncate',
            'alter',
            'create',
            'rename',
            'grant',
            'revoke',
            'execute',
            'exec',
            'call',
            'do',
            'vacuum',
            'pg_read_file',
            'pg_write_file',
            'lo_import',
            'lo_export',
            'dblink',
            'dblink_exec',
        ];

        // Add driver-specific forbidden keywords
        if ($driver === 'pgsql') {
            $forbidden[] = 'copy'; // PostgreSQL COPY command
        } elseif ($driver === 'sqlsrv') {
            $forbidden[] = 'bulk'; // SQL Server BULK operations
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            $forbidden[] = 'load'; // MySQL LOAD DATA
            $forbidden[] = 'into'; // SELECT ... INTO
        } elseif ($driver === 'clickhouse') {
            // ClickHouse: blokir perintah DDL berbahaya
            $forbidden[] = 'optimize'; // OPTIMIZE TABLE (memakan resource)
            $forbidden[] = 'attach';   // ATTACH TABLE
            $forbidden[] = 'detach';   // DETACH TABLE
            $forbidden[] = 'system';   // SYSTEM commands
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
        // KRITIS: Hanya aktifkan autoFix jika periode_bulan/periode_tahun ada di dalam
        // WHERE clause — BUKAN di CASE WHEN, SELECT, atau subquery.
        // Ini mencegah regex penghapus merusak CASE WHEN periode_tahun = '2025' THEN ...
        $hasPeriodeInWhere = false;
        if (stripos($trimmedSql, 'periode_bulan') !== false || stripos($trimmedSql, 'periode_tahun') !== false) {
            // Ekstrak isi WHERE clause saja (hentikan di GROUP BY / ORDER BY / HAVING / LIMIT)
            if (preg_match('/\bWHERE\b(.*?)(?:\b(?:GROUP\s+BY|ORDER\s+BY|HAVING|LIMIT)\b|$)/is', $trimmedSql, $whereMatch)) {
                $whereClause = strtolower($whereMatch[1]);
                $hasPeriodeInWhere = str_contains($whereClause, 'periode_bulan')
                    || str_contains($whereClause, 'periode_tahun');
            }
        }
        if ($hasPeriodeInWhere) {
            $trimmedSql = $this->autoFixPeriodFilter($trimmedSql, $databaseCode);
        }

        // ── LAYER 5: Validasi akses tabel (Strict RBAC) ──────────────────────
        $user = Auth::user();
        if ($user && $user->is_super_admin) {
            // Super Admin bypasses table access validation & security policy
        } else {
            $allowedDbs = $this->getAllowedTables();

            if (!isset($allowedDbs[$databaseCode])) {
                return $this->errorResponse("Akses ditolak: Anda tidak memiliki akses ke database '{$databaseCode}'.");
            }

            // BUG FIX: Exclude FROM yang ada di dalam fungsi SQL seperti:
            //   EXTRACT(MONTH FROM tgl_fak_jl)  → 'tgl_fak_jl' bukan nama tabel!
            // Strategi: hapus dulu semua konteks "FUNGSI(... FROM ...)" dari SQL
            // sebelum di-scan RBAC, sehingga FROM di dalam fungsi tidak ikut tertangkap.
            $sqlFunctionsUsingFrom = ['EXTRACT', 'TRIM', 'OVERLAY', 'POSITION', 'SUBSTRING'];
            $sqlForRbacScan = $trimmedSql;
            foreach ($sqlFunctionsUsingFrom as $fn) {
                $sqlForRbacScan = preg_replace(
                    '/\b' . preg_quote($fn, '/') . '\s*\([^)]*\bFROM\b[^)]*\)/i',
                    $fn . '(__RBAC_SKIP__)',
                    $sqlForRbacScan
                );
            }

            $identPattern = '(?:"([^"]+)"|([a-zA-Z0-9_]+))';
            // Cocokkan FROM/JOIN diikuti identifier, tapi TIDAK diikuti '(' (itu subquery/fungsi)
            $pattern = '/\b(?:FROM|JOIN)\s+' . $identPattern . '(?:\s*\.\s*' . $identPattern . ')?(?!\s*\()/i';

            if (preg_match_all($pattern, $sqlForRbacScan, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $hasDot = !empty($match[3]) || !empty($match[4]);
                    $firstId = strtolower(!empty($match[1]) ? $match[1] : $match[2]);
                    $secondId = $hasDot ? strtolower(!empty($match[3]) ? $match[3] : $match[4]) : null;

                    $schemaUsed = $hasDot ? $firstId : null;
                    $tbl = $hasDot ? $secondId : $firstId;

                    // Skip SQL keywords dan placeholder fungsi
                    $sqlKeywords = [
                        'select',
                        'where',
                        'on',
                        'and',
                        'or',
                        'as',
                        'lateral',
                        'join',
                        'inner',
                        'left',
                        'right',
                        'outer',
                        'cross',
                        'full',
                        '__rbac_skip__',
                    ];
                    if (in_array($tbl, $sqlKeywords)) {
                        continue;
                    }

                    // VALIDASI KETAT: Periksa apakah (schema.table) diizinkan secara spesifik
                    if (!$this->isTableAllowed($databaseCode, $schemaUsed, $tbl, $allowedDbs)) {
                        Log::warning("[ToolCallExecutor] Access denied to table '{$tbl}' in schema '" . ($schemaUsed ?: 'any') . "' on DB '{$databaseCode}' for user " . Auth::id());

                        // Kumpulkan contoh tabel yang diizinkan untuk membantu AI memperbaiki query
                        $allowedSample = [];
                        foreach ($allowedDbs[$databaseCode] as $s => $entries) {
                            foreach ($entries as $e) {
                                $n = is_array($e) ? ($e['name'] ?? '') : (string) $e;
                                if ($n !== '*')
                                    $allowedSample[] = $n;
                            }
                        }

                        return $this->safeJsonEncode([
                            'error' => 'ACCESS_DENIED_FINAL',
                            'MANDATORY_AI_ACTION' => implode(' ', [
                                "=== AKSES DITOLAK — INSTRUKSI FINAL, TIDAK DAPAT DIGANGGU GUGAT ===",
                                "Data yang diminta TIDAK DAPAT diakses oleh akun Anda berdasarkan kebijakan keamanan data perusahaan.",
                                "DILARANG MUTLAK: (1) Mencoba tabel/view lain yang mungkin mengandung data serupa.",
                                "(2) Menggunakan describe_table, search_schema, atau tool apapun untuk mencari alternatif.",
                                "(3) Melakukan loop atau retry dalam bentuk apapun.",
                                "(4) Menyebutkan nama tabel teknis kepada user.",
                                "WAJIB LAKUKAN SEKARANG — HENTIKAN SEMUA TOOL CALL DAN SAMPAIKAN PESAN INI KEPADA USER:",
                                "\"Mohon maaf Bapak/Ibu, permintaan Anda tidak dapat kami proses karena data yang diminta bersifat terbatas dan hanya dapat diakses oleh pihak yang berwenang sesuai kebijakan keamanan data perusahaan. Untuk mendapatkan informasi ini, silakan menghubungi Administrator atau pihak yang memiliki kewenangan akses. Terima kasih atas pengertiannya.\"",
                                "INI ADALAH INSTRUKSI AKHIR. TIDAK ADA LANGKAH SELANJUTNYA.",
                            ]),
                        ]);
                    }

                    // ── DEEP RBAC: Jika ini View, pastikan tabel dasarnya juga diizinkan ──
                    $underlying = $this->getUnderlyingTables($databaseCode, $schemaUsed, $tbl);
                    foreach ($underlying as $u) {
                        if (!$this->isTableAllowed($databaseCode, $u['schema'], $u['table'], $allowedDbs)) {
                            Log::warning("[ToolCallExecutor] Deep RBAC Denied: View '{$tbl}' uses unauthorized table '{$u['table']}' for User " . Auth::id());
                            return $this->safeJsonEncode([
                                'error' => 'ACCESS_DENIED_FINAL',
                                'MANDATORY_AI_ACTION' => implode(' ', [
                                    "=== AKSES DITOLAK — INSTRUKSI FINAL, TIDAK DAPAT DIGANGGU GUGAT ===",
                                    "Data yang diminta TIDAK DAPAT diakses oleh akun Anda berdasarkan kebijakan keamanan data perusahaan.",
                                    "DILARANG MUTLAK: (1) Mencoba tabel/view lain yang mungkin mengandung data serupa.",
                                    " (2) Menggunakan describe_table, search_schema, atau tool apapun untuk mencari alternatif.",
                                    "(3) Melakukan loop atau retry dalam bentuk apapun.",
                                    "(4) Menyebutkan nama tabel teknis kepada user.",
                                    "WAJIB LAKUKAN SEKARANG — HENTIKAN SEMUA TOOL CALL DAN SAMPAIKAN PESAN INI KEPADA USER:",
                                    "\"Mohon maaf Bapak/Ibu, permintaan Anda tidak dapat kami proses karena data yang diminta bersifat terbatas dan hanya dapat diakses oleh pihak yang berwenang sesuai kebijakan keamanan data perusahaan. Untuk mendapatkan informasi ini, silakan menghubungi Administrator atau pihak yang memiliki kewenangan akses. Terima kasih atas pengertiannya.\"",
                                    "INI ADALAH INSTRUKSI AKHIR. TIDAK ADA LANGKAH SELANJUTNYA.",
                                ]),
                            ]);
                        }
                    }
                }
            }

            // ── LAYER 5.5: Global Security Policy (Columns & Keywords) ───────────
            $policyViolation = $this->validateSecurityPolicy($databaseCode, $allowedDbs, $sqlForRbacScan);
            if ($policyViolation) {
                return $policyViolation;
            }
        }

        // ── LAYER 5.8: Inject User Table Filters (Row Level Security) ────────
        // Syarat RLS:
        // 1. User terautentikasi
        // 2. Bukan Super Admin
        // 3. Jika Admin, hanya kena RLS pada database yang BUKAN ditambahkan sendiri (kecuali eksplisit dibatasi)
        $user = Auth::user();
        if ($user && !$user->is_super_admin && $dbModel) {
            // Admin bebas dari RLS pada database yang dia buat sendiri
            if ($user->is_admin && $dbModel->added_by === $user->id) {
                // Skip RLS
            } else {
                $tableFilters = \App\Models\UserTableFilter::where('user_id', $user->id)
                    ->where('database_connection_id', $dbModel->id)
                    ->get();
            
                if ($tableFilters->count() > 0) {
                    foreach ($tableFilters as $tf) {
                        $tbl = $tf->table_name;
                        $rawCondition = $tf->filter_condition;
                        if (empty($rawCondition)) continue;

                        // Parse JSON rules and build WHERE clause
                        $rules = json_decode($rawCondition, true);
                        if (!is_array($rules) || empty($rules)) continue;

                        $cond = $this->buildWhereFromRules($rules);
                        if (empty($cond)) continue;

                        // Strategi: Bungkus tabel dengan subquery yang sudah difilter
                        
                        // 1. Handle FROM
                        $fromPattern = '/\bFROM\s+(?:("[^"]+"|[a-zA-Z0-9_]+)\s*\.\s*)?(' . preg_quote($tbl, '/') . '|"' . preg_quote($tbl, '/') . '")\b/i';
                        $trimmedSql = preg_replace_callback($fromPattern, function($m) use ($tbl, $cond) {
                            $schema = !empty($m[1]) ? $m[1] . "." : "";
                            return "FROM (SELECT * FROM {$schema}{$tbl} WHERE {$cond}) AS {$tbl}";
                        }, $trimmedSql);

                        // 2. Handle JOIN
                        $joinPattern = '/\bJOIN\s+(?:("[^"]+"|[a-zA-Z0-9_]+)\s*\.\s*)?(' . preg_quote($tbl, '/') . '|"' . preg_quote($tbl, '/') . '")\b/i';
                        $trimmedSql = preg_replace_callback($joinPattern, function($m) use ($tbl, $cond) {
                            $schema = !empty($m[1]) ? $m[1] . "." : "";
                            return "JOIN (SELECT * FROM {$schema}{$tbl} WHERE {$cond}) AS {$tbl}";
                        }, $trimmedSql);

                        Log::info("[QueryService] RLS filter injected for user {$user->id} on table {$tbl}: {$cond}");
                    }
                }
            }
        }

        // ── LAYER 6: Execute Query ────────────────────────────────────────────
        $cleanSql = $trimmedSql;
        Log::info("[ToolCallExecutor] Executing SQL on DB {$databaseCode}: " . substr($cleanSql, 0, 300));

        // ── QUERY RESULT CACHING ──────────────────────────────────────────────
        // Probe query = SELECT DISTINCT tanpa GROUP BY, atau query yang hanya
        // mengambil nilai unik/enumerasi (bukan agregasi bisnis).
        // Definisi diperluas: query dengan IN (...) tanpa SUM/COUNT/AVG/GROUP BY
        // juga dianggap probe karena biasanya hanya untuk validasi ketersediaan data.
        $hasDistinct = stripos($cleanSql, 'SELECT DISTINCT') !== false;
        $hasGroupBy = stripos($cleanSql, 'GROUP BY') !== false;
        $hasAggregate = (bool) preg_match('/\b(SUM|COUNT|AVG|MIN|MAX)\s*\(/i', $cleanSql);

        $isProbeForKey = ($hasDistinct && !$hasGroupBy) || (!$hasGroupBy && !$hasAggregate);
        $cacheKey = $isProbeForKey
            ? 'query_probe_' . md5($cleanSql . '_' . $databaseCode)
            : 'query_result_' . md5($cleanSql . '_' . $databaseCode . '_' . Auth::id());

        $cachedResult = Cache::get($cacheKey);
        if ($cachedResult !== null) {
            Log::info("[QueryService] Cache HIT for query (saved ~57s DB call): " . substr($cleanSql, 0, 100));
            return $cachedResult;
        }

        // ── PERSISTENT CONNECTION (KEEP-ALIVE) ────────────────────────────
        // Strategi: gunakan koneksi yang sama (keep-alive) selama satu request
        // HTTP berlangsung. Koneksi hanya dibuat sekali per database per request,
        // lalu di-reuse oleh semua tool call berikutnya dalam loop yang sama.
        //
        // CARA KERJA KEEP-ALIVE di PHP/PDO:
        //   - PHP tidak memiliki connection pooling built-in seperti PgBouncer.
        //   - Tapi selama object PDO masih ada di memory (dalam satu PHP process),
        //     koneksi tetap terbuka dan bisa di-reuse.
        //   - Kuncinya: JANGAN panggil DB::purge() di awal setiap execute_query.
        //     Purge hanya dilakukan saat error (agar koneksi rusak tidak di-reuse).
        //   - DB::connection($connName) akan REUSE koneksi yang sudah ada jika
        //     config sudah di-set dan koneksi belum di-purge.
        //
        // KENAPA INI MEMBUAT TIMEOUT HILANG:
        //   - Sebelumnya: setiap query membuat koneksi baru → PHP socket baru →
        //     bisa kena default socket timeout OS atau framework.
        //   - Sekarang: koneksi dibuat sekali, query berikutnya langsung pakai
        //     channel yang sudah terbuka → tidak ada overhead re-connect.
        //   - `SET statement_timeout = 0` juga hanya perlu diset sekali per sesi.
        //
        // UNLIMITED TIMEOUT = kombinasi:
        //   1. `set_time_limit(0)` di controller (PHP script tidak die)
        //   2. `statement_timeout = 0` di PostgreSQL (server tidak cancel query)
        //   3. Koneksi keep-alive (tidak ada re-connect yang bisa trigger timeout)
        //   4. `Http::timeout(600)` di AI API call (sudah ada di controller)
        $connName = "persistent_conn_{$databaseCode}";
        try {
            if (!$dbModel) {
                $dbModel = \App\Models\DatabaseConnection::where('database', $databaseCode)->active()->first();
            }
            if (!$dbModel) {
                Log::error("[QueryService] Database config not found for database='{$databaseCode}'.");
                return $this->errorResponse("Database configuration for '{$databaseCode}' not found or inactive.");
            }

            // Cek apakah koneksi sudah ada dan masih hidup (keep-alive check)
            // NOTE: ClickHouse HTTP driver tidak support getPdo(), skip ping
            $needNewConn = true;
            if ($driver === 'clickhouse') {
                // ClickHouse: HTTP stateless — selalu buat koneksi baru
                $needNewConn = true;
            } else {
                try {
                    DB::connection($connName)->getPdo();
                    $needNewConn = false;
                    Log::info("[QueryService] Reusing persistent connection for {$databaseCode}");
                } catch (\Throwable $pingEx) {
                    Log::info("[QueryService] Creating new persistent connection for {$databaseCode}");
                    $needNewConn = true;
                }
            }

            if ($needNewConn) {
                // Bersihkan koneksi lama yang mungkin rusak, lalu buat baru
                DB::purge($connName);
                $connConfig = $dbModel->getConnectionConfig();

                // Inject opsi keep-alive di level PDO/DSN
                if ($driver === 'pgsql') {
                    // connect_timeout: gagal cepat jika server tidak bisa dicapai (bukan query timeout)
                    $connConfig['connect_timeout'] = 10;

                    // PENTING: Laravel PostgreSQL driver meneruskan 'options' ke PDO sebagai
                    // array PDO::ATTR_* — BUKAN string DSN. Untuk menginject parameter
                    // libpq seperti keepalives dan statement_timeout, kita gunakan
                    // 'sslmode' dan custom key 'pgsql_options' yang di-handle adapter,
                    // ATAU inject langsung via SET setelah koneksi terbuka (lebih andal).
                    //
                    // SOLUSI: Pastikan 'options' tetap array (PDO options), bukan string.
                    // keepalives dan statement_timeout di-set via SQL setelah koneksi dibuat.
                    // Hapus key 'options' jika berisi string dari config lama agar tidak crash.
                    if (isset($connConfig['options']) && is_string($connConfig['options'])) {
                        unset($connConfig['options']);
                    }
                    if (!isset($connConfig['options'])) {
                        $connConfig['options'] = [];
                    }

                } elseif ($driver === 'mysql' || $driver === 'mariadb') {
                    // MySQL: INIT_COMMAND diset via SQL setelah koneksi terbuka
                    if (isset($connConfig['options']) && is_string($connConfig['options'])) {
                        unset($connConfig['options']);
                    }
                    if (!isset($connConfig['options'])) {
                        $connConfig['options'] = [];
                    }
                }

                config(["database.connections.{$connName}" => $connConfig]);

                // Buka koneksi dan set server-side unlimited timeout
                // ClickHouse tidak support statement_timeout — skip
                if ($driver === 'pgsql') {
                    DB::connection($connName)->statement('SET statement_timeout = 0');
                } elseif ($driver === 'mysql' || $driver === 'mariadb') {
                    DB::connection($connName)->statement('SET SESSION max_execution_time = 0');
                }

                Log::info("[QueryService] Persistent connection established for {$databaseCode} (keep-alive enabled)");
            }

            $startTime = microtime(true);
            $rows = DB::connection($connName)->select($cleanSql);
            $executionTime = round(microtime(true) - $startTime, 2);

            // JANGAN purge koneksi setelah query sukses — biarkan keep-alive
            // Koneksi akan otomatis ditutup saat PHP process selesai (end of request)

        } catch (\Exception $e) {
            // Hanya purge saat error, agar koneksi rusak tidak di-reuse
            DB::purge($connName);
            Log::error("[QueryService] Query failed on {$databaseCode}: " . $e->getMessage() . " | SQL: " . $cleanSql);

            $dbError = $e->getMessage();
            $errorJson = $this->formatDatabaseError($dbError, $driver, $cleanSql);

            // formatDatabaseError sudah mengembalikan JSON string, 
            // jadi kita langsung return tanpa di-encode lagi agar tidak double-encoded.
            return $errorJson;
        }

        // FIX: Jika rows kosong, berikan konteks yang lebih informatif kepada AI
        // agar AI TIDAK langsung menyimpulkan "data tidak ada" — mungkin filter WHERE
        // menggunakan nama kolom yang salah (misalnya: periode_bulan yang tidak ada di schema).
        if (empty($rows)) {
            return $this->safeJsonEncode([
                'label' => $label,
                'total' => 0,
                'rows' => [],
                'columns' => [],
                'MANDATORY_AI_ACTION' => implode(' ', [
                    'INTERNAL NOTE: Query successful but 0 rows. / Query berhasil tetapi 0 baris.',
                    'DO NOT mention "0 rows" or "empty query" to the user. / DILARANG menyebut "0 baris" atau "query kosong" kepada user.',
                    'Use business language: "Data not available" or "No transaction records found". / Gunakan bahasa bisnis: "Data belum tersedia" atau "Belum ada catatan transaksi".',
                    'Match your response language to the user\'s language. / Samakan bahasa jawaban Anda dengan bahasa user.',
                    'Troubleshooting: (1) Check if date columns (e.g. tgl_faktur) are used correctly. (2) Check if branch names are exact.',
                    'ACTION: Call describe_table to verify date columns, then retry execute_query with BETWEEN on the correct column. DO NOT ask for permission, just do it.',
                ]),
            ]);
        }

        // Apply Column-level RBAC: Redact forbidden columns from results
        $forbidden = [];
        $user = Auth::user();
        if (!$user || !$user->is_super_admin) {
            $allowedDbs = $allowedDbs ?? $this->getAllowedTables();
            $forbidden = $this->getForbiddenColumns($databaseCode, $allowedDbs);
        }

        $data = array_map(function ($row) use ($forbidden) {
            $r = (array) $row;

            if (!empty($forbidden)) {
                foreach ($forbidden as $col) {
                    if (array_key_exists($col, $r)) {
                        unset($r[$col]);
                    }
                }
            }

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

        // ── CURRENCY COLUMNS: AI hint + server-side fallback ─────────────────
        $detectedCurrencyCols = $this->sanitizeCurrencyColumns($currencyColumns, array_keys($data[0] ?? []), $data);

        $result = [
            'label' => $label,
            'rows_returned' => $returned,
            'execution_time_seconds' => $executionTime ?? 0,
            'columns' => array_keys($data[0]),
            'currency_columns' => $detectedCurrencyCols,
            'rows' => $data,
        ];

        // ── PERFORMANCE WARNING: Inform AI if query is slow ──────────────────
        if (($executionTime ?? 0) > 30) {
            $result['PERFORMANCE_NOTE'] = "INTERNAL ANALYST NOTE: Query took {$executionTime}s (SLOW). / Query memakan waktu {$executionTime} detik (LAMBAT). DO NOT mention this to the user. / DILARANG menyebutkan hal ini kepada user. Use more specific filters in the next turn if possible. / Gunakan filter yang lebih spesifik jika memungkinkan.";
        }

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

        // Cache dengan TTL berbeda: probe query di-cache lebih lama.
        // Gunakan definisi yang sama dengan isProbeForKey di atas.
        $isProbeQuery = ($hasDistinct && !$hasGroupBy) || (!$hasGroupBy && !$hasAggregate);
        $ttl = $isProbeQuery ? $this->probeQueryCacheTtl : $this->queryCacheTtl;

        Cache::put($cacheKey, $resultJson, $ttl);
        Log::info("[QueryService] Query result cached (TTL={$ttl}s, isProbe=" . ($isProbeQuery ? 'true' : 'false') . ")");

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
            'pgsql' => ['statement timeout', 'canceling statement due to statement timeout'],
            'mysql' => ['Statement timeout', 'max_execution_time'],
            'mariadb' => ['Statement timeout', 'max_execution_time'],
            'sqlsrv' => ['Timeout expired', 'execution timeout'],
            'sqlite' => ['database is locked'],
            'clickhouse' => ['timeout', 'e.connect', 'received EOF', 'connection refused'],
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
        if (
            !$isTimeout && (
                stripos($dbError, 'could not obtain lock') !== false ||
                stripos($dbError, 'SQLSTATE[HY000]') !== false ||
                stripos($dbError, 'server has gone away') !== false ||
                (stripos($dbError, 'SQLSTATE') !== false && stripos($dbError, 'timeout') !== false)
            )
        ) {
            $isTimeout = true;
        }

        if ($isTimeout) {
            // FIX: Format sebagai string tunggal + langkah bernomor eksplisit agar lebih mudah
            // diproses oleh Mistral yang kadang mengabaikan nested JSON array.
            return json_encode([
                'error' => 'QUERY_TIMEOUT',
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
                'error' => 'UNDEFINED_COLUMN',
                'detail' => $dbError,
                'MANDATORY_AI_ACTION' => implode(' ', [
                    'INTERNAL NOTE: Nama kolom yang digunakan SALAH.',
                    'DILARANG menyebutkan nama kolom (misal: tgl_fak_jl) kepada Bapak/Ibu user.',
                    'Gunakan bahasa bisnis: "Mohon maaf Bapak/Ibu, terjadi kendala teknis saat mencoba mengambil data. Kami sedang melakukan penyesuaian pada sistem untuk menampilkan informasi ini secara akurat."',
                    'WAJIB: Panggil describe_table dengan database_code dan schema_name yang eksak untuk melihat daftar kolom yang benar.',
                    'Kemudian retry execute_query menggunakan nama kolom yang benar dari hasil describe_table.',
                    'DILARANG menebak nama kolom.',
                ]),
            ]);
        }

        // Relation / table does not exist
        if (stripos($dbError, 'does not exist') !== false || stripos($dbError, 'relation') !== false) {
            return json_encode([
                'error' => 'RELATION_NOT_FOUND',
                'detail' => $dbError,
                'MANDATORY_AI_ACTION' => implode(' ', [
                    'Nama tabel atau schema SALAH.',
                    'WAJIB: Panggil get_database_schema_info untuk mendapatkan nama eksak tabel dan schema.',
                    'Kemudian retry execute_query dengan nama yang benar.',
                ]),
            ]);
        }

        return json_encode([
            'error' => 'DATABASE_ERROR',
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
     *
     * KRITIS: Fungsi ini hanya dipanggil jika periode_* terbukti ada di WHERE
     * clause (dicek di executeQuery sebelum memanggil fungsi ini).
     * Penghapusan juga dilakukan HANYA pada WHERE clause, bukan seluruh SQL,
     * agar CASE WHEN periode_tahun = '2025' THEN ... tidak ikut terhapus.
     */
    private function autoFixPeriodFilter(string $sql, string $databaseCode): string
    {
        // Detect alias and values
        $alias = null;
        $bulan = 0;
        $tahun = 0;
        $tahunList = []; // untuk IN (...) multi-tahun

        // ── Deteksi periode_bulan ─────────────────────────────────────────────
        // Support: equality  → periode_bulan = '3'  atau  periode_bulan = 3
        if (preg_match('/(?:([\w"\'`]+)\.)?\bperiode_bulan\s*=\s*(?:[\x27"](\d{1,2})[\x27"]|(\d{1,2}))/i', $sql, $mBulan)) {
            $alias = !empty($mBulan[1]) ? $mBulan[1] : null;
            $bulan = (int) (!empty($mBulan[2]) ? $mBulan[2] : $mBulan[3]);
        }
        // Support: IN clause → periode_bulan IN ('3', '4')  atau  IN (3,4)
        if ($bulan === 0 && preg_match('/(?:([\w"\'`]+)\.)?\bperiode_bulan\s+IN\s*\(([^)]+)\)/i', $sql, $mBulanIn)) {
            $alias = !empty($mBulanIn[1]) ? $mBulanIn[1] : null;
            $rawVals = array_map('trim', explode(',', $mBulanIn[2]));
            $bulanArr = array_filter(array_map(fn($v) => (int) trim($v, " '\"\t"), $rawVals), fn($v) => $v >= 1 && $v <= 12);
            if (!empty($bulanArr)) {
                sort($bulanArr);
                // Ambil rentang bulan terkecil–terbesar (untuk jadi BETWEEN akhir bulan)
                $bulanMin = min($bulanArr);
                $bulanMax = max($bulanArr);
                // Simpan ke $bulan supaya logika di bawah tidak berubah banyak;
                // akan di-override ke range jika $bulanArr > 1 elemen.
                $bulan = count($bulanArr) === 1 ? $bulanMin : -1; // -1 = multi-bulan
                $GLOBALS['_autofix_bulan_arr'] = $bulanArr; // pass ke scope bawah via globals (sementara)
            }
        }

        // ── Deteksi periode_tahun ─────────────────────────────────────────────
        // Support: equality → periode_tahun = '2025'
        if (preg_match('/(?:([\w"\'`]+)\.)?\bperiode_tahun\s*=\s*(?:[\x27"](\d{4})[\x27"]|(\d{4}))/i', $sql, $mTahun)) {
            $alias = $alias ?: (!empty($mTahun[1]) ? $mTahun[1] : null);
            $tahun = (int) (!empty($mTahun[2]) ? $mTahun[2] : $mTahun[3]);
            $tahunList = [$tahun];
        }
        // Support: IN clause → periode_tahun IN ('2025', '2026')
        if (empty($tahunList) && preg_match('/(?:([\w"\'`]+)\.)?\bperiode_tahun\s+IN\s*\(([^)]+)\)/i', $sql, $mTahunIn)) {
            $alias = $alias ?: (!empty($mTahunIn[1]) ? $mTahunIn[1] : null);
            $rawVals = array_map('trim', explode(',', $mTahunIn[2]));
            $tahunList = array_filter(array_map(fn($v) => (int) trim($v, " '\"\t"), $rawVals), fn($v) => $v >= 2000 && $v <= 2099);
            sort($tahunList);
            $tahun = !empty($tahunList) ? $tahunList[0] : 0;
        }

        if ($bulan === 0 && empty($tahunList)) {
            return $sql; // Tidak ada yang perlu di-fix
        }

        // ── Hitung dateStart dan dateEnd ──────────────────────────────────────
        // Jika multi-tahun (IN), ambil rentang dari tahun terkecil ke terbesar
        $useTahunMin = !empty($tahunList) ? min($tahunList) : ((int) date('Y'));
        $useTahunMax = !empty($tahunList) ? max($tahunList) : $useTahunMin;

        if ($bulan === 0) {
            // Hanya filter tahun — ambil rentang full (bisa multi-tahun)
            $dateStart = sprintf('%04d-01-01', $useTahunMin);
            $dateEnd = sprintf('%04d-12-31', $useTahunMax);
        } elseif ($bulan === -1) {
            // Multi-bulan (dari IN clause) — ambil dari bulan terkecil ke terbesar
            $bulanArr = $GLOBALS['_autofix_bulan_arr'] ?? [];
            unset($GLOBALS['_autofix_bulan_arr']);
            $bulanMin = !empty($bulanArr) ? min($bulanArr) : 1;
            $bulanMax = !empty($bulanArr) ? max($bulanArr) : 12;
            $dateStart = sprintf('%04d-%02d-01', $useTahunMin, $bulanMin);
            $lastDay = (int) date('t', mktime(0, 0, 0, $bulanMax, 1, $useTahunMax));
            $dateEnd = sprintf('%04d-%02d-%02d', $useTahunMax, $bulanMax, $lastDay);
        } else {
            // Satu bulan spesifik
            $useBulan = ($bulan >= 1 && $bulan <= 12) ? $bulan : (int) date('m');
            $dateStart = sprintf('%04d-%02d-01', $useTahunMin, $useBulan);
            $lastDay = (int) date('t', mktime(0, 0, 0, $useBulan, 1, $useTahunMax));
            $dateEnd = sprintf('%04d-%02d-%02d', $useTahunMax, $useBulan, $lastDay);
        }

        // Coba temukan nama kolom tanggal aktual dari schema secara mandiri
        $dateColumn = $this->detectDateColumn($databaseCode, $sql);

        // Jika detectDateColumn tidak berhasil (null), JANGAN tebak/hardcode.
        // Kembalikan SQL asli tanpa modifikasi + log agar AI tetap berjalan.
        // AI akan mendapat 0 rows atau error kolom tidak ada, lalu MANDATORY_AI_ACTION
        // di execute_query akan memaksanya panggil describe_table secara mandiri.
        if ($dateColumn === null) {
            Log::warning('[QueryService] autoFixPeriodFilter: detectDateColumn returned null — cannot auto-fix, returning original SQL. AI must self-discover date column via describe_table.');
            // Kembalikan SQL asli agar proses tidak berhenti;
            // AI akan belajar dari hasil query (error/empty) dan panggil describe_table sendiri.
            return $sql;
        }

        Log::info("[QueryService] AutoFix: Bulan={$bulan} Tahun={$tahun} (Alias: " . ($alias ?: 'none') . ") "
            . "-> BETWEEN '{$dateStart}' AND '{$dateEnd}' "
            . "using column: {$dateColumn}");

        // ── HAPUS periode_bulan/periode_tahun HANYA dari WHERE clause ────────
        // KRITIS: Jangan hapus dari CASE WHEN, FILTER (WHERE ...), atau SELECT.
        // Strategi: pisahkan SQL → beforeWhere + whereBody + afterWhere,
        // terapkan regex hanya pada whereBody, lalu gabungkan kembali.
        $inValPattern = '\s*\([^)]+\)'; // cocokkan nilai IN: ('2025','2026') atau (2025,2026)
        $eqBulan = '=\s*[\'"]?\d{1,2}[\'"]?';
        $eqTahun = '=\s*[\'"]?\d{4}[\'"]?';
        $inBulan = "IN{$inValPattern}";
        $inTahun = "IN{$inValPattern}";
        $removePatterns = [
            // Hapus AND/OR sebelum kondisi (kondisi di tengah/akhir WHERE)
            "/\s+\b(?:AND|OR)\b\s+[\w\"'`]*\.*periode_bulan\s*(?:{$eqBulan}|{$inBulan})/i",
            // Hapus kondisi di awal WHERE (diikuti AND/OR)
            "/\b[\w\"'`]*\.*periode_bulan\s*(?:{$eqBulan}|{$inBulan})(\s+\b(?:AND|OR)\b)?/i",
            "/\s+\b(?:AND|OR)\b\s+[\w\"'`]*\.*periode_tahun\s*(?:{$eqTahun}|{$inTahun})/i",
            "/\b[\w\"'`]*\.*periode_tahun\s*(?:{$eqTahun}|{$inTahun})(\s+\b(?:AND|OR)\b)?/i",
        ];

        $cleanSql = $sql;

        // Pisahkan SQL menjadi 3 bagian: sebelum WHERE, isi WHERE, sesudah WHERE.
        // Regex: tangkap (sebelum+WHERE) + (isi WHERE) + (GROUP BY/ORDER BY/HAVING/LIMIT/akhir)
        if (preg_match('/^(.*?\bWHERE\b)(.*?)((?:\b(?:GROUP\s+BY|ORDER\s+BY|HAVING|LIMIT)\b.*)?)$/is', $sql, $sqlParts)) {
            $beforeWhere = $sqlParts[1]; // "SELECT ... FROM ... WHERE"
            $whereBody = $sqlParts[2]; // isi kondisi WHERE saja — INI yang dibersihkan
            $afterWhere = $sqlParts[3]; // "GROUP BY ..." — JANGAN disentuh

            // Terapkan regex hanya pada $whereBody
            foreach ($removePatterns as $pattern) {
                $whereBody = preg_replace($pattern, ' ', $whereBody);
            }

            $cleanSql = $beforeWhere . $whereBody . $afterWhere;
        }
        // Jika tidak ada WHERE clause: tidak ada yang perlu dihapus,
        // BETWEEN akan ditambahkan sebagai WHERE baru di bawah.

        // Tambahkan filter BETWEEN yang benar (sertakan alias jika ditemukan)
        $qualifiedCol = $alias ? "{$alias}.{$dateColumn}" : $dateColumn;
        $betweenFilter = "{$qualifiedCol} BETWEEN '{$dateStart}' AND '{$dateEnd}'";

        // Cek apakah masih ada WHERE clause setelah pembersihan
        if (preg_match('/\bWHERE\b/i', $cleanSql)) {
            if (preg_match('/\b(GROUP\s+BY|ORDER\s+BY|LIMIT|HAVING)\b/i', $cleanSql, $gm, PREG_OFFSET_CAPTURE)) {
                $insertPos = $gm[0][1];
                $before = rtrim(substr($cleanSql, 0, $insertPos));
                $after = ltrim(substr($cleanSql, $insertPos));
                // Pastikan tidak ada trailing AND/OR di $before
                $before = preg_replace('/\s+(?:AND|OR)\s*$/i', '', $before);
                $cleanSql = $before . " AND {$betweenFilter} " . $after;
            } else {
                $before = rtrim($cleanSql);
                $before = preg_replace('/\s+(?:AND|OR)\s*$/i', '', $before);
                $cleanSql = $before . " AND {$betweenFilter}";
            }
        } else {
            if (preg_match('/\b(GROUP BY|ORDER BY|LIMIT|HAVING)\b/i', $cleanSql, $m, PREG_OFFSET_CAPTURE)) {
                $pos = $m[0][1];
                $cleanSql = substr($cleanSql, 0, $pos) . " WHERE {$betweenFilter} " . substr($cleanSql, $pos);
            } else {
                $cleanSql = rtrim($cleanSql) . " WHERE {$betweenFilter}";
            }
        }

        // Pembersihan akhir (hapus whitespace ganda, perbaiki WHERE AND)
        $cleanSql = preg_replace('/\s+/', ' ', $cleanSql);
        $cleanSql = preg_replace('/WHERE\s+(?:AND|OR)\s+/i', 'WHERE ', $cleanSql);
        $cleanSql = preg_replace('/\(\s+(?:AND|OR)\s+/i', '(', $cleanSql);
        $cleanSql = preg_replace('/\s+(?:AND|OR)\s+\)/i', ' )', $cleanSql);
        $cleanSql = trim($cleanSql);

        // FIX: Dukung periode_bulan dan periode_tahun di SELECT dan GROUP BY
        // dengan menggantinya ke fungsi ekstraksi tanggal yang sesuai driver.
        $dbModel = \App\Models\DatabaseConnection::where('database', $databaseCode)->active()->first();
        $driver = $dbModel ? $dbModel->driver : 'pgsql';
        $qualifiedCol = $alias ? "{$alias}.{$dateColumn}" : $dateColumn;

        $extractMonth = ($driver === 'pgsql') ? "EXTRACT(MONTH FROM {$qualifiedCol})" : "MONTH({$qualifiedCol})";
        $extractYear = ($driver === 'pgsql') ? "EXTRACT(YEAR FROM {$qualifiedCol})" : "YEAR({$qualifiedCol})";

        // FIX: AI sering membandingkan periode_bulan dengan string bulan (contoh: periode_bulan = 'Januari')
        // Ini menyebabkan error "invalid input syntax for type numeric" karena EXTRACT(MONTH) menghasilkan angka.
        $monthMap = [
            'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
            'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
            'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12
        ];
        foreach ($monthMap as $monthStr => $monthNum) {
            $cleanSql = preg_replace("/(periode_bulan|EXTRACT\s*\(\s*MONTH\s+FROM\s+[^\)]+\))\s*=\s*'{$monthStr}'/i", "$1 = {$monthNum}", $cleanSql);
        }

        // Ganti periode_bulan dan periode_tahun di seluruh query
        // Gunakan regex agar tidak mengganti bagian dari kata lain
        $cleanSql = preg_replace('/\bperiode_bulan\b/i', $extractMonth, $cleanSql);
        $cleanSql = preg_replace('/\bperiode_tahun\b/i', $extractYear, $cleanSql);

        Log::info("[QueryService] AutoFix SQL result: " . substr($cleanSql, 0, 400));

        return $cleanSql;
    }


    /**
     * Deteksi nama kolom tanggal dari schema database secara MANDIRI.
     *
     * FIX #7: Gunakan koneksi persistent yang sudah ada jika tersedia,
     * hindari membuat koneksi baru berulang kali saat autoFix retry.
     * Jika deteksi gagal total, kembalikan NULL dan biarkan AI
     * yang memutuskan lewat MANDATORY_AI_ACTION di autoFixPeriodFilter.
     */
    private function detectDateColumn(string $databaseCode, string $sql): ?string
    {
        // Ekstrak schema.tabel dari SQL
        if (!preg_match('/FROM\s+["\`]?([\w]+)["\`]?\s*\.\s*["\`]?([\w]+)["\`]?/i', $sql, $m)) {
            Log::warning('[QueryService] detectDateColumn: cannot extract table name from SQL');
            return null;
        }

        $schemaName = $m[1];
        $tableName = $m[2];

        try {
            // FIX #7: Coba gunakan koneksi persistent yang sudah ada terlebih dahulu
            // untuk menghindari overhead membuat koneksi baru setiap kali detectDateColumn dipanggil.
            $persistentConn = "persistent_conn_{$databaseCode}";
            $useConn = null;

            try {
                DB::connection($persistentConn)->getPdo();
                $useConn = $persistentConn;
                Log::info("[QueryService] detectDateColumn: reusing persistent connection for {$databaseCode}");
            } catch (\Throwable $e) {
                // Koneksi persistent belum ada atau mati, buat koneksi sementara
                $useConn = null;
            }

            $dbModel = \App\Models\DatabaseConnection::where('database', $databaseCode)->active()->first();
            if (!$dbModel) {
                Log::warning("[QueryService] detectDateColumn: DB model not found for '{$databaseCode}'");
                return null;
            }

            $adapter = $dbModel->getAdapter();

            if ($useConn === null) {
                // Buat koneksi sementara khusus untuk deteksi ini
                $tempConn = "temp_conn_{$databaseCode}_detect";
                DB::purge($tempConn);
                config(["database.connections.{$tempConn}" => $dbModel->getConnectionConfig()]);
                $useConn = $tempConn;
            }

            $cols = DB::connection($useConn)->select(
                $adapter->describeTableQuery(),
                // MySQL describeTableQuery butuh 2 params: [tableName, schemaName].
                // PostgreSQL/SQLServer: schemaName = schema (sch_mbi, public).
                // MySQL/MariaDB: schemaName = database name (karena usesSchema()=false).
                $adapter->usesSchema()
                ? [$tableName, $schemaName]
                : [$tableName, $dbModel->database]
            );

            // Bersihkan koneksi sementara jika dibuat
            if ($useConn !== $persistentConn) {
                DB::purge($useConn);
            }

            // Cari kolom bertipe DATE atau TIMESTAMP — kembalikan yang PERTAMA
            foreach ($cols as $col) {
                $colType = strtolower($col->data_type ?? '');
                if (str_contains($colType, 'date') || str_contains($colType, 'timestamp')) {
                    Log::info("[QueryService] detectDateColumn: found '{$col->column_name}' ({$col->data_type}) in {$schemaName}.{$tableName}");
                    return $col->column_name;
                }
            }

            Log::warning("[QueryService] detectDateColumn: no DATE/TIMESTAMP column found in {$schemaName}.{$tableName}");
            return null;

        } catch (\Exception $e) {
            Log::warning('[QueryService] detectDateColumn failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Build WHERE clause from structured JSON rules.
     * Used by RLS injection and AdminController preview.
     */
    private function buildWhereFromRules(array $rules): string
    {
        if (empty($rules)) return '';

        $parts = [];
        $logics = [];
        foreach ($rules as $rule) {
            $col = preg_replace('/[^a-zA-Z0-9_]/', '', $rule['column'] ?? '');
            $op = strtoupper($rule['operator'] ?? '=');
            $val = $rule['value'] ?? '';
            $logic = strtoupper($rule['logic'] ?? 'AND');
            if (!in_array($logic, ['AND', 'OR'])) $logic = 'AND';

            if (empty($col) || empty($val)) continue;

            $escaped = str_replace("'", "''", $val);

            if ($op === 'IN' || $op === 'NOT IN') {
                $vals = array_map(function($v) {
                    $v = trim($v, " \t\n\r\"");
                    return "'" . str_replace("'", "''", trim($v)) . "'";
                }, explode(',', $val));
                $parts[] = "{$col} {$op} (" . implode(', ', $vals) . ")";
            } elseif ($op === 'LIKE' || $op === 'ILIKE') {
                $parts[] = "{$col} {$op} '{$escaped}'";
            } else {
                $parts[] = "{$col} {$op} '{$escaped}'";
            }

            if (count($parts) > 1) {
                $logics[] = $logic;
            }
        }

        if (empty($parts)) return '';

        $result = $parts[0];
        for ($i = 1; $i < count($parts); $i++) {
            $logic = $logics[$i - 1] ?? 'AND';
            $result .= " {$logic} {$parts[$i]}";
        }

        return $result;
    }

    /**
     * Keep currency metadata limited to actual monetary columns.
     * Maps the AI-provided currency columns to actual result column names (case-insensitively).
     */
    private function sanitizeCurrencyColumns(array $currencyColumns, array $actualColumns = [], array $rows = []): array
    {
        if (empty($currencyColumns)) {
            return [];
        }

        $actualByLower = [];
        foreach ($actualColumns as $col) {
            $actualByLower[strtolower((string) $col)] = (string) $col;
        }

        $clean = [];
        foreach ($currencyColumns as $col) {
            $col = trim((string) $col);
            if ($col === '') {
                continue;
            }

            $actualCol = $actualByLower[strtolower($col)] ?? null;
            if ($actualCol !== null) {
                $clean[] = $actualCol;
            } else {
                Log::info("[QueryService] currency_columns sanitized: removed non-existent column '{$col}'");
            }
        }

        return array_values(array_unique($clean));
    }
}
