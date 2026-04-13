<?php

namespace App\Services\Core;

use App\Services\BaseService;
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
            $dbModel = \App\Models\DatabaseConnection::where('code', $databaseCode)->active()->first();
            if (!$dbModel) {
                 return $this->errorResponse("Database configuration for '{$databaseCode}' not found or inactive.");
            }

            DB::purge($connName);
            config(["database.connections.{$connName}" => $dbModel->getConnectionConfig()]);

            $columns = DB::connection($connName)->select("
                SELECT column_name, data_type, is_nullable
                FROM information_schema.columns
                WHERE table_name = ? AND table_schema = ?
                ORDER BY ordinal_position
            ", [$tableName, $schemaName]);
            
            DB::purge($connName);

            if (empty($columns)) {
                return $this->errorResponse("Table '{$schemaName}.{$tableName}' not found or has no columns in database '{$databaseCode}'.");
            }

            $result = [];
            foreach ($columns as $col) {
                $result[] = [
                    'column'   => $col->column_name,
                    'type'     => $col->data_type,
                    'nullable' => $col->is_nullable,
                ];
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
