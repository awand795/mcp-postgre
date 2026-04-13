<?php

namespace App\Mcp\Tools;

use App\Services\ToolCallExecutor;
use Illuminate\Support\Facades\DB;
use PhpMcp\Server\Attributes\McpTool;

class DescribeTable
{
    /**
     * Get the schema of a specific table in any database.
     * Access is limited based on user's role.
     *
     * @param string $database_code The database code (e.g., 'mbi_prod')
     * @param string $schema_name The schema name (e.g., 'public', 'dbo')
     * @param string $table_name The name of the table to describe
     */
    #[McpTool(name: 'describe_table')]
    public function handle(
        string $database_code,
        string $schema_name,
        string $table_name
    ): array {
        // Get database connection model
        $dbModel = \App\Models\DatabaseConnection::where('code', $database_code)->active()->first();
        if (!$dbModel) {
            throw new \InvalidArgumentException("Database '{$database_code}' not found or inactive.");
        }

        $adapter = $dbModel->getAdapter();

        // For drivers that don't use schema concept, use database name
        $schemaParam = $adapter->usesSchema() ? $schema_name : $dbModel->database;

        // Validate RBAC: ensure user has access to this table
        $executor = new ToolCallExecutor();
        $allowed = $executor->getAllowedTables();

        if (!isset($allowed[$database_code][$schema_name]) ||
            !in_array($table_name, $allowed[$database_code][$schema_name] ?? [])) {
            throw new \InvalidArgumentException("Access denied: table '{$schema_name}.{$table_name}' is not allowed for your role.");
        }

        $connName = "temp_conn_{$database_code}";
        try {
            DB::purge($connName);
            config(["database.connections.{$connName}" => $dbModel->getConnectionConfig()]);

            // SQLite uses PRAGMA table_info
            if ($dbModel->driver === 'sqlite') {
                $columns = DB::connection($connName)->select("PRAGMA table_info({$table_name})");

                $result = [];
                foreach ($columns as $col) {
                    $result[] = [
                        'column_name' => $col->name,
                        'data_type' => $col->type,
                        'is_nullable' => $col->notnull ? 'NO' : 'YES',
                        'column_default' => $col->dflt_value,
                    ];
                }
            } else {
                $query = $adapter->describeTableQuery();
                $columns = DB::connection($connName)->select($query, [$table_name, $schemaParam]);

                $result = array_map(function($col) {
                    return [
                        'column_name' => $col->column_name,
                        'data_type' => $col->data_type,
                        'is_nullable' => $col->is_nullable,
                        'column_default' => $col->column_default ?? null,
                    ];
                }, $columns);
            }

            DB::purge($connName);

            if (empty($result)) {
                throw new \InvalidArgumentException("Table '{$schema_name}.{$table_name}' not found in database '{$database_code}'.");
            }

            return [
                'database_code' => $database_code,
                'driver' => $dbModel->driver,
                'schema_name' => $schema_name,
                'table_name' => $table_name,
                'columns' => $result,
            ];
        } catch (\InvalidArgumentException $e) {
            DB::purge($connName);
            throw $e;
        } catch (\Exception $e) {
            DB::purge($connName);
            throw new \InvalidArgumentException("Failed to describe table: " . $e->getMessage());
        }
    }
}
