<?php

namespace App\Services\Core;

use App\Models\RolePermission;
use App\Services\BaseService;
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
     * Cached allowed tables for RBAC.
     */
    private ?array $cachedAllowedTables = null;

    /**
     * Query result cache TTL in seconds.
     * Short TTL to avoid stale data but reduce duplicate queries.
     */
    private int $queryCacheTtl = 60;

    /**
     * Set cached allowed tables (used before session_write_close).
     */
    public function setAllowedTables(array $tables): void
    {
        $this->cachedAllowedTables = $tables;
    }

    /**
     * Get allowed tables for current user (RBAC).
     */
    public function getAllowedTables(): array
    {
        if ($this->cachedAllowedTables !== null) {
            return $this->cachedAllowedTables;
        }

        if (!Auth::check()) {
            return [];
        }

        $user = Auth::user();

        if ($user->is_admin) {
            return cache()->remember('agentic_all_tables_admin', 600, function () {
                $tables = DB::connection('pgsql_mbi')->select(
                    "SELECT table_name FROM information_schema.tables WHERE table_schema = 'sch_mbi' ORDER BY table_name"
                );
                return array_column($tables, 'table_name');
            });
        }

        $roleId = $user->role;
        return cache()->remember("agentic_allowed_tables_role_{$roleId}", 600, function () use ($roleId) {
            return RolePermission::where('role_id', $roleId)->pluck('table_name')->toArray();
        });
    }

    /**
     * Execute SQL SELECT query with 6-layer security validation.
     */
    public function executeQuery(string $sql, string $label, array $currencyColumns = []): string
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

        // ── LAYER 3: Blokir kata kunci berbahaya ─────────────────────────────
        $forbidden = [
            'insert', 'update', 'delete', 'merge', 'upsert',
            'drop', 'truncate', 'alter', 'create', 'rename',
            'grant', 'revoke', 'execute', 'exec', 'call', 'do',
            'copy', 'vacuum', 'pg_read_file', 'pg_write_file',
            'lo_import', 'lo_export', 'dblink', 'dblink_exec',
        ];
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
        $allowed = $this->getAllowedTables();
        if (preg_match_all('/(?:from|join)\s+(?:sch_mbi\.)?([a-zA-Z0-9_]+)/i', $trimmedSql, $matches)) {
            foreach ($matches[1] as $tbl) {
                $tbl = strtolower(trim($tbl));
                if (in_array($tbl, ['select', 'where', 'on', 'and', 'or', 'as', 'lateral'])) continue;
                if (!in_array($tbl, $allowed)) {
                    Log::warning("[ToolCallExecutor] Access denied to table '{$tbl}'");
                    return $this->errorResponse("Akses ditolak: tabel '{$tbl}' tidak diizinkan.");
                }
            }
        }

        // ── LAYER 6: Execute Query ─────────────────────────────────────────────
        $cleanSql = $trimmedSql;
        Log::info("[ToolCallExecutor] Executing SQL: " . substr($cleanSql, 0, 300));

        // ── QUERY RESULT CACHING ──────────────────────────────────────────────
        // Generate cache key from SQL hash to avoid duplicate queries
        $cacheKey = 'query_result_' . md5($cleanSql . '_' . Auth::id());
        
        // Check if query result is cached
        $cachedResult = Cache::get($cacheKey);
        if ($cachedResult !== null) {
            Log::info("[ToolCallExecutor] Using cached query result (saved DB call)");
            return $cachedResult;
        }

        try {
            // ANTI-LIMIT: No statement timeout for SQL execution
            DB::connection('pgsql_mbi')->statement('SET statement_timeout = 0');
            $rows = DB::connection('pgsql_mbi')->select($cleanSql);
        } catch (\Exception $e) {
            Log::error("[ToolCallExecutor] Query failed: " . $e->getMessage() . " | SQL: " . $cleanSql);

            $dbError = $e->getMessage();

            $msg = str_contains($dbError, 'statement timeout')
                ? 'Query memakan waktu terlalu lama. Coba persempit data dengan menambahkan filter tahun, bulan, atau wilayah (misal: WHERE periode_tahun = EXTRACT(YEAR FROM NOW())).'
                : "DATABASE_ERROR: {$dbError}. \n\nHINT UNTUK AI: Jika kesalahan disebabkan oleh nama kolom atau tabel yang tidak ditemukan, Anda WAJIB memanggil tool 'get_schema_info' atau 'describe_table' untuk memverifikasi struktur tabel sch_mbi yang benar sebelum mencoba query lagi. Jangan menebak nama kolom.";

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

        // --- SMARTER AI: Auto-detect currency columns as a safety net ---
        $detectedCurrencyCols = $this->autoDetectCurrencyColumns($data[0], $currencyColumns);

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
     * Auto-detect currency columns based on common business naming patterns.
     */
    public function autoDetectCurrencyColumns(array $sampleRow, array $existingCols): array
    {
        $cols = [];

        // Base currency patterns (Monetary terms)
        $moneyPatterns = [
            'total_netto', 'total_dpp', 'harga', 'price',
            'nominal', 'nilai', 'amount', 'biaya', 'fee',
            'ongkir', 'pajak', 'tax', 'diskon', 'discount',
            'laba', 'profit', 'cogs', 'gpn', 'hpp', 'netto',
            'dpp', 'saldo', 'revenue', 'omzet', 'income'
        ];

        // Exclusion patterns (Quantities, IDs, Percents)
        $excludePatterns = [
            'qty', 'count', 'jumlah', 'terjual', 'unit',
            'stok', 'stock', 'persen', 'percent', 'pencapaian',
            'growth', 'id', 'kode', 'nomor', 'no_', 'bulan', 'tahun',
            'transaksi', 'faktur', 'nota', 'cabang', 'pelanggan',
            'barang', 'produk', 'hari', 'baris', 'freq', 'frekuensi'
        ];

        // 1. FILTER ASURANSI: Kadang AI salah memasukkan kolom non-uang ke currency_columns
        foreach ($existingCols as $col) {
            $lowCol = strtolower($col);
            $shouldExclude = false;
            foreach ($excludePatterns as $e) {
                if (str_contains($lowCol, $e)) {
                    $shouldExclude = true;
                    break;
                }
            }
            // Khusus: Kalau match exclude tapi namanya memang valid uang
            if (!$shouldExclude || in_array($lowCol, ['total_netto', 'total_dpp'])) {
                $cols[] = $col;
            }
        }

        // 2. AUTO-DETECT: Tambahkan kolom uji coba jika cocok dengan pattern uang
        $currentColsLower = array_map('strtolower', $cols);
        foreach (array_keys($sampleRow) as $col) {
            $lowCol = strtolower($col);

            if (in_array($lowCol, $currentColsLower)) continue;

            $isMoney = false;
            foreach ($moneyPatterns as $p) {
                if (str_contains($lowCol, $p)) {
                    $isMoney = true;
                    break;
                }
            }

            if ($isMoney) {
                $shouldExclude = false;
                foreach ($excludePatterns as $e) {
                    if (str_contains($lowCol, $e)) {
                        $shouldExclude = true;
                        break;
                    }
                }

                if (!$shouldExclude || in_array($lowCol, ['total_netto', 'total_dpp'])) {
                    $cols[] = $col;
                }
            }
        }

        return array_unique($cols);
    }
}
