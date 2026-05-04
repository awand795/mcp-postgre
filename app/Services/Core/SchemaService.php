<?php

namespace App\Services\Core;

use App\Services\BaseService;
use App\Services\Database\DriverFactory;
use Illuminate\Support\Facades\Cache;
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

        // Check table access using centralized BaseService helper
        if (!$this->isTableAllowed($databaseCode, $schemaName, $tableName, $allowedDbs)) {
            return $this->errorResponse("Access denied: You don't have access to table '{$schemaName}.{$tableName}' in database '{$databaseCode}'.");
        }

        // ── DEEP RBAC: Check underlying tables ───────────────────────────────
        $underlying = $this->getUnderlyingTables($databaseCode, $schemaName, $tableName);
        foreach ($underlying as $u) {
            if (!$this->isTableAllowed($databaseCode, $u['schema'], $u['table'], $allowedDbs)) {
                return $this->errorResponse("Akses ditolak: View '{$tableName}' menggunakan data dari tabel '{$u['table']}' yang tidak diizinkan untuk peran Anda.");
            }
        }

        $connName = "temp_conn_{$databaseCode}";
        try {
            $dbModel = \App\Models\DatabaseConnection::where('database', $databaseCode)->active()->first();
            if (!$dbModel) {
                return $this->errorResponse("Database configuration for '{$databaseCode}' not found or inactive.");
            }

            $adapter = $dbModel->getAdapter();

            // OPT: Cache describe_table — struktur kolom sangat jarang berubah
            $cacheKey = 'describe_table_' . md5("{$databaseCode}_{$schemaName}_{$tableName}");
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                Log::info("[SchemaService] describeTable cache HIT: {$schemaName}.{$tableName}");
                return $cached;
            }

            // OPT: Reuse koneksi persistent dari QueryService jika sudah ada
            // agar tidak overhead membuat koneksi baru hanya untuk describe_table
            $persistentConn = "persistent_conn_{$databaseCode}";
            $useConn = null;
            try {
                DB::connection($persistentConn)->getPdo();
                $useConn = $persistentConn;
                Log::info("[SchemaService] describeTable reusing persistent connection for {$databaseCode}");
            } catch (\Throwable $e) {
                // Koneksi persistent belum ada, buat koneksi sementara
                DB::purge($connName);
                config(["database.connections.{$connName}" => $dbModel->getConnectionConfig()]);
                $useConn = $connName;
            }

            // Get columns and FKs
            $result = [];
            $indexes = [];

            // SQLite uses PRAGMA table_info which can't be parameterized
            if ($dbModel->driver === 'sqlite') {
                $columns = DB::connection($useConn)->select("PRAGMA table_info({$tableName})");
                foreach ($columns as $col) {
                    $result[] = [
                        'column' => $col->name,
                        'type' => $col->type,
                        'nullable' => $col->notnull ? 'NO' : 'YES',
                    ];
                }
            } else {
                $schemaParam = $adapter->usesSchema() ? $schemaName : $dbModel->database;
                $query = $adapter->describeTableWithKeysQuery();
                $columns = DB::connection($useConn)->select($query, [$tableName, $schemaParam]);

                $forbiddenCols = $this->getForbiddenColumns($databaseCode, $allowedDbs);

                foreach ($columns as $col) {
                    $colName = $col->column_name;
                    if (in_array(strtolower($colName), $forbiddenCols)) {
                        continue;
                    }

                    $item = [
                        'column' => $colName,
                        'type' => $col->data_type,
                        'nullable' => $col->is_nullable,
                        'notes' => $col->description ?? ''
                    ];
                    if (!empty($col->foreign_key_table)) {
                        $item['references'] = "{$col->foreign_key_table}.{$col->foreign_key_column}";
                    }
                    $result[] = $item;
                }

                // Get Index Info
                $idxQuery = $adapter->getTableIndexesQuery();
                $idxData = DB::connection($useConn)->select($idxQuery, [$tableName, $schemaParam]);
                foreach ($idxData as $idx) {
                    $indexes[] = [
                        'name' => $idx->index_name,
                        'column' => $idx->column_name,
                        'type' => $idx->is_primary ? 'PRIMARY KEY' : ($idx->is_unique ? 'UNIQUE INDEX' : 'INDEX')
                    ];
                }
            }

            if ($useConn === $connName) {
                DB::purge($connName);
            }

            // Apply Column-level RBAC: Redact forbidden columns
            $forbidden = $this->getForbiddenColumns($databaseCode, $allowedDbs);
            $hasForbiddenFilter = !empty($forbidden);
            $originalCount = count($result);

            if ($hasForbiddenFilter) {
                $result = array_filter($result, function ($col) use ($forbidden) {
                    return !in_array(strtolower($col['column'] ?? $col['column_name']), $forbidden);
                });
                $result = array_values($result); // re-index

                $indexes = array_filter($indexes, function ($idx) use ($forbidden) {
                    return !in_array(strtolower($idx['column']), $forbidden);
                });
                $indexes = array_values($indexes);
            }

            if (empty($result)) {
                // If the table exists (originalCount > 0) but all columns are filtered out
                if ($originalCount > 0 && $hasForbiddenFilter) {
                    return $this->safeJsonEncode([
                        'error' => 'COLUMN_ACCESS_DENIED',
                        'detail' => "Akses ke seluruh kolom di tabel '{$tableName}' dibatasi untuk peran Anda.",
                        'MANDATORY_AI_ACTION' => "Informasikan kepada user bahwa akses ke rincian data di tabel '{$tableName}' dibatasi sesuai kebijakan keamanan data. BERHENTI mencoba mencari rincian data ini di tabel lain.",
                    ]);
                }

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
                    'error' => "Table '{$schemaName}.{$tableName}' not found or has no columns.",
                    'MANDATORY_AI_ACTION' => $hint,
                ]);
            }
            $response = [
                'database' => $databaseCode,
                'table' => "{$schemaName}.{$tableName}",
                'columns' => $result,
                'indexes' => $indexes,
                'usage_tip' => 'Gunakan get_column_values untuk melihat variasi isi data pada kolom kategori/status.'
            ];

            $encoded = $this->safeJsonEncode($response);
            // Cache 30 menit — struktur kolom sangat jarang berubah
            Cache::put($cacheKey, $encoded, 1800);
            Log::info("[SchemaService] describeTable cached: {$schemaName}.{$tableName} (TTL=1800s)");
            return $encoded;
        } catch (\Exception $e) {
            DB::purge($connName);
            return $this->errorResponse('Failed to describe table: ' . $e->getMessage());
        }
    }

    /**
     * Get distinct values for a specific column.
     *
     * OPT: Cache hasil selama 1 jam agar AI tidak perlu query ulang untuk kolom
     * yang sama (misal: nama_kategori_barang, status_aktif_barang).
     * Jika target adalah VIEW (TABLESAMPLE error), langsung return MANDATORY_AI_ACTION
     * tanpa fallback SELECT DISTINCT yang menyebabkan full scan + timeout.
     */
    public function getColumnValues(string $databaseCode, string $schemaName, string $tableName, string $columnName): string
    {
        if ($schemaName === '*' || $tableName === '*') {
            return $this->errorResponse('Please provide an exact schema_name and table_name. Wildcard "*" is not allowed.');
        }

        // GUARD: Tolak langsung jika target adalah VIEW (nama mengandung "view_")
        // VIEW tidak support TABLESAMPLE — ini 100% akan error, skip sejak awal.
        if (stripos($tableName, 'view_') === 0 || stripos($tableName, 'view') === 0) {
            Log::info("[SchemaService] getColumnValues blocked for VIEW '{$tableName}' — returning fast instruction.");
            return $this->safeJsonEncode([
                'warning' => "get_column_values TIDAK DIDUKUNG untuk '{$tableName}' karena ini adalah VIEW PostgreSQL.",
                'MANDATORY_AI_ACTION' => implode(' ', [
                    "JANGAN panggil get_column_values lagi untuk tabel ini.",
                    "LANGKAH WAJIB BERIKUTNYA:",
                    "(1) Gunakan execute_query dengan query: SELECT DISTINCT {$columnName} FROM {$schemaName}.{$tableName} LIMIT 20",
                    "(2) Gunakan hasil tersebut sebagai nilai filter WHERE pada query utama berikutnya.",
                    "(3) JANGAN menebak nilai kolom — eksekusi SELECT DISTINCT terlebih dahulu.",
                ]),
            ]);
        }

        $allowedDbs = $this->queryService->getAllowedTables();

        if (!$this->isTableAllowed($databaseCode, $schemaName, $tableName, $allowedDbs)) {
            return $this->errorResponse("Access denied.");
        }

        // ── DEEP RBAC: Check underlying tables ───────────────────────────────
        $underlying = $this->getUnderlyingTables($databaseCode, $schemaName, $tableName);
        foreach ($underlying as $u) {
            if (!$this->isTableAllowed($databaseCode, $u['schema'], $u['table'], $allowedDbs)) {
                return $this->errorResponse("Akses ditolak: View '{$tableName}' menggunakan data dari tabel '{$u['table']}' yang tidak diizinkan untuk peran Anda.");
            }
        }

        // OPT: Cek cache dulu — nilai kolom kategori/status jarang berubah
        $cacheKey = 'col_values_' . md5("{$databaseCode}_{$schemaName}_{$tableName}_{$columnName}");
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::info("[SchemaService] getColumnValues cache HIT: {$schemaName}.{$tableName}.{$columnName}");
            return $cached;
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
                $flatValues = array_map(fn($v) => current((array) $v), $values);
                DB::purge($connName);
                $result = $this->safeJsonEncode([
                    'database' => $databaseCode,
                    'column' => "{$schemaName}.{$tableName}.{$columnName}",
                    'distinct_values' => $flatValues,
                    'note' => count($flatValues) < 20 ? 'Full result' : 'Sampled (top 20)',
                    'cached' => false,
                ]);
                // Cache 1 jam — nilai kolom seperti kategori/status sangat jarang berubah
                Cache::put($cacheKey, $result, 3600);
                Log::info("[SchemaService] getColumnValues cached: {$schemaName}.{$tableName}.{$columnName} (" . count($flatValues) . " values)");
                return $result;
            } catch (\Exception $tablesampleErr) {
                // VIEW tidak support TABLESAMPLE — langsung return instruksi, jangan fallback SELECT DISTINCT
                DB::purge($connName);
                Log::warning("[SchemaService] get_column_values skipped for {$tableName}.{$columnName} (likely a VIEW): " . $tablesampleErr->getMessage());
                // Cache response "VIEW tidak supported" selama 10 menit agar AI tidak retry terus
                $viewResult = $this->safeJsonEncode([
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
                Cache::put($cacheKey, $viewResult, 600);
                return $viewResult;
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

        $allowedDbs = $this->queryService->getAllowedTables();

        if (!$this->isTableAllowed($databaseCode, $schemaName, $viewName, $allowedDbs)) {
            return $this->errorResponse("Access denied.");
        }

        // ── DEEP RBAC: Check underlying tables ───────────────────────────────
        $underlying = $this->getUnderlyingTables($databaseCode, $schemaName, $viewName);
        foreach ($underlying as $u) {
            if (!$this->isTableAllowed($databaseCode, $u['schema'], $u['table'], $allowedDbs)) {
                return $this->errorResponse("Akses ditolak: View '{$viewName}' menggunakan data dari tabel '{$u['table']}' yang tidak diizinkan untuk peran Anda.");
            }
        }

        $connName = "temp_conn_{$databaseCode}";
        try {
            $dbModel = \App\Models\DatabaseConnection::where('database', $databaseCode)->active()->first();
            $adapter = $dbModel->getAdapter();

            DB::purge($connName);
            config(["database.connections.{$connName}" => $dbModel->getConnectionConfig()]);

            $query = $adapter->getViewDefinitionQuery();
            // FIX: Untuk MySQL/MariaDB, gunakan database name sebagai schema param
            $schemaParam = $adapter->usesSchema() ? $schemaName : $dbModel->database;
            $params = ($dbModel->driver === 'sqlite') ? [$viewName] : [$viewName, $schemaParam];
            $definition = DB::connection($connName)->select($query, $params);

            DB::purge($connName);
            return $this->safeJsonEncode([
                'database' => $databaseCode,
                'view' => "{$schemaName}.{$viewName}",
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
                if (!$dbModel)
                    continue;

                $adapter = $dbModel->getAdapter();
                DB::purge($connName);
                config(["database.connections.{$connName}" => $dbModel->getConnectionConfig()]);

                $query = $adapter->searchSchemaQuery();
                $searchTerm = "%{$keyword}%";

                $placeholderCount = substr_count($query, '?');
                $params = array_fill(0, $placeholderCount, $searchTerm);

                $matches = DB::connection($connName)->select($query, $params);

                $forbidden = $this->getForbiddenColumns($dbCode, $allowedDbs);

                foreach ($matches as $match) {
                    $matchSchema = $match->table_schema;
                    $matchTable = $match->table_name;
                    $matchColumn = $match->column_name;

                    // Skip if table or column is not allowed
                    if (!$this->isTableAllowed($dbCode, $matchSchema, $matchTable, $allowedDbs)) {
                        continue;
                    }

                    if (in_array(strtolower($matchColumn), $forbidden)) {
                        continue;
                    }

                    $results[] = [
                        'database' => $dbCode,
                        'schema' => $matchSchema,
                        'table' => $matchTable,
                        'column' => $matchColumn,
                        'notes' => $match->description ?? ''
                    ];
                }
                DB::purge($connName);
            } catch (\Exception $e) {
                Log::warning("Failed to search schema in {$dbCode}: " . $e->getMessage());
            }
        }

        return $this->safeJsonEncode([
            'keyword' => $keyword,
            'matches' => $results,
            'count' => count($results),
            'instruction' => 'IMPORTANT: Use the exact "schema" and "database" values from each match above when calling describe_table or execute_query. Never use "*" as schema_name.',
        ]);
    }

    /**
     * Get a small preview of data from a table.
     *
     * OPT: Guard VIEW — jika target adalah VIEW PostgreSQL, langsung kembalikan
     * instruksi MANDATORY_AI_ACTION tanpa eksekusi query yang lambat.
     * VIEW besar bisa memakan 30-60 detik hanya untuk LIMIT 5.
     */
    public function getTablePreview(string $databaseCode, string $schemaName, string $tableName): string
    {
        if ($schemaName === '*' || $tableName === '*') {
            return $this->errorResponse('Please provide an exact schema_name and table_name. Wildcard "*" is not allowed.');
        }

        $allowedDbs = $this->queryService->getAllowedTables();

        if (!$this->isTableAllowed($databaseCode, $schemaName, $tableName, $allowedDbs)) {
            return $this->errorResponse("Access denied or table not found.");
        }

        // ── DEEP RBAC: Check underlying tables ───────────────────────────────
        $underlying = $this->getUnderlyingTables($databaseCode, $schemaName, $tableName);
        foreach ($underlying as $u) {
            if (!$this->isTableAllowed($databaseCode, $u['schema'], $u['table'], $allowedDbs)) {
                return $this->errorResponse("Akses ditolak: View '{$tableName}' menggunakan data dari tabel '{$u['table']}' yang tidak diizinkan untuk peran Anda.");
            }
        }

        $connName = "temp_conn_{$databaseCode}";
        try {
            $dbModel = \App\Models\DatabaseConnection::where('database', $databaseCode)->active()->first();
            if (!$dbModel) {
                return $this->errorResponse("Database configuration for '{$databaseCode}' not found or inactive.");
            }
            $adapter = $dbModel->getAdapter();

            // OPT: Cek apakah target adalah VIEW sebelum eksekusi preview
            // VIEW besar bisa memakan 30-60 detik hanya untuk LIMIT 5 — langsung skip
            if ($dbModel->driver === 'pgsql') {
                $cacheKeyView = 'is_view_' . md5("{$databaseCode}_{$schemaName}_{$tableName}");
                $isView = Cache::remember($cacheKeyView, 3600, function () use ($connName, $dbModel, $schemaName, $tableName) {
                    try {
                        DB::purge($connName);
                        config(["database.connections.{$connName}" => $dbModel->getConnectionConfig()]);
                        $check = DB::connection($connName)->select(
                            "SELECT table_type FROM information_schema.tables 
                             WHERE table_schema = ? AND table_name = ? LIMIT 1",
                            [$schemaName, $tableName]
                        );
                        DB::purge($connName);
                        return !empty($check) && ($check[0]->table_type === 'VIEW');
                    } catch (\Throwable $e) {
                        return false;
                    }
                });

                if ($isView) {
                    Log::info("[SchemaService] getTablePreview skipped — '{$schemaName}.{$tableName}' is a VIEW. Returning fast instruction.");
                    return $this->safeJsonEncode([
                        'warning' => "'{$tableName}' adalah VIEW — get_table_preview tidak didukung karena akan sangat lambat.",
                        'MANDATORY_AI_ACTION' => implode(' ', [
                            "JANGAN panggil get_table_preview lagi untuk '{$tableName}'.",
                            "LANGKAH WAJIB:",
                            "(1) Gunakan describe_table untuk mendapatkan nama kolom yang TEPAT.",
                            "(2) Langsung jalankan execute_query dengan filter WHERE yang spesifik dan LIMIT 5 jika butuh sample data.",
                            "(3) Jangan coba preview VIEW besar — gunakan query langsung dengan filter yang tepat.",
                        ]),
                    ]);
                }
            }

            DB::purge($connName);
            config(["database.connections.{$connName}" => $dbModel->getConnectionConfig()]);

            $query = $adapter->getTablePreviewQuery($schemaName, $tableName, 5);
            $rows = DB::connection($connName)->select($query);

            DB::purge($connName);

            return $this->safeJsonEncode([
                'database' => $databaseCode,
                'table' => "{$schemaName}.{$tableName}",
                'sample_rows' => $rows
            ]);
        } catch (\Exception $e) {
            DB::purge($connName);
            return $this->errorResponse('Failed to get preview: ' . $e->getMessage());
        }
    }

    /**
     * Get complete schema overview for all accessible databases.
     * OPT: Cache hasil schema info per user/role selama 10 menit agar tidak
     * query information_schema berulang kali di setiap percakapan baru.
     * Optimization: If total tables < 50, eagerly include column names to save AI loops.
     */
    public function getSchemaInfo(bool $isGroq = false): string
    {
        // OPT: Cache schema info — structure database tidak berubah setiap menit
        $userId = \Illuminate\Support\Facades\Auth::id() ?? 'guest';
        $cacheKey = 'schema_info_' . md5("{$userId}_{$isGroq}");
        // CATATAN: Cache schema info di-skip jika ada perubahan DB connection
        // TTL 10 menit — cukup untuk session normal, tidak terlalu lama
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::info("[SchemaService] getSchemaInfo cache HIT for user {$userId}");
            return $cached;
        }
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

        // Bangun schema hints dari allowedDbs.
        // Jika schema key adalah '*' (wildcard), AI tidak tahu harus pakai schema apa.
        // Dalam kondisi itu, sertakan nama schema dari overview yang sudah dibangun di atas
        // agar AI tetap mendapat nama eksak yang bisa langsung dipakai.
        $schemaHints = [];
        foreach ($allowedDbs as $dbCode => $schemas) {
            $realSchemas = array_filter(array_keys($schemas), fn($s) => $s !== '*');
            // Jika tidak ada schema nyata (semua wildcard), ambil dari overview yang sudah built
            if (empty($realSchemas) && isset($overview[$dbCode])) {
                $realSchemas = array_filter(array_keys($overview[$dbCode]), fn($s) => $s !== '*');
            }
            foreach ($realSchemas as $s) {
                $schemaHints[] = "database_code='{$dbCode}' gunakan schema_name='{$s}'";
            }
        }

        // Jika masih kosong (edge case semua wildcard dan overview kosong), beri instruksi fallback
        $mandatorySchemaUsage = !empty($schemaHints)
            ? implode('; ', $schemaHints)
            : 'Panggil search_schema dengan keyword nama tabel untuk menemukan schema_name yang eksak. JANGAN gunakan "*" sebagai schema_name.';

        $result = $this->safeJsonEncode([
            'total_databases' => count($allowedDbs),
            'total_tables' => $totalTables,
            'is_eager_loaded' => $isSmallSchema,
            'databases' => $overview,
            'MANDATORY_SCHEMA_USAGE' => $mandatorySchemaUsage,
            'MANDATORY_NEXT_STEP' => implode(' ', [
                'Setelah membaca response ini, LANGKAH BERIKUTNYA YANG WAJIB:',
                '(1) Identifikasi tabel yang paling relevan dari daftar di atas.',
                '(2) Langsung panggil describe_table pada tabel tersebut.',
                '(3) DILARANG memanggil search_schema jika tabel sudah terlihat jelas dari daftar di atas.',
                '(4) DILARANG memanggil search_schema lebih dari 1 kali untuk topik yang sama.',
                '(5) Jika tabel adalah VIEW (nama mengandung view_), DILARANG panggil get_column_values — gunakan execute_query SELECT DISTINCT sebagai gantinya.',
            ]),
            'usage_note' => $isSmallSchema
                ? 'Column info is eager loaded. IMPORTANT: Use the EXACT schema_name from MANDATORY_SCHEMA_USAGE above when calling describe_table or execute_query. NEVER use "*" as schema_name.'
                : 'Use describe_table(database_code, schema_name, table_name) to see columns. IMPORTANT: Use the EXACT schema_name from MANDATORY_SCHEMA_USAGE above. NEVER use "*".',
        ]);

        // Cache schema info 10 menit
        Cache::put($cacheKey, $result, 600);
        Log::info("[SchemaService] getSchemaInfo cached for user {$userId} ({$totalTables} tables)");
        return $result;
    }

    /**
     * Get columns for a table (Internal helper for eager loading).
     * OPT: Cache hasil kolom tabel selama 30 menit — struktur kolom sangat jarang berubah.
     */
    private function getCachedTableColumns(string $databaseCode, string $schemaName, string $tableName): array
    {
        // OPT: Cache struktur kolom tabel — tidak berubah kecuali ada migration
        $cacheKey = 'table_columns_' . md5("{$databaseCode}_{$schemaName}_{$tableName}");
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $connName = "temp_conn_{$databaseCode}_eager";
        try {
            $dbModel = \App\Models\DatabaseConnection::where('database', $databaseCode)->active()->first();
            if (!$dbModel)
                return [];

            $adapter = $dbModel->getAdapter();
            DB::purge($connName);
            config(["database.connections.{$connName}" => $dbModel->getConnectionConfig()]);

            $schemaParam = $adapter->usesSchema() ? $schemaName : $dbModel->database;
            $query = $adapter->describeTableWithKeysQuery();
            $columns = DB::connection($connName)->select($query, [$tableName, $schemaParam]);

            $result = array_map(fn($col) => $col->column_name, $columns);

            DB::purge($connName);

            Cache::put($cacheKey, $result, 1800);
            return $result;
        } catch (\Exception $e) {
            DB::purge($connName);
            return [];
        }
    }
}