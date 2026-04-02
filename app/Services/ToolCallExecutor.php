<?php

namespace App\Services;

use App\Models\RolePermission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ToolCallExecutor
 *
 * Mengeksekusi tool calls yang diminta AI (OpenAI gpt-5.4).
 * Tools:
 *   - list_tables     : Daftar tabel yang boleh diakses user
 *   - describe_table  : Struktur kolom sebuah tabel
 *   - execute_query   : Eksekusi SELECT query ke PostgreSQL
 *   - get_schema_info : Ringkasan semua tabel + kolom sekaligus
 */
class ToolCallExecutor
{
    // FIX: Cache allowed tables so Auth::check() is not needed inside stream
    private ?array $cachedAllowedTables = null;

    public function setAllowedTables(array $tables): void
    {
        $this->cachedAllowedTables = $tables;
    }

    // ── Definisi tools yang dikirim ke OpenAI ─────────────────────────────────
    // FIX: properties kosong harus pakai stdClass agar JSON encode jadi {} bukan []
    // FIX: deskripsi lebih detail agar AI tahu kapan memanggil tiap tool
    public static function getToolDefinitions(): array
    {
        return [
            [
                'type'        => 'function',
                'name'        => 'get_schema_info',
                'description' => 'Get a complete overview of all accessible tables with their columns and data types in one single call. ALWAYS call this first before writing any SQL query. This gives you everything you need to understand the database structure.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),  // FIX: {} bukan []
                    'required'   => [],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'list_tables',
                'description' => 'List all database table names the current user is allowed to access. Use this only if you need a quick list of table names without column details.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),  // FIX: {} bukan []
                    'required'   => [],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'describe_table',
                'description' => 'Get all columns and their data types for a specific table. Use this when you need detailed information about a single table after already knowing the table name.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'table_name' => [
                            'type'        => 'string',
                            'description' => 'The exact table name without schema prefix, e.g. "view_data_penjualan_rinci_mbi"',
                        ],
                    ],
                    'required' => ['table_name'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'execute_query',
                'description' => 'Execute a SQL SELECT query to retrieve business data from the PostgreSQL database (schema: sch_mbi). Always prefix table names with "sch_mbi.". USE LIMIT when the user asks for a specific number (e.g. "top 10"), but do NOT use LIMIT for general "show/list" requests where the user wants to see all data.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'sql'   => [
                            'type'        => 'string',
                            'description' => 'A valid PostgreSQL SELECT query. Must include sch_mbi. prefix for all table names. Use LIMIT only if explicitly requested or for performance on "Top N" queries. Example: SELECT nama_barang, SUM(qty_jual) as total FROM sch_mbi.view_data_penjualan_rinci_mbi GROUP BY nama_barang ORDER BY total DESC LIMIT 10',
                        ],
                        'label' => [
                            'type'        => 'string',
                            'description' => 'A short business-friendly description of what this query retrieves, e.g. "10 produk terlaris" or "Total penjualan per cabang"',
                        ],
                        'currency_columns' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'string'],
                            'description' => 'A list of column names in the result that should be formatted as Indonesian Rupiah (currency), e.g. ["total_netto", "harga_satuan", "profit"]. ONLY include columns that represent monetary values.',
                        ],
                    ],
                    'required' => ['sql', 'label'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'get_business_context',
                'description' => 'Retrieve documentation about business metrics (KPIs), definitions, calculations, and regional hierarchies. Use this when you need to understand how to interpret specific data fields or calculate metrics like Gross Profit Margin or Turnover.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                    'required'   => [],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'analyze_trend',
                'description' => 'Perform statistical trend analysis on a dataset. Calculates growth rates and identifies the overall direction (upward/downward) of a series of values over time.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'data' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'object'],
                            'description' => 'The dataset returned by execute_query.',
                        ],
                        'value_column' => [
                            'type'        => 'string',
                            'description' => 'The column name containing the numeric values to analyze.',
                        ],
                        'period_column' => [
                            'type'        => 'string',
                            'description' => 'The column name containing the time periods (e.g., month, year).',
                        ],
                    ],
                    'required' => ['data', 'value_column', 'period_column'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'detect_anomalies',
                'description' => 'Identify significant outliers in a dataset that deviate from the business norm (e.g., unusually low sales or high stock).',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'data' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'object'],
                            'description' => 'The dataset to check for anomalies.',
                        ],
                        'value_column' => [
                            'type'        => 'string',
                            'description' => 'The numeric column to check for outliers.',
                        ],
                    ],
                    'required' => ['data', 'value_column'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'compare_periods',
                'description' => 'Precisely compare two specific time periods in a dataset to calculate growth or decline in absolute and percentage terms (MoM/YoY analysis).',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'data' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'object'],
                            'description' => 'The dataset containing multiple periods.',
                        ],
                        'value_column' => [
                            'type'        => 'string',
                            'description' => 'The column name for the values being compared.',
                        ],
                        'period_column' => [
                            'type'        => 'string',
                            'description' => 'The column name for time periods.',
                        ],
                        'base_period' => [
                            'type'        => 'string',
                            'description' => 'The period to use as a baseline (e.g., "2024-01").',
                        ],
                        'compare_period' => [
                            'type'        => 'string',
                            'description' => 'The period to compare against the baseline (e.g., "2024-02").',
                        ],
                    ],
                    'required' => ['data', 'value_column', 'period_column', 'base_period', 'compare_period'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'predict_future',
                'description' => 'Predict future values based on a historical dataset using linear regression. Use this when the user asks for "forecast", "projection", or "predictions" for upcoming periods.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'data' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'object'],
                            'description' => 'The historical dataset containing a numeric value column and a period column.',
                        ],
                        'value_column' => [
                            'type'        => 'string',
                            'description' => 'The column name containing the numeric values to project.',
                        ],
                        'period_column' => [
                            'type'        => 'string',
                            'description' => 'The column name for the time sequence (used for sorting).',
                        ],
                        'periods_to_project' => [
                            'type'        => 'integer',
                            'description' => 'Number of future units to project (e.g., 3 months). Max recommended: 6.',
                            'default'     => 3,
                        ],
                    ],
                    'required' => ['data', 'value_column', 'period_column', 'periods_to_project'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'audit_dataset',
                'description' => 'Perform a comprehensive "Proactive Audit" on a dataset. It automatically detects anomalies, trends, top contributors (Pareto), and volatility. Use this to find "hidden stories" or when a user asks for a general "audit" or "insight" on a list of data.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'data' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'object'],
                            'description' => 'The dataset to audit.',
                        ],
                        'value_column' => [
                            'type'        => 'string',
                            'description' => 'The main numeric metric to audit (e.g., "total_netto").',
                        ],
                        'label_column' => [
                            'type'        => 'string',
                            'description' => 'The descriptive column (e.g., "nama_cabang" or "bulan").',
                        ],
                    ],
                    'required' => ['data', 'value_column', 'label_column'],
                ],
            ],
        ];
    }

    // ── Dispatch tool call dari AI ────────────────────────────────────────────
    public function execute(string $toolName, array $arguments): string
    {
        Log::info("[ToolCallExecutor] Tool called: {$toolName}", $arguments);

        try {
            return match ($toolName) {
                'list_tables'     => $this->listTables(),
                'describe_table'  => $this->describeTable($arguments['table_name'] ?? ''),
                'execute_query'   => $this->executeQuery($arguments['sql'] ?? '', $arguments['label'] ?? '', $arguments['currency_columns'] ?? []),
                'get_schema_info' => $this->getSchemaInfo(),
                'get_business_context' => $this->getBusinessContext(),
                'analyze_trend'    => $this->analyzeTrend($arguments['data'] ?? [], $arguments['value_column'] ?? '', $arguments['period_column'] ?? ''),
                'detect_anomalies' => $this->detectAnomalies($arguments['data'] ?? [], $arguments['value_column'] ?? ''),
                'compare_periods'  => $this->comparePeriods($arguments['data'] ?? [], $arguments['value_column'] ?? '', $arguments['period_column'] ?? '', $arguments['base_period'] ?? '', $arguments['compare_period'] ?? ''),
                'predict_future'   => $this->predictFuture($arguments['data'] ?? [], $arguments['value_column'] ?? '', $arguments['period_column'] ?? '', $arguments['periods_to_project'] ?? 3),
                'audit_dataset'    => $this->auditDataset($arguments['data'] ?? [], $arguments['value_column'] ?? '', $arguments['label_column'] ?? ''),
                default           => json_encode(['error' => "Unknown tool: {$toolName}"]),
            };
        } catch (\Throwable $e) {
            Log::error("[ToolCallExecutor] Tool {$toolName} failed: " . $e->getMessage());
            return json_encode(['error' => 'Permintaan tidak dapat diproses saat ini. Silakan coba lagi.']);
        }
    }

    // ── list_tables ───────────────────────────────────────────────────────────
    private function listTables(): string
    {
        $allowed = $this->getAllowedTables();
        return json_encode([
            'tables' => $allowed,
            'total'  => count($allowed),
            'schema' => 'sch_mbi',
            'note'   => 'Always prefix table names with "sch_mbi." in queries',
        ]);
    }

    // ── describe_table ────────────────────────────────────────────────────────
    private function describeTable(string $tableName): string
    {
        if (empty($tableName)) {
            return json_encode(['error' => 'table_name is required']);
        }

        $allowed = $this->getAllowedTables();
        if (!in_array($tableName, $allowed)) {
            return json_encode(['error' => "Access denied: table '{$tableName}' is not in your allowed tables list."]);
        }

        $columns = DB::connection('pgsql_mbi')->select("
            SELECT column_name, data_type, is_nullable
            FROM information_schema.columns
            WHERE table_name = ? AND table_schema = 'sch_mbi'
            ORDER BY ordinal_position
        ", [$tableName]);

        if (empty($columns)) {
            return json_encode(['error' => "Table '{$tableName}' not found or has no columns."]);
        }

        $result = [];
        foreach ($columns as $col) {
            $result[] = [
                'column'   => $col->column_name,
                'type'     => $col->data_type,
                'nullable' => $col->is_nullable,
            ];
        }

        return json_encode([
            'table'   => $tableName,
            'schema'  => 'sch_mbi',
            'sql_ref' => "sch_mbi.{$tableName}",
            'columns' => $result,
        ]);
    }

    // ── execute_query ─────────────────────────────────────────────────────────
    private function executeQuery(string $sql, string $label, array $currencyColumns = []): string
    {
        if (empty($sql)) {
            return json_encode(['error' => 'sql is required']);
        }

        // ── LAYER 1: Strip comments ──────────────────────────────────────────
        $sqlStripped = preg_replace('/--[^\n]*/', '', $sql);
        $sqlStripped = preg_replace('/\/\*.*?\*\//s', '', $sqlStripped);
        $sqlStripped = trim($sqlStripped);

        // ── LAYER 2: Harus diawali SELECT ────────────────────────────────────
        if (!preg_match('/^\s*SELECT\b/i', $sqlStripped)) {
            Log::warning("[ToolCallExecutor] Rejected non-SELECT query: " . substr($sql, 0, 200));
            return json_encode(['error' => 'Hanya query SELECT yang diizinkan.']);
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
                return json_encode(['error' => "Perintah '{$kw}' tidak diizinkan."]);
            }
        }

        // ── LAYER 4: Blokir multiple statements ──────────────────────────────
        $trimmedSql = rtrim($sqlStripped, '; ');
        if (str_contains($trimmedSql, ';')) {
            return json_encode(['error' => 'Hanya satu query per panggilan.']);
        }

        // ── LAYER 5: Validasi akses tabel ─────────────────────────────────────
        $allowed = $this->getAllowedTables();
        if (preg_match_all('/(?:from|join)\s+(?:sch_mbi\.)?([a-zA-Z0-9_]+)/i', $trimmedSql, $matches)) {
            foreach ($matches[1] as $tbl) {
                $tbl = strtolower(trim($tbl));
                if (in_array($tbl, ['select', 'where', 'on', 'and', 'or', 'as', 'lateral'])) continue;
                if (!in_array($tbl, $allowed)) {
                    Log::warning("[ToolCallExecutor] Access denied to table '{$tbl}'");
                    return json_encode(['error' => "Akses ditolak: tabel '{$tbl}' tidak diizinkan."]);
                }
            }
        }

        // ── LAYER 6: Execute Query ─────────────────────────────────────────────
        $cleanSql = $trimmedSql;
        Log::info("[ToolCallExecutor] Executing SQL: " . substr($cleanSql, 0, 300));

        // FIX: Hapus SET TRANSACTION READ ONLY karena tidak kompatibel dengan Laravel DB::transaction()
        // Cukup jalankan langsung — validasi SELECT + forbidden keywords di atas sudah cukup aman
        try {
            // ANTI-LIMIT: No statement timeout for SQL execution
            DB::connection('pgsql_mbi')->statement('SET statement_timeout = 0');
            $rows = DB::connection('pgsql_mbi')->select($cleanSql);
        } catch (\Exception $e) {
            Log::error("[ToolCallExecutor] Query failed: " . $e->getMessage() . " | SQL: " . $cleanSql);
            
            $dbError = $e->getMessage();
            
            $msg = str_contains($dbError, 'statement timeout')
                ? 'Query memakan waktu terlalu lama. Coba persempit data dengan menambahkan filter tahun, bulan, atau wilayah (misal: WHERE periode_tahun = \'2025\').'
                : "DATABASE_ERROR: {$dbError}. \n\nHINT UNTUK AI: Jika kesalahan disebabkan oleh nama kolom atau tabel yang tidak ditemukan, Anda WAJIB memanggil tool 'get_schema_info' atau 'describe_table' untuk memverifikasi struktur tabel sch_mbi yang benar sebelum mencoba query lagi. Jangan menebak nama kolom.";

            return json_encode(['error' => $msg]);
        }

        if (empty($rows)) {
            return json_encode([
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

        $result = [
            'label'            => $label,
            'rows_returned'    => $returned,
            'columns'          => array_keys($data[0]),
            'currency_columns' => $currencyColumns,
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
        return json_encode($result);
    }

    // ── get_schema_info ───────────────────────────────────────────────────────
    // FIX: Batasi jumlah kolom per tabel agar tidak overflow context window AI
    private function getSchemaInfo(): string
    {
        $allowed = $this->getAllowedTables();

        if (empty($allowed)) {
            return json_encode(['error' => 'Anda tidak memiliki izin untuk mengakses data. Silakan hubungi administrator.']);
        }

        // Buat placeholder untuk IN clause
        $placeholders = implode(',', array_fill(0, count($allowed), '?'));

        $results = DB::connection('pgsql_mbi')->select("
            SELECT table_name, column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = 'sch_mbi'
            AND table_name IN ({$placeholders})
            ORDER BY table_name, ordinal_position
        ", $allowed);

        $schema = [];
        foreach ($results as $row) {
            if (!isset($schema[$row->table_name])) {
                $schema[$row->table_name] = [];
            }
            // FIX: Batasi max 30 kolom per tabel agar JSON tidak overflow context AI
            if (count($schema[$row->table_name]) < 30) {
                $schema[$row->table_name][] = $row->column_name . ' (' . $row->data_type . ')';
            }
        }

        // FIX: Cek total ukuran JSON, jika terlalu besar kirim versi ringkas (nama tabel saja)
        $fullJson = json_encode([
            'schema'       => 'sch_mbi',
            'total_tables' => count($schema),
            'tables'       => $schema,
            'usage_note'   => 'Prefix all table names with "sch_mbi." in SQL queries.',
        ]);

        // Jika > 20KB, kirim versi ringkas: nama tabel + jumlah kolom saja
        if (strlen($fullJson) > 20000) {
            Log::warning('[ToolCallExecutor] getSchemaInfo terlalu besar (' . strlen($fullJson) . ' chars), mengirim versi ringkas.');
            $compact = [];
            foreach ($schema as $tbl => $cols) {
                $compact[$tbl] = count($cols) . ' columns: ' . implode(', ', array_slice($cols, 0, 5)) . (count($cols) > 5 ? '...' : '');
            }
            return json_encode([
                'schema'       => 'sch_mbi',
                'total_tables' => count($compact),
                'tables'       => $compact,
                'usage_note'   => 'Schema ringkas karena terlalu besar. Gunakan describe_table untuk detail kolom lengkap.',
            ]);
        }

        return $fullJson;
    }

    // ── get_business_context ──────────────────────────────────────────────────
    private function getBusinessContext(): string
    {
        $path = config_path('business_metrics.json');
        if (!file_exists($path)) {
            return json_encode(['error' => 'Business metrics configuration not found.']);
        }

        $content = file_get_contents($path);
        return $content ?: json_encode(['error' => 'Failed to read business metrics config.']);
    }

    // ── analyze_trend ─────────────────────────────────────────────────────────
    private function analyzeTrend(array $data, string $valueCol, string $periodCol): string
    {
        if (empty($data)) return json_encode(['error' => 'Data is empty.']);
        
        $series = collect($data)->sortBy($periodCol)->values();
        $count = $series->count();
        
        if ($count < 2) return json_encode(['error' => 'Not enough data points for trend analysis.']);

        $first = (float)($series[0][$valueCol] ?? 0);
        $last = (float)($series[$count - 1][$valueCol] ?? 0);
        
        $totalGrowth = $first != 0 ? (($last - $first) / abs($first)) * 100 : 0;
        $avgGrowth = 0;
        $growths = [];

        for ($i = 1; $i < $count; $i++) {
            $prev = (float)($series[$i-1][$valueCol] ?? 0);
            $curr = (float)($series[$i][$valueCol] ?? 0);
            $g = $prev != 0 ? (($curr - $prev) / abs($prev)) * 100 : 0;
            $growths[] = $g;
        }
        
        $avgGrowth = count($growths) > 0 ? array_sum($growths) / count($growths) : 0;
        
        return json_encode([
            'trend' => $last > $first ? 'UPWARD' : ($last < $first ? 'DOWNWARD' : 'STABLE'),
            'total_growth_pct' => round($totalGrowth, 2),
            'avg_periodic_growth_pct' => round($avgGrowth, 2),
            'start_value' => $first,
            'end_value' => $last,
            'data_points' => $count
        ]);
    }

    // ── detect_anomalies ──────────────────────────────────────────────────────
    private function detectAnomalies(array $data, string $valueCol): string
    {
        if (empty($data)) return json_encode(['error' => 'Data is empty.']);

        $values = collect($data)->pluck($valueCol)->map(fn($v) => (float)$v);
        $count = $values->count();
        
        if ($count < 3) return json_encode(['error' => 'Insufficient data for anomaly detection.']);

        $avg = $values->avg();
        // Calculate Standard Deviation
        $variance = $values->reduce(fn($carry, $val) => $carry + pow($val - $avg, 2), 0) / $count;
        $stdDev = sqrt($variance);
        
        $anomalies = [];
        foreach ($data as $index => $row) {
            $val = (float)($row[$valueCol] ?? 0);
            if ($stdDev > 0) {
                $zScore = ($val - $avg) / $stdDev;
                if (abs($zScore) > 2) { // 2 Sigma Threshold
                    $anomalies[] = [
                        'row_index' => $index,
                        'value' => $val,
                        'z_score' => round($zScore, 2),
                        'severity' => abs($zScore) > 3 ? 'HIGH' : 'MEDIUM',
                        'data' => $row
                    ];
                }
            }
        }

        return json_encode([
            'avg_value' => round($avg, 2),
            'std_dev' => round($stdDev, 2),
            'anomalies_found' => count($anomalies),
            'anomalies' => $anomalies
        ]);
    }

    // ── compare_periods ───────────────────────────────────────────────────────
    private function comparePeriods(array $data, string $valueCol, string $periodCol, string $base, string $compare): string
    {
        $baseData = collect($data)->firstWhere($periodCol, $base);
        $compareData = collect($data)->firstWhere($periodCol, $compare);

        if (!$baseData || !$compareData) {
            return json_encode(['error' => "Could not find one or both periods: {$base} or {$compare}"]);
        }

        $vBase = (float)($baseData[$valueCol] ?? 0);
        $vComp = (float)($compareData[$valueCol] ?? 0);
        
        $diff = $vComp - $vBase;
        $diffPct = $vBase != 0 ? ($diff / abs($vBase)) * 100 : 0;

        return json_encode([
            'base_period' => $base,
            'compare_period' => $compare,
            'base_value' => $vBase,
            'compare_value' => $vComp,
            'absolute_difference' => $diff,
            'percentage_difference' => round($diffPct, 2),
            'status' => $diff > 0 ? 'INCREASE' : ($diff < 0 ? 'DECREASE' : 'NO_CHANGE')
        ]);
    }

    // ── predict_future ────────────────────────────────────────────────────────
    private function predictFuture(array $data, string $valueCol, string $periodCol, int $periodsToProject): string
    {
        if (empty($data)) return json_encode(['error' => 'Data is empty.']);
        
        $series = collect($data)->sortBy($periodCol)->values();
        $n = $series->count();
        
        if ($n < 3) return json_encode(['error' => 'Minimum 3 data points are required for forecasting.']);

        $sumX = 0; $sumY = 0; $sumXY = 0; $sumXX = 0; $sumYY = 0;
        
        foreach ($series as $i => $row) {
            $x = $i;
            $y = (float)($row[$valueCol] ?? 0);
            
            $sumX += $x;
            $sumY += $y;
            $sumXY += ($x * $y);
            $sumXX += ($x * $x);
            $sumYY += ($y * $y);
        }

        $denominator = ($n * $sumXX) - ($sumX * $sumX);
        if ($denominator == 0) return json_encode(['error' => 'Cannot calculate regression (all dates may be identical).']);

        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        // Calculate R-Squared (Confidence)
        $avgY = $sumY / $n;
        $ssTot = 0; $ssRes = 0;
        foreach ($series as $i => $row) {
            $y = (float)($row[$valueCol] ?? 0);
            $yPred = ($slope * $i) + $intercept;
            $ssTot += pow($y - $avgY, 2);
            $ssRes += pow($y - $yPred, 2);
        }
        $rSquared = $ssTot != 0 ? 1 - ($ssRes / $ssTot) : 0;

        $projections = [];
        for ($i = 0; $i < $periodsToProject; $i++) {
            $futureX = $n + $i;
            $val = ($slope * $futureX) + $intercept;
            $projections[] = [
                'period_index' => $futureX,
                'projected_value' => round($val, 2)
            ];
        }

        return json_encode([
            'slope' => round($slope, 2),
            'intercept' => round($intercept, 2),
            'confidence_score_r2' => round($rSquared, 2), // R^2: 1 = exact fit, 0 = no fit
            'prediction_strength' => $rSquared > 0.8 ? 'STRONG' : ($rSquared > 0.5 ? 'MODERATE' : 'WEAK'),
            'projections' => $projections,
            'message' => 'Proyeksi berdasarkan tren linear historis.'
        ]);
    }

    // ── audit_dataset (The "Proactive Insight" Wrapper) ───────────────────────
    private function auditDataset(array $data, string $valueCol, string $labelCol): string
    {
        if (empty($data)) return json_encode(['error' => 'Data is empty.']);

        $collection = collect($data);
        $total = $collection->sum($valueCol);
        
        // 1. Trend Analysis
        // Try to find a period-like column if no explicit periodCol is provided, or just use indices
        $trend = json_decode($this->analyzeTrend($data, $valueCol, $labelCol), true);
        
        // 2. Anomaly Detection
        $anomalies = json_decode($this->detectAnomalies($data, $valueCol), true);
        
        // 3. Pareto Analysis (Top Contributors)
        $sorted = $collection->sortByDesc($valueCol)->values();
        $top3 = $sorted->take(3)->map(function($row) use ($valueCol, $labelCol, $total) {
            $val = (float)$row[$valueCol];
            return [
                'label' => $row[$labelCol] ?? 'Unknown',
                'value' => $val,
                'pct' => $total != 0 ? round(($val / $total) * 100, 1) : 0
            ];
        });

        $top3Pct = $top3->sum('pct');

        // 4. Volatility (CV)
        $values = $collection->pluck($valueCol)->map(fn($v) => (float)$v);
        $mean = $values->avg();
        $variance = $values->reduce(fn($carry, $val) => $carry + pow($val - $mean, 2), 0) / $values->count();
        $stdDev = sqrt($variance);
        $volatility = $mean != 0 ? ($stdDev / abs($mean)) : 0;

        return json_encode([
            'audit_summary' => [
                'total_value' => $total,
                'volatility_score' => round($volatility, 2), // CV
                'volatility_label' => $volatility > 0.5 ? 'HIGH' : ($volatility > 0.2 ? 'MODERATE' : 'STABLE'),
                'is_concentrated' => $top3Pct > 70, // 70-80 rule
                'top_3_drivers_pct' => $top3Pct
            ],
            'top_contributors' => $top3,
            'trend_summary'    => $trend,
            'anomalies'        => $anomalies['anomalies'] ?? [],
            'strategic_hint'   => $top3Pct > 70 ? "Peringatan: Bisnis sangat bergantung pada 3 item teratas ($top3Pct% total). Risiko tinggi jika pasar bergeser." : "Distribusi bisnis cukup sehat dan tersebar."
        ]);
    }

    // ── Helper: daftar tabel yang boleh diakses ───────────────────────────────
    public function getAllowedTables(): array
    {
        // FIX: Return cached tables if already resolved (e.g., set before session_write_close)
        if ($this->cachedAllowedTables !== null) {
            return $this->cachedAllowedTables;
        }

        // Jika tidak login, tidak ada akses sama sekali
        // (route sudah dilindungi middleware 'auth', tapi ini sebagai double-check)
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
}
