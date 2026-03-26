<?php

namespace App\Services;

use App\Models\RolePermission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ToolCallExecutor
 *
 * Mengeksekusi tool calls yang diminta AI (OpenAI gpt-4o-mini).
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
                'type' => 'function',
                'function' => [
                    'name'        => 'get_schema_info',
                    'description' => 'Get a complete overview of all accessible tables with their columns and data types in one single call. ALWAYS call this first before writing any SQL query. This gives you everything you need to understand the database structure.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => new \stdClass(),  // FIX: {} bukan []
                        'required'   => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'list_tables',
                    'description' => 'List all database table names the current user is allowed to access. Use this only if you need a quick list of table names without column details.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => new \stdClass(),  // FIX: {} bukan []
                        'required'   => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
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
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'execute_query',
                    'description' => 'Execute a SQL SELECT query to retrieve business data from the PostgreSQL database (schema: sch_mbi). Always prefix table names with "sch_mbi." (e.g. SELECT * FROM sch_mbi.view_data_penjualan_rinci_mbi). Only SELECT queries are allowed. Do NOT add LIMIT unless the user explicitly asks for a limited number of rows — always retrieve full data by default.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'sql'   => [
                                'type'        => 'string',
                                'description' => 'A valid PostgreSQL SELECT query. Must include sch_mbi. prefix for all table names. Do NOT add LIMIT unless user explicitly requests limited rows. Example: SELECT nama_barang, SUM(qty_jual) as total FROM sch_mbi.view_data_penjualan_rinci_mbi GROUP BY nama_barang ORDER BY total DESC',
                            ],
                            'label' => [
                                'type'        => 'string',
                                'description' => 'A short business-friendly description of what this query retrieves, e.g. "10 produk terlaris" or "Total penjualan per cabang"',
                            ],
                        ],
                        'required' => ['sql', 'label'],
                    ],
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
                'execute_query'   => $this->executeQuery($arguments['sql'] ?? '', $arguments['label'] ?? ''),
                'get_schema_info' => $this->getSchemaInfo(),
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
    private function executeQuery(string $sql, string $label): string
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

        // ── LAYER 6: ANTI-LIMIT - No forced LIMIT ──────────────────────────────
        $cleanSql = $trimmedSql;
        // Forced LIMIT removed to allow unlimited data retrieval as requested.

        Log::info("[ToolCallExecutor] Executing SQL: " . substr($cleanSql, 0, 300));

        // FIX: Hapus SET TRANSACTION READ ONLY karena tidak kompatibel dengan Laravel DB::transaction()
        // Cukup jalankan langsung — validasi SELECT + forbidden keywords di atas sudah cukup aman
        try {
            // ANTI-LIMIT: No statement timeout for SQL execution
            DB::connection('pgsql_mbi')->statement('SET statement_timeout = 0');
            $rows = DB::connection('pgsql_mbi')->select($cleanSql);
        } catch (\Exception $e) {
            Log::error("[ToolCallExecutor] Query failed: " . $e->getMessage() . " | SQL: " . $cleanSql);
            $msg = str_contains($e->getMessage(), 'statement timeout')
                ? 'Query memakan waktu terlalu lama. Coba persempit data dengan menambahkan filter tahun, bulan, atau wilayah.'
                : 'Data tidak dapat diambil saat ini. Silakan coba lagi atau hubungi administrator.';
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

        $data = array_map(fn($row) => (array) $row, $rows);

        $returned = count($data);

        $result = [
            'label'         => $label,
            'rows_returned' => $returned,
            'columns'       => array_keys($data[0]),
            'rows'          => $data,
        ];

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
