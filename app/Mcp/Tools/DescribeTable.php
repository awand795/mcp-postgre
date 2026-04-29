<?php

namespace App\Mcp\Tools;

use App\Services\ToolCallExecutor;
use Illuminate\Support\Facades\DB;
use PhpMcp\Server\Attributes\McpTool;

class DescribeTable
{
    /**
     * Get column definitions, data types, and constraints for a specific table.
     * Always call this before executing a query to know the exact column names.
     */
    #[McpTool(
        name: 'describe_table',
        description: 'Get column definitions, data types, and constraints for a specific table. Always call this before executing a query to know the exact column names.'
    )]
    public function handle(string $database_code, string $schema_name, string $table_name): array
    {
        $dbModel = \App\Models\DatabaseConnection::where('database', $database_code)->active()->first();
        if (!$dbModel) {
            throw new \InvalidArgumentException("Database '{$database_code}' not found or inactive.");
        }

        $adapter   = $dbModel->getAdapter();
        $schemaPrm = $adapter->usesSchema() ? $schema_name : $dbModel->database;

        // RBAC check
        $executor = new ToolCallExecutor();
        $allowed  = $executor->getAllowedTables();
        $tables   = collect($allowed[$database_code][$schema_name] ?? [])
            ->map(fn($t) => is_array($t) ? ($t['name'] ?? '') : (string) $t)
            ->all();

        if (!isset($allowed[$database_code]) ||
            (!in_array('*', $tables) && !in_array($table_name, $tables))) {
            throw new \InvalidArgumentException("Access denied: table '{$schema_name}.{$table_name}' is not allowed.");
        }

        $connName = "mcp_describe_{$database_code}";
        try {
            DB::purge($connName);
            config(["database.connections.{$connName}" => $dbModel->getConnectionConfig()]);

            if ($dbModel->driver === 'sqlite') {
                $columns = DB::connection($connName)->select("PRAGMA table_info({$table_name})");
                $result  = array_map(fn($c) => [
                    'column_name'    => $c->name,
                    'data_type'      => $c->type,
                    'is_nullable'    => $c->notnull ? 'NO' : 'YES',
                    'column_default' => $c->dflt_value,
                ], $columns);
            } else {
                $columns = DB::connection($connName)->select($adapter->describeTableQuery(), [$table_name, $schemaPrm]);
                $result  = array_map(fn($c) => [
                    'column_name'    => $c->column_name,
                    'data_type'      => $c->data_type,
                    'is_nullable'    => $c->is_nullable,
                    'column_default' => $c->column_default ?? null,
                ], $columns);
            }

            DB::purge($connName);

            if (empty($result)) {
                throw new \InvalidArgumentException("Table '{$schema_name}.{$table_name}' not found in '{$database_code}'.");
            }

            return [
                'database_code' => $database_code,
                'driver'        => $dbModel->driver,
                'schema_name'   => $schema_name,
                'table_name'    => $table_name,
                'columns'       => $result,
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
