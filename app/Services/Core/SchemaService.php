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

        // Guard: reject wildcard calls — AI must supply exact names
        if ($schemaName === '*' || $tableName === '*') {
            return $this->errorResponse(
                'Please provide an exact schema_name and table_name. ' .
                'Wildcard "*" is not allowed. Use get_database_schema_info to discover available tables, ' .
                'then call describe_table with specific names.'
            );
        }

        $allowedDbs = $this->queryService->getAllowedTables();
        
        if (!isset($allowedDbs[$databaseCode])) {
            return $this->errorResponse("Access denied: You don't have access to database '{$databaseCode}'.");
        }

        // Check schema access: allow if schema key is '*' or matches exactly
        $schemaAllowed = isset($allowedDbs[$databaseCode][$schemaName])
            || isset($allowedDbs[$databaseCode]['*']);

        if (!$schemaAllowed) {
            return $this->errorResponse("Access denied: You don't have access to schema '{$schemaName}' in database '{$databaseCode}'.");
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
                return $this->errorResponse("Table '{$schemaName}.{$tableName}' not found or has no columns.");
            }

            return $this->safeJsonEncode([
                'database' => $databaseCode,
                'table'    => "{$schemaName}.{$tableName}",
                'columns'  => $result,
                'indexes'  => $indexes,
                'usage_tip' => 'Gunakan get_column_values untuk melihat variasi isi data pada kolom kategori/status.'
            ]);
        } catch (\Exception $e) {
            DB::purge($connName);
            return $this->errorResponse('Failed to describe table: ' . $e->getMessage());
        }
    }

    /**
     * Get distinct values for a specific column.
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
            $values = DB::connection($connName)->select($query);
            
            $flatValues = array_map(fn($v) => current((array)$v), $values);

            DB::purge($connName);
            return $this->safeJsonEncode([
                'database' => $databaseCode,
                'column'   => "{$schemaName}.{$tableName}.{$columnName}",
                'distinct_values' => $flatValues
            ]);
        } catch (\Exception $e) {
            DB::purge($connName);
            return $this->errorResponse('Failed to get values: ' . $e->getMessage());
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
            // SQLite adapter needs different handling usually, but we'll follow general pattern
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
                
                // Dynamically determine number of placeholders needed
                $placeholderCount = substr_count($query, '?');
                $params = array_fill(0, $placeholderCount, $searchTerm);
                
                $matches = DB::connection($connName)->select($query, $params);

                foreach ($matches as $match) {
                    // Filter matches by user's RBAC — support wildcard schema/table and object-format entries
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
            'count'   => count($results)
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
    public function getSchemaInfo(): string
    {
        $allowedDbs = $this->queryService->getAllowedTables();

        if (empty($allowedDbs)) {
            return $this->errorResponse('Anda tidak memiliki izin untuk mengakses database apa pun. Silakan hubungi administrator.');
        }

        $overview = [];
        $totalTables = 0;

        // Count total tables first
        foreach ($allowedDbs as $dbCode => $schemas) {
            foreach ($schemas as $schema => $tables) {
                $totalTables += count($tables);
            }
        }

        $isSmallSchema = ($totalTables < 50);

        foreach ($allowedDbs as $dbCode => $schemas) {
            $overview[$dbCode] = [];
            
            // If small schema, try to get columns for all tables in one or few goes
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

        return $this->safeJsonEncode([
            'total_databases' => count($allowedDbs),
            'total_tables'    => $totalTables,
            'is_eager_loaded' => $isSmallSchema,
            'databases'       => $overview,
            'usage_note'      => $isSmallSchema 
                ? 'Informasi kolom sudah dimuat secara otomatis (Eager Loaded). Anda bisa langsung menulis query tanpa describe_table.'
                : 'Gunakan describe_table(database_code, schema_name, table_name) untuk melihat kolom. Pada execute_query, selalu pakai prefix schema.',
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
