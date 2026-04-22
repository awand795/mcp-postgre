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
        // FIX: hanya jalankan autofix jika query benar-benar mengandung periode_bulan/periode_tahun
        // agar tidak mengacak-acak query yang sudah benar.
        $lowerCheck = strtolower($trimmedSql);
        if (str_contains($lowerCheck, 'periode_bulan') || str_contains($lowerCheck, 'periode_tahun')) {
            $trimmedSql = $this->autoFixPeriodFilter($trimmedSql, $databaseCode);
        }

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
            $needNewConn = true;
            try {
                // Coba ping koneksi yang sudah ada
                DB::connection($connName)->getPdo();
                $needNewConn = false;
                Log::info("[QueryService] Reusing persistent connection for {$databaseCode}");
            } catch (\Throwable $pingEx) {
                // Koneksi belum ada atau sudah mati → buat baru
                Log::info("[QueryService] Creating new persistent connection for {$databaseCode}");
                $needNewConn = true;
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
                if ($driver === 'pgsql') {
                    // statement_timeout sudah di-set via DSN options di atas,
                    // ini sebagai double confirmation
                    DB::connection($connName)->statement('SET statement_timeout = 0');
                } elseif ($driver === 'mysql' || $driver === 'mariadb') {
                    // Untuk MySQL, INIT_COMMAND sudah handle, tapi set lagi untuk safety
                    DB::connection($connName)->statement('SET SESSION max_execution_time = 0');
                }

                Log::info("[QueryService] Persistent connection established for {$databaseCode} (keep-alive enabled)");
            }

            $rows = DB::connection($connName)->select($cleanSql);

            // JANGAN purge koneksi setelah query sukses — biarkan keep-alive
            // Koneksi akan otomatis ditutup saat PHP process selesai (end of request)

        } catch (\Exception $e) {
            // Hanya purge saat error, agar koneksi rusak tidak di-reuse
            DB::purge($connName);
            Log::error("[QueryService] Query failed on {$databaseCode}: " . $e->getMessage() . " | SQL: " . $cleanSql);

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

        // ── CURRENCY COLUMNS: AI hint + server-side fallback ─────────────────
        // Prioritas: pakai hint dari AI jika dikirim dan tidak kosong.
        // Jika AI tidak mengirim currency_columns (atau kirim []),
        // lakukan auto-detect dari nama alias kolom di hasil query.
        // Keyword yang dianggap kolom uang: total, netto, harga, hpp, revenue, dll.
        $detectedCurrencyCols = array_unique($currencyColumns);

        if (empty($detectedCurrencyCols) && !empty($data)) {
            $currencyKeywords = [
                'total', 'netto', 'harga', 'hpp', 'revenue', 'amount',
                'nominal', 'omset', 'dpp', 'profit', 'laba', 'margin',
                'bruto', 'diskon', 'disc', 'cost', 'sales', 'value',
                'penjualan', 'pendapatan', 'biaya', 'piutang', 'hutang',
            ];
            $excludeKeywords = ['qty', 'count', 'jumlah_item', 'persentase', 'persen', 'rate', '%'];
            $columns = array_keys($data[0]);
            foreach ($columns as $col) {
                $colLower = strtolower($col);
                // Skip kolom yang jelas bukan uang
                $isExcluded = false;
                foreach ($excludeKeywords as $exc) {
                    if (str_contains($colLower, $exc)) {
                        $isExcluded = true;
                        break;
                    }
                }
                if ($isExcluded) continue;
                // Cek apakah nama kolom mengandung keyword uang
                foreach ($currencyKeywords as $kw) {
                    if (str_contains($colLower, $kw)) {
                        // Verifikasi nilai di kolom ini numerik (bukan string/teks)
                        $sampleVal = $data[0][$col] ?? null;
                        if (is_numeric($sampleVal)) {
                            $detectedCurrencyCols[] = $col;
                        }
                        break;
                    }
                }
            }
            $detectedCurrencyCols = array_unique($detectedCurrencyCols);
            if (!empty($detectedCurrencyCols)) {
                Log::info('[QueryService] currency_columns auto-detected (AI did not provide): ' . implode(', ', $detectedCurrencyCols));
            }
        }

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
        // Detect alias and values
        $alias = null;
        $bulan = 0;
        $tahun = 0;

        // Check for periode_bulan and its optional alias
        if (preg_match('/(?:([\w"\'`]+)\.)?\bperiode_bulan\s*=\s*(?:[\x27"](\d{1,2})[\x27"]|(\d{1,2}))/i', $sql, $mBulan)) {
            $alias = !empty($mBulan[1]) ? $mBulan[1] : null;
            $bulan = (int)(!empty($mBulan[2]) ? $mBulan[2] : $mBulan[3]);
        }

        // Check for periode_tahun and its optional alias
        if (preg_match('/(?:([\w"\'`]+)\.)?\bperiode_tahun\s*=\s*(?:[\x27"](\d{4})[\x27"]|(\d{4}))/i', $sql, $mTahun)) {
            // Priority for alias remains with whatever is found, usually they should be the same
            $alias = $alias ?: (!empty($mTahun[1]) ? $mTahun[1] : null);
            $tahun = (int)(!empty($mTahun[2]) ? $mTahun[2] : $mTahun[3]);
        }

        if ($bulan === 0 && $tahun === 0) {
            return $sql; // Nothing to fix
        }

        // Hitung tanggal awal dan akhir
        // Jika hanya tahun (tanpa bulan) → range 1 tahun penuh
        $useTahun = ($tahun >= 2000 && $tahun <= 2099) ? $tahun : (int)date('Y');

        if ($bulan === 0) {
            // Query tahunan: tanpa filter bulan → ambil seluruh tahun
            $dateStart = sprintf('%04d-01-01', $useTahun);
            $dateEnd   = sprintf('%04d-12-31', $useTahun);
        } else {
            $useBulan  = ($bulan >= 1 && $bulan <= 12) ? $bulan : (int)date('m');
            $dateStart = sprintf('%04d-%02d-01', $useTahun, $useBulan);
            $lastDay   = (int) date('t', mktime(0, 0, 0, $useBulan, 1, $useTahun));
            $dateEnd   = sprintf('%04d-%02d-%02d', $useTahun, $useBulan, $lastDay);
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

        // Hapus kondisi periode_bulan dan periode_tahun dari SQL secara aman
        // Pattern mencakup opsional alias dan whitespace di sekelilingnya
        $patterns = [
            '/\s+(?:AND|OR)\s+[\w"\'`]*\.*periode_bulan\s*=\s*[\'"]?\d{1,2}[\'"]?/i',
            '/\b[\w"\'`]*\.*periode_bulan\s*=\s*[\'"]?\d{1,2}[\'"]?(\s+(?:AND|OR))?/i',
            '/\s+(?:AND|OR)\s+[\w"\'`]*\.*periode_tahun\s*=\s*[\'"]?\d{4}[\'"]?/i',
            '/\b[\w"\'`]*\.*periode_tahun\s*=\s*[\'"]?\d{4}[\'"]?(\s+(?:AND|OR))?/i',
        ];

        $cleanSql = $sql;
        foreach ($patterns as $pattern) {
            $cleanSql = preg_replace($pattern, ' ', $cleanSql);
        }

        // Tambahkan filter BETWEEN yang benar (sertakan alias jika ditemukan)
        $qualifiedCol  = $alias ? "{$alias}.{$dateColumn}" : $dateColumn;
        $betweenFilter = "{$qualifiedCol} BETWEEN '{$dateStart}' AND '{$dateEnd}'";

        // Cek apakah masih ada WHERE clause
        if (preg_match('/\bWHERE\b/i', $cleanSql)) {
            if (preg_match('/\b(GROUP\s+BY|ORDER\s+BY|LIMIT|HAVING)\b/i', $cleanSql, $gm, PREG_OFFSET_CAPTURE)) {
                $insertPos = $gm[0][1];
                $before = rtrim(substr($cleanSql, 0, $insertPos));
                $after  = ltrim(substr($cleanSql, $insertPos));
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
        $tableName  = $m[2];

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
                [$tableName, $schemaName]
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
}