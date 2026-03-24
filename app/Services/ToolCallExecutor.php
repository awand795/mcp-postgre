<?php

namespace App\Services;

use App\Models\RolePermission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ToolCallExecutor
 *
 * Bertanggung jawab mengeksekusi tool calls yang diminta AI.
 * Tool yang tersedia:
 *   - list_tables        : Daftar tabel yang boleh diakses user
 *   - describe_table     : Struktur kolom sebuah tabel
 *   - execute_query      : Eksekusi SELECT query ke PostgreSQL
 *   - get_schema_info    : Informasi schema secara keseluruhan (shortcut)
 */
class ToolCallExecutor
{
    // ── Definisi tools yang dikirim ke AI ─────────────────────────────────────
    public static function getToolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'list_tables',
                    'description' => 'List all database tables that the current user is allowed to access. Always call this first to know what tables are available.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => (object)[],
                        'required'   => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'describe_table',
                    'description' => 'Get all columns and their data types for a specific table. Use this to understand the structure before writing a query.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'table_name' => [
                                'type'        => 'string',
                                'description' => 'The table name (without schema prefix, e.g. "view_data_penjualan_rinci_mbi")',
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
                    'description' => 'Execute a SQL SELECT query against the PostgreSQL database (schema: sch_mbi). Always prefix table names with "sch_mbi." (e.g. SELECT * FROM sch_mbi.view_data_penjualan_rinci_mbi LIMIT 10). Only SELECT queries are allowed. Add LIMIT to prevent large result sets.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'sql'   => [
                                'type'        => 'string',
                                'description' => 'A valid PostgreSQL SELECT query. Must include sch_mbi. prefix for all table names.',
                            ],
                            'label' => [
                                'type'        => 'string',
                                'description' => 'A short human-readable description of what this query retrieves, e.g. "Top 10 produk terlaris"',
                            ],
                        ],
                        'required' => ['sql', 'label'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'get_schema_info',
                    'description' => 'Get a summary of all allowed tables with their columns in one call. Useful as an alternative to calling list_tables + multiple describe_table calls.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => (object)[],
                        'required'   => [],
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
            return json_encode(['error' => $e->getMessage()]);
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
            SELECT column_name, data_type, is_nullable, column_default
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
                'default'  => $col->column_default,
            ];
        }

        return json_encode([
            'table'   => $tableName,
            'schema'  => 'sch_mbi',
            'columns' => $result,
            'sql_ref' => "sch_mbi.{$tableName}",
        ]);
    }

    // ── execute_query ─────────────────────────────────────────────────────────
    private function executeQuery(string $sql, string $label): string
    {
        if (empty($sql)) {
            return json_encode(['error' => 'sql is required']);
        }

        // ── LAYER 1: Strip comments dulu sebelum validasi ──────────────────
        // Hapus single-line comments (-- ...) dan multi-line (/* ... */)
        $sqlStripped = preg_replace('/--[^\n]*/', '', $sql);
        $sqlStripped = preg_replace('/\/\*.*?\*\//s', '', $sqlStripped);
        $sqlStripped = trim($sqlStripped);

        // ── LAYER 2: Harus diawali SELECT (tidak boleh ada apapun sebelumnya) ──
        if (!preg_match('/^\s*SELECT\b/i', $sqlStripped)) {
            Log::warning("[ToolCallExecutor] Rejected non-SELECT query: " . substr($sql, 0, 200));
            return json_encode(['error' => 'Hanya query SELECT yang diizinkan. Operasi tulis data tidak diperbolehkan.']);
        }

        // ── LAYER 3: Blokir semua kata kunci berbahaya sebagai word boundary ──
        $forbidden = [
            // DML - data modification
            'insert', 'update', 'delete', 'merge', 'upsert',
            // DDL - structure changes
            'drop', 'truncate', 'alter', 'create', 'rename', 'comment',
            // DCL - privileges
            'grant', 'revoke',
            // Execution
            'execute', 'exec', 'call', 'do',
            // System / dangerous
            'copy', 'vacuum', 'reindex', 'cluster', 'analyze',
            'pg_read_file', 'pg_write_file', 'pg_ls_dir',
            'lo_import', 'lo_export',
            'dblink', 'dblink_exec',
        ];
        $lowerSql  = strtolower($sqlStripped);
        foreach ($forbidden as $kw) {
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/', $lowerSql)) {
                Log::warning("[ToolCallExecutor] Forbidden keyword '{$kw}' in query: " . substr($sql, 0, 200));
                return json_encode(['error' => "Perintah '{$kw}' tidak diizinkan. Hanya operasi baca (SELECT) yang diperbolehkan."]);
            }
        }

        // ── LAYER 4: Blokir multiple statements (stacked queries) ──────────
        // Hapus semicolon di akhir (wajar), tapi tolak jika ada di tengah
        $trimmedSql = rtrim($sqlStripped, '; ');
        if (str_contains($trimmedSql, ';')) {
            Log::warning("[ToolCallExecutor] Multiple statements detected: " . substr($sql, 0, 200));
            return json_encode(['error' => 'Hanya satu query SELECT yang diizinkan per panggilan. Multiple statements diblokir.']);
        }

        // ── LAYER 5: Validasi akses tabel ─────────────────────────────────────
        $allowed = $this->getAllowedTables();
        if (preg_match_all('/(?:from|join)\s+(?:sch_mbi\.)?([a-zA-Z0-9_]+)/i', $sqlStripped, $matches)) {
            foreach ($matches[1] as $tbl) {
                $tbl = strtolower(trim($tbl));
                // Skip subquery aliases & SQL keywords
                if (in_array($tbl, ['select', 'where', 'on', 'and', 'or', 'as'])) continue;
                if (!in_array($tbl, $allowed)) {
                    Log::warning("[ToolCallExecutor] Access denied to table '{$tbl}'");
                    return json_encode(['error' => "Akses ditolak: tabel '{$tbl}' tidak ada dalam daftar tabel yang diizinkan."]);
                }
            }
        }

        // ── LAYER 6: Paksa transaction READ ONLY + tambah LIMIT jika tidak ada ──
        $cleanSql = $trimmedSql;
        if (!preg_match('/\blimit\b/i', $cleanSql)) {
            $cleanSql .= ' LIMIT 100';
        }

        Log::info("[ToolCallExecutor] Executing READ-ONLY SQL: " . substr($cleanSql, 0, 300));

        // Jalankan dalam transaksi read-only agar tidak bisa commit perubahan
        $rows = DB::connection('pgsql_mbi')->transaction(function () use ($cleanSql) {
            DB::connection('pgsql_mbi')->statement('SET TRANSACTION READ ONLY');
            return DB::connection('pgsql_mbi')->select($cleanSql);
        });

        if (empty($rows)) {
            return json_encode([
                'label'   => $label,
                'sql'     => $cleanSql,
                'rows'    => [],
                'total'   => 0,
                'message' => 'No data found for this query.',
            ]);
        }

        $data = array_map(fn($row) => (array) $row, $rows);

        return json_encode([
            'label'   => $label,
            'sql'     => $cleanSql,
            'rows'    => $data,
            'total'   => count($data),
            'columns' => array_keys($data[0]),
        ]);
    }

    // ── get_schema_info ───────────────────────────────────────────────────────
    private function getSchemaInfo(): string
    {
        $allowed = $this->getAllowedTables();

        if (empty($allowed)) {
            return json_encode(['error' => 'No tables accessible for your role.']);
        }

        $results = DB::connection('pgsql_mbi')->select("
            SELECT table_name, column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = 'sch_mbi'
            ORDER BY table_name, ordinal_position
        ");

        $schema = [];
        foreach ($results as $row) {
            if (!in_array($row->table_name, $allowed)) continue;
            if (!isset($schema[$row->table_name])) {
                $schema[$row->table_name] = [];
            }
            $schema[$row->table_name][] = $row->column_name . ' (' . $row->data_type . ')';
        }

        return json_encode([
            'schema'       => 'sch_mbi',
            'total_tables' => count($schema),
            'tables'       => $schema,
            'note'         => 'Use "sch_mbi.table_name" in SQL queries',
        ]);
    }

    // ── Helper: daftar tabel yang boleh diakses ───────────────────────────────
    public function getAllowedTables(): array
    {
        if (!Auth::check()) {
            return [
                'view_data_penjualan_rinci_mbi',
                'view_master_cabang_mbi',
                'view_master_pelanggan_mbi',
                'view_data_target_realisasi_mbi',
                'view_target_unit_mbi',
                'view_master_barang_mbi',
                'view_data_kartu_stock_mbi',
                'view_master_provinsi_mbi',
                'view_master_kabupaten_mbi',
            ];
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
