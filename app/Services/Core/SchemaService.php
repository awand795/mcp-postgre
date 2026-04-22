<?php

namespace App\Services\Core;

use App\Services\BaseService;
use App\Services\Database\DriverFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SchemaService
 *
 * Handles schema discovery and table descriptions for multi-database.
 */
class SchemaService extends BaseService
{
    /**
     * Reference to QueryService for RBAC.
     */
    private QueryService $queryService;

    public function __construct(QueryService $queryService)
    {
        $this->queryService = $queryService;
    }

    /**
     * Normalize a table entry from allowedDbs (may be string or ['name'=>...,'description'=>...]).
     */
    private function normalizeTableName(mixed $entry): string
    {
        if (is_array($entry)) {
            return $entry['name'] ?? '';
        }
        return (string) $entry;
    }

    /**
     * Check if a given tableName is allowed within a list of table entries.
     * Supports wildcard '*' and both string and object entry formats.
     */
    private function isTableAllowed(string $tableName, array $tableEntries): bool
    {
        foreach ($tableEntries as $entry) {
            $name = $this->normalizeTableName($entry);
            if ($name === '*' || $name === $tableName) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get columns and data types for a specific table in a specific DB and schema.
     */
    public function describeTable(string $databaseCode, string $schemaName, string $tableName): string
    {
        if (empty($databaseCode) || empty($schemaName) || empty($tableName)) {
            return $this->errorResponse('database_code, schema_name, and table_name are required');
        }

        // FIX: Auto-resolve wildcard schema_name='*' agar Llama/OpenRouter tidak loop.
        // Sebelumnya langsung error, sekarang kita bantu resolve schema yang benar dari RBAC.
        if ($schemaName === '*') {
            $allowedDbsTemp = $this->queryService->getAllowedTables();
            if (isset($allowedDbsTemp[$databaseCode])) {
                $schemas = array_keys($allowedDbsTemp[$databaseCode]);
                $resolvedSchemas = array_filter($schemas, fn($s) => $s !== '*');
                if (!empty($resolvedSchemas)) {
                    $schemaName = array_values($resolvedSchemas)[0];
                    Log::info("[SchemaService] Auto-resolved wildcard schema_name='*' to '{$schemaName}' for db='{$databaseCode}'");
                } else {
                    return $this->safeJsonEncode([
                        'error' => "schema_name '*' tidak valid. Tidak ditemukan schema di database '{$databaseCode}'.",
                        'MANDATORY_AI_ACTION' => "Panggil get_database_schema_info untuk melihat daftar schema dan tabel yang tersedia, kemudian panggil describe_table dengan schema_name yang eksak.",
                    ]);
                }
            }
        }

        // Guard: reject wildcard table_name
        if ($tableName === '*') {
            return $this->safeJsonEncode([
                'error' => "table_name '*' tidak valid. Berikan nama tabel yang eksak.",
                'MANDATORY_AI_ACTION' => "Panggil get_database_schema_info untuk melihat daftar tabel, kemudian panggil describe_table dengan table_name yang spesifik.",
            ]);
        }

        $allowedDbs = $this->queryService->getAllowedTables();
        
        if (!isset($allowedDbs[$databaseCode])) {
            return $this->errorResponse("Access denied: You don't have access to database '{$databaseCode}'.");
        }

        // Check schema access: allow if schema key is '*' or matches exactly
        $schemaAllowed = isset($allowedDbs[$databaseCode][$schemaName])
            || isset($allowedDbs[$databaseCode]['*']);

        if (!$schemaAllowed) {
            // FIX: Berikan daftar schema yang valid agar AI tahu harus pakai schema apa
            $availableSchemas = array_keys($allowedDbs[$databaseCode]);
            return $this->safeJsonEncode([
                'error' => "Access denied: Schema '{$schemaName}' tidak ditemukan di database '{$databaseCode}'.",
                'available_schemas' => $availableSchemas,
                'MANDATORY_AI_ACTION' => "Gunakan salah satu schema dari 'available_schemas' di atas, bukan '{$schemaName}'. Kemudian panggil describe_table lagi dengan schema yang benar.",
            ]);
        }

        // Resolve which table list to use (exact schema or wildcard)
        $tableEntries = $allowedDbs[$databaseCode][$schemaName]
            ?? $allowedDbs[$databaseCode]['*']
            ?? [];

        if (!$this->isTableAllowed($tableName, $tableEntries)) {
            return $this->errorResponse("Access denied: You don't have access to table '{$schemaName}.{$tableName}' in database '{$databaseCode}'.");
        }

        $connName = "temp_conn_{$databaseCode}";
        try {
            $dbModel = \App\Models\DatabaseConnection::where('database', $databaseCode)->active()->first();
            if (!$dbModel) {
                 return $this->errorResponse("Database configuration for '{$databaseCode}' not found or inactive.");
            }

            $adapter = $dbModel->getAdapter();

            DB::purge($connName);
            config(["database.connections.{$connName}" => $dbModel->getConnectionConfig()]);

            // Get columns and FKs
            $result = [];
            $indexes = [];

            // SQLite uses PRAGMA table_info which can't be parameterized
            if ($dbModel->driver === 'sqlite') {
                $columns = DB::connection($connName)->select("PRAGMA table_info({$tableName})");
                foreach ($columns as $col) {
                    $result[] = [
                        'column'   => $col->name,
                        'type'     => $col->type,
                        'nullable' => $col->notnull ? 'NO' : 'YES',
                    ];
                }
            } else {
                $schemaParam = $adapter->usesSchema() ? $schemaName : $dbModel->database;
                $query = $adapter->describeTableWithKeysQuery();
                $columns = DB::connection($connName)->select($query, [$tableName, $schemaParam]);

                foreach ($columns as $col) {
                    $item = [
                        'column'   => $col->column_name,
                        'type'     => $col->data_type,
                        'nullable' => $col->is_nullable,
                        'notes'    => $col->description ?? ''
                    ];
                    if (!empty($col->foreign_key_table)) {
                        $item['references'] = "{$col->foreign_key_table}.{$col->foreign_key_column}";
                    }
                    $result[] = $item;
                }

                // Get Index Info
                $idxQuery = $adapter->getTableIndexesQuery();
                $idxData = DB::connection($connName)->select($idxQuery, [$tableName, $schemaParam]);
                foreach ($idxData as $idx) {
                    $indexes[] = [
                        'name'   => $idx->index_name,
                        'column' => $idx->column_name,
                        'type'   => $idx->is_primary ? 'PRIMARY KEY' : ($idx->is_unique ? 'UNIQUE INDEX' : 'INDEX')
                    ];
                }
            }

            DB::purge($connName);

            if (empty($result)) {
                // FIX: Jika tabel tidak ditemukan di schema itu, coba search schema lain
                // dan berikan MANDATORY_AI_ACTION agar model tahu harus pakai schema apa
                $alternativeSchema = null;
                foreach ($allowedDbs[$databaseCode] as $s => $tbls) {
                    if ($s !== '*' && $s !== $schemaName) {
                        $alternativeSchema = $s;
                        break;
                    }
                }

                $hint = $alternativeSchema
                    ? "MANDATORY_AI_ACTION: Tabel '{$tableName}' tidak ditemukan di schema '{$schemaName}'. Coba panggil describe_table dengan schema_name='{$alternativeSchema}' sebagai gantinya. Atau panggil search_schema dengan keyword='{$tableName}' untuk menemukan lokasi tabel yang tepat."
                    : "MANDATORY_AI_ACTION: Panggil search_schema dengan keyword='{$tableName}' untuk menemukan di schema mana tabel ini berada.";

                return $this->safeJsonEncode([
                    'error'               => "Table '{$schemaName}.{$tableName}' not found or has no columns.",
                    'MANDATORY_AI_ACTION' => $hint,
                ]);
            }

            // ── Deteksi kolom status/aktif dan inject MANDATORY hint ─────────
            $statusColumns = [];
            $statusKeywordPattern = '/^(status|is_active|aktif|status_aktif|flag_aktif|active|enabled|is_enabled)$/i';
            foreach ($result as $col) {
                if (preg_match($statusKeywordPattern, $col['column'])) {
                    $statusColumns[] = $col['column'];
                }
            }

            $response = [
                'database' => $databaseCode,
                'table'    => "{$schemaName}.{$tableName}",
                'columns'  => $result,
                'indexes'  => $indexes,
                'usage_tip' => 'Gunakan get_column_values untuk melihat variasi isi data pada kolom kategori/status.'
            ];

            if (!empty($statusColumns)) {
                $colList = implode(', ', $statusColumns);
                $response['MANDATORY_AI_ACTION'] = implode(' ', [
                    "Tabel ini memiliki kolom STATUS: [{$colList}].",
                    "WAJIB: Sebelum menulis query COUNT atau agregasi apapun,",
                    "panggil get_column_values untuk kolom [{$colList}] agar tahu nilai aktif yang benar.",
                    "Kemudian WAJIB tambahkan filter WHERE {$statusColumns[0]} = '[nilai_aktif]' di semua query COUNT.",
                    "DILARANG menggunakan COUNT tanpa filter status — hasilnya akan SALAH karena termasuk data non-aktif.",
                ]);
            }

            return $this->safeJsonEncode($response);
        } catch (\Exception $e) {
            DB::purge($connName);
            return $this->errorResponse('Failed to describe table: ' . $e->getMessage());
        }
    }

    /**
     * Get distinct values for a specific column.
     *
     * FIX: Fallback SELECT DISTINCT dihapus karena menyebabkan timeout pada VIEW besar.
     * Sekarang jika TABLESAMPLE gagal (misal: karena target adalah VIEW, bukan table fisik),
     * langsung kembalikan instruksi MANDATORY_AI_ACTION agar AI melanjutkan ke describe_table
     * + execute_query dengan filter ILIKE, tanpa membuang waktu 48 detik untuk SELECT DISTINCT.
     */
    public function getColumnValues(string $databaseCode, string $schemaName, string $tableName, string $columnName): string
    {
        if ($schemaName === '*' || $tableName === '*') {
            return $this->errorResponse('Please provide an exact schema_name and table_name. Wildcard "*" is not allowed.');
        }

        $allowedDbs = $this->queryService->getAllowedTables();
        $schemaAllowed = isset($allowedDbs[$databaseCode][$schemaName]) || isset($allowedDbs[$databaseCode]['*']);
        $tableEntries  = $allowedDbs[$databaseCode][$schemaName] ?? $allowedDbs[$databaseCode]['*'] ?? [];

        if (!$schemaAllowed || !$this->isTableAllowed($tableName, $tableEntries)) {
            return $this->errorResponse("Access denied.");
        }

        $connName = "temp_conn_{$databaseCode}";
        try {
            $dbModel = \App\Models\DatabaseConnection::where('database', $databaseCode)->active()->first();
            $adapter = $dbModel->getAdapter();

            DB::purge($connName);
            config(["database.connections.{$connName}" => $dbModel->getConnectionConfig()]);

            $query = $adapter->getDistinctValuesQuery($schemaName, $tableName, $columnName, 20);
            try {
                $values = DB::connection($connName)->select($query);
                $flatValues = array_map(fn($v) => current((array)$v), $values);
                DB::purge($connName);
                return $this->safeJsonEncode([
                    'database'        => $databaseCode,
                    'column'          => "{$schemaName}.{$tableName}.{$columnName}",
                    'distinct_values' => $flatValues,
                    'note'            => count($flatValues) < 20 ? 'Full result' : 'Sampled (top 20)',
                ]);
            } catch (\Exception $tablesampleErr) {
                // FIX: JANGAN coba fallback SELECT DISTINCT — ini akan timeout pada VIEW besar.
                DB::purge($connName);
                Log::warning("[SchemaService] get_column_values skipped for {$tableName}.{$columnName} (likely a VIEW): " . $tablesampleErr->getMessage());
                return $this->safeJsonEncode([
                    'warning' => "get_column_values tidak didukung untuk '{$tableName}' (kemungkinan VIEW atau tabel besar tanpa index pada kolom ini).",
                    'MANDATORY_AI_ACTION' => implode(' ', [
                        "JANGAN tunggu atau retry get_column_values.",
                        "LANGKAH WAJIB BERIKUTNYA:",
                        "(1) Panggil describe_table untuk '{$databaseCode}', '{$schemaName}', '{$tableName}' agar mendapat nama kolom yang TEPAT (terutama kolom tanggal/periode).",
                        "(2) Gunakan filter ILIKE untuk kolom teks: {$columnName} ILIKE '%kata1%' AND {$columnName} ILIKE '%kata2%'.",
                        "(3) Untuk filter tanggal, WAJIB pakai BETWEEN dengan kolom DATE/TIMESTAMP aktual dari describe_table, BUKAN periode_bulan atau periode_tahun.",
                        "(4) Jalankan execute_query dengan nama kolom yang sudah diverifikasi dari describe_table.",
                    ]),
                ]);
            }
        } catch (\Exception $e) {
            DB::purge($connName);
            Log::warning("[SchemaService] getColumnValues outer exception for {$tableName}.{$columnName}: " . $e->getMessage());
            return $this->safeJsonEncode([
                'warning' => 'get_column_values gagal untuk tabel/view ini.',
                'MANDATORY_AI_ACTION' => implode(' ', [
                    "JANGAN retry get_column_values.",
                    "Panggil describe_table untuk '{$databaseCode}', '{$schemaName}', '{$tableName}' terlebih dahulu.",
                    "Kemudian jalankan execute_query dengan nama kolom yang benar dari hasil describe_table.",
                    "Gunakan ILIKE untuk filter teks dan BETWEEN untuk filter tanggal.",
                ]),
            ]);
        }
    }

    /**
     * Get View DDL definition.
     */
    public function getViewDefinition(string $databaseCode, string $schemaName, string $viewName): string
    {
        if ($schemaName === '*' || $viewName === '*') {
            return $this->errorResponse('Please provide an exact schema_name and view_name. Wildcard "*" is not allowed.');
        }

        $allowedDbs    = $this->queryService->getAllowedTables();
        $schemaAllowed = isset($allowedDbs[$databaseCode][$schemaName]) || isset($allowedDbs[$databaseCode]['*']);
        $tableEntries  = $allowedDbs[$databaseCode][$schemaName] ?? $allowedDbs[$databaseCode]['*'] ?? [];

        if (!$schemaAllowed || !$this->isTableAllowed($viewName, $tableEntries)) {
            return $this->errorResponse("Access denied.");
        }

        $connName = "temp_conn_{$databaseCode}";
        try {
            $dbModel = \App\Models\DatabaseConnection::where('database', $databaseCode)->active()->first();
            $adapter = $dbModel->getAdapter();

            DB::purge($connName);
            config(["database.connections.{$connName}" => $dbModel->getConnectionConfig()]);

            $query = $adapter->getViewDefinitionQuery();
            $params = ($dbModel->driver === 'sqlite') ? [$viewName] : [$viewName, $schemaName];
            $definition = DB::connection($connName)->select($query, $params);

            DB::purge($connName);
            return $this->safeJsonEncode([
                'database'   => $databaseCode,
                'view'       => "{$schemaName}.{$viewName}",
                'definition' => $definition[0]->view_definition ?? $definition[0]->definition ?? $definition[0]->sql ?? 'Not found'
            ]);
        } catch (\Exception $e) {
            DB::purge($connName);
            return $this->errorResponse('Failed to get view definition: ' . $e->getMessage());
        }
    }

    /**
     * Search for tables or columns by keyword across all accessible databases.
     */
    public function searchSchema(string $keyword): string
    {
        if (empty($keyword)) {
            return $this->errorResponse('keyword is required');
        }

        $allowedDbs = $this->queryService->getAllowedTables();
        $results = [];

        foreach ($allowedDbs as $dbCode => $schemas) {
            $connName = "temp_conn_{$dbCode}";
            try {
                $dbModel = \App\Models\DatabaseConnection::where('database', $dbCode)->active()->first();
                if (!$dbModel) continue;

                $adapter = $dbModel->getAdapter();
                DB::purge($connName);
                config(["database.connections.{$connName}" => $dbModel->getConnectionConfig()]);

                $query = $adapter->searchSchemaQuery();
                $searchTerm = "%{$keyword}%";
                
                $placeholderCount = substr_count($query, '?');
                $params = array_fill(0, $placeholderCount, $searchTerm);
                
                $matches = DB::connection($connName)->select($query, $params);

                foreach ($matches as $match) {
                    $matchSchema = $match->table_schema;
                    $matchTable  = $match->table_name;

                    $schemaAllowed = isset($schemas[$matchSchema]) || isset($schemas['*']);
                    $tableEntries  = $schemas[$matchSchema] ?? $schemas['*'] ?? [];

                    if ($schemaAllowed && $this->isTableAllowed($matchTable, $tableEntries)) {
                        $results[] = [
                            'database' => $dbCode,
                            'schema'   => $matchSchema,
                            'table'    => $matchTable,
                            'column'   => $match->column_name,
                            'notes'    => $match->description ?? ''
                        ];
                    }
                }
                DB::purge($connName);
            } catch (\Exception $e) {
                Log::warning("Failed to search schema in {$dbCode}: " . $e->getMessage());
            }
        }

        return $this->safeJsonEncode([
            'keyword' => $keyword,
            'matches' => $results,
            'count'   => count($results),
            'instruction' => 'IMPORTANT: Use the exact "schema" and "database" values from each match above when calling describe_table or execute_query. Never use "*" as schema_name.',
        ]);
    }

    /**
     * Get a small preview of data from a table.
     */
    public function getTablePreview(string $databaseCode, string $schemaName, string $tableName): string
    {
        if ($schemaName === '*' || $tableName === '*') {
            return $this->errorResponse('Please provide an exact schema_name and table_name. Wildcard "*" is not allowed.');
        }

        $allowedDbs    = $this->queryService->getAllowedTables();
        $schemaAllowed = isset($allowedDbs[$databaseCode][$schemaName]) || isset($allowedDbs[$databaseCode]['*']);
        $tableEntries  = $allowedDbs[$databaseCode][$schemaName] ?? $allowedDbs[$databaseCode]['*'] ?? [];

        if (!$schemaAllowed || !$this->isTableAllowed($tableName, $tableEntries)) {
            return $this->errorResponse("Access denied or table not found.");
        }

        $connName = "temp_conn_{$databaseCode}";
        try {
            $dbModel = \App\Models\DatabaseConnection::where('database', $databaseCode)->active()->first();
            $adapter = $dbModel->getAdapter();

            DB::purge($connName);
            config(["database.connections.{$connName}" => $dbModel->getConnectionConfig()]);

            $query = $adapter->getTablePreviewQuery($schemaName, $tableName, 5);
            $rows = DB::connection($connName)->select($query);

            DB::purge($connName);

            return $this->safeJsonEncode([
                'database' => $databaseCode,
                'table'    => "{$schemaName}.{$tableName}",
                'sample_rows' => $rows
            ]);
        } catch (\Exception $e) {
            DB::purge($connName);
            return $this->errorResponse('Failed to get preview: ' . $e->getMessage());
        }
    }

    /**
     * Get complete schema overview for all accessible databases.
     * Optimization: If total tables < 50, eagerly include column names to save AI loops.
     */
    public function getSchemaInfo(bool $isGroq = false): string
    {
        $allowedDbs = $this->queryService->getAllowedTables();

        if (empty($allowedDbs)) {
            return $this->errorResponse('Anda tidak memiliki izin untuk mengakses database apa pun. Silakan hubungi administrator.');
        }

        $overview = [];
        $totalTables = 0;

        foreach ($allowedDbs as $dbCode => $schemas) {
            foreach ($schemas as $schema => $tables) {
                $totalTables += count($tables);
            }
        }

        $isSmallSchema = ($totalTables < 10) && !$isGroq;

        foreach ($allowedDbs as $dbCode => $schemas) {
            $overview[$dbCode] = [];
            
            foreach ($schemas as $schema => $tables) {
                $formattedTables = [];
                
                foreach ($tables as $t) {
                    $tableName = is_array($t) ? ($t['name'] ?? '') : $t;
                    $tableObj = ['table_name' => $tableName];

                    if ($isSmallSchema) {
                        try {
                            $columns = $this->getCachedTableColumns($dbCode, $schema, $tableName);
                            $tableObj['columns'] = $columns;
                        } catch (\Exception $e) {
                            $tableObj['columns_error'] = 'Failed to load';
                        }
                    }
                    
                    $formattedTables[] = $tableObj;
                }
                $overview[$dbCode][$schema] = $formattedTables;
            }
        }

        // FIX: Tambahkan MANDATORY hint di response getSchemaInfo agar model Llama
        // langsung tahu nama schema eksak yang harus dipakai (bukan menebak '*')
        $schemaHints = [];
        foreach ($allowedDbs as $dbCode => $schemas) {
            $realSchemas = array_filter(array_keys($schemas), fn($s) => $s !== '*');
            foreach ($realSchemas as $s) {
                $schemaHints[] = "database_code='{$dbCode}' gunakan schema_name='{$s}'";
            }
        }

        return $this->safeJsonEncode([
            'total_databases' => count($allowedDbs),
            'total_tables'    => $totalTables,
            'is_eager_loaded' => $isSmallSchema,
            'databases'       => $overview,
            'MANDATORY_SCHEMA_USAGE' => implode('; ', $schemaHints),
            'usage_note'      => $isSmallSchema
                ? 'Column info is eager loaded. IMPORTANT: Use the EXACT schema_name from MANDATORY_SCHEMA_USAGE above when calling describe_table or execute_query. NEVER use "*" as schema_name.'
                : 'Use describe_table(database_code, schema_name, table_name) to see columns. IMPORTANT: Use the EXACT schema_name from MANDATORY_SCHEMA_USAGE above. NEVER use "*".',
        ]);
    }

    /**
     * Get columns for a table (Internal helper for eager loading).
     */
    private function getCachedTableColumns(string $databaseCode, string $schemaName, string $tableName): array
    {
        $connName = "temp_conn_{$databaseCode}_eager";
        try {
            $dbModel = \App\Models\DatabaseConnection::where('database', $databaseCode)->active()->first();
            if (!$dbModel) return [];

            $adapter = $dbModel->getAdapter();
            DB::purge($connName);
            config(["database.connections.{$connName}" => $dbModel->getConnectionConfig()]);

            $schemaParam = $adapter->usesSchema() ? $schemaName : $dbModel->database;
            $query = $adapter->describeTableWithKeysQuery();
            $columns = DB::connection($connName)->select($query, [$tableName, $schemaParam]);

            $result = array_map(fn($col) => $col->column_name, $columns);
            
            DB::purge($connName);
            return $result;
        } catch (\Exception $e) {
            DB::purge($connName);
            return [];
        }
    }
}
