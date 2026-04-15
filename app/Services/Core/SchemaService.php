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
     * Get columns and data types for a specific table in a specific DB and schema.
     */
    public function describeTable(string $databaseCode, string $schemaName, string $tableName): string
    {
        if (empty($databaseCode) || empty($schemaName) || empty($tableName)) {
            return $this->errorResponse('database_code, schema_name, and table_name are required');
        }

        $allowedDbs = $this->queryService->getAllowedTables();
        
        if (!isset($allowedDbs[$databaseCode])) {
            return $this->errorResponse("Access denied: You don't have access to database '{$databaseCode}'.");
        }
        
        if (!isset($allowedDbs[$databaseCode][$schemaName]) || !in_array($tableName, $allowedDbs[$databaseCode][$schemaName])) {
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

            // SQLite uses PRAGMA table_info which can't be parameterized
            if ($dbModel->driver === 'sqlite') {
                $columns = DB::connection($connName)->select("PRAGMA table_info({$tableName})");

                // Transform PRAGMA result to standard format
                $result = [];
                foreach ($columns as $col) {
                    $result[] = [
                        'column'   => $col->name,
                        'type'     => $col->type,
                        'nullable' => $col->notnull ? 'NO' : 'YES',
                    ];
                }
            } else {
                // For MySQL, use database name as schema if driver doesn't use schema concept
                $schemaParam = $adapter->usesSchema() ? $schemaName : $dbModel->database;

                $query = $adapter->describeTableWithKeysQuery();
                $columns = DB::connection($connName)->select($query, [$tableName, $schemaParam]);

                $result = [];
                foreach ($columns as $col) {
                    $item = [
                        'column'   => $col->column_name,
                        'type'     => $col->data_type,
                        'nullable' => $col->is_nullable,
                    ];
                    if (!empty($col->foreign_key_table)) {
                        $item['references'] = "{$col->foreign_key_table}.{$col->foreign_key_column}";
                    }
                    $result[] = $item;
                }
            }

            DB::purge($connName);

            if (empty($result)) {
                return $this->errorResponse("Table '{$schemaName}.{$tableName}' not found or has no columns in database '{$databaseCode}'.");
            }

            return $this->safeJsonEncode([
                'database' => $databaseCode,
                'schema'   => $schemaName,
                'table'    => $tableName,
                'sql_ref'  => "{$schemaName}.{$tableName}",
                'columns'  => $result,
            ]);
        } catch (\Exception $e) {
            DB::purge($connName);
            return $this->errorResponse('Failed to describe table: ' . $e->getMessage());
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
                $matches = DB::connection($connName)->select($query, [$searchTerm, $searchTerm]);

                foreach ($matches as $match) {
                    // Filter matches by user's RBAC
                    if (isset($schemas[$match->table_schema]) && in_array($match->table_name, $schemas[$match->table_schema])) {
                        $results[] = [
                            'database' => $dbCode,
                            'schema'   => $match->table_schema,
                            'table'    => $match->table_name,
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
        $allowedDbs = $this->queryService->getAllowedTables();
        
        if (!isset($allowedDbs[$databaseCode][$schemaName]) || !in_array($tableName, $allowedDbs[$databaseCode][$schemaName])) {
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
     */
    public function getSchemaInfo(): string
    {
        $allowedDbs = $this->queryService->getAllowedTables();

        if (empty($allowedDbs)) {
            return $this->errorResponse('Anda tidak memiliki izin untuk mengakses database apa pun. Silakan hubungi administrator.');
        }

        $overview = [];
        $totalTables = 0;

        foreach ($allowedDbs as $dbCode => $schemas) {
            $overview[$dbCode] = [];
            foreach ($schemas as $schema => $tables) {
                // Here we just provide the list of tables we know they have access to.
                // We do not fetch all columns for all tables in all databases because it would be too large 
                // and take too long dynamically across remote DBs.
                // AI can use describe_table to get column details per table.
                $overview[$dbCode][$schema] = $tables;
                $totalTables += count($tables);
            }
        }

        return $this->safeJsonEncode([
            'total_databases' => count($allowedDbs),
            'total_tables'    => $totalTables,
            'databases'       => $overview,
            'usage_note'      => 'Gunakan describe_table(database_code, schema_name, table_name) untuk mendapatkan tipe data setiap kolom. Pada SQL execute_query, SELALU nyatakan tabel lengkap dengan prefix schema, contoh: schema_name.table_name.',
        ]);
    }
}
