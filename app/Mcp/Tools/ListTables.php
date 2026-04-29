<?php

namespace App\Mcp\Tools;

use App\Services\ToolCallExecutor;
use PhpMcp\Server\Attributes\McpTool;

class ListTables
{
    /**
     * List all tables accessible by the current user across all databases, grouped by database and schema.
     */
    #[McpTool(
        name: 'list_tables',
        description: 'List all tables accessible by the current user across all databases, grouped by database and schema.'
    )]
    public function handle(): array
    {
        $executor = new ToolCallExecutor();
        $allowed = $executor->getAllowedTables();

        $result = [];
        foreach ($allowed as $dbCode => $schemas) {
            foreach ($schemas as $schema => $tables) {
                foreach ($tables as $table) {
                    $tableName = is_array($table) ? ($table['name'] ?? '') : (string) $table;
                    if (empty($tableName) || $tableName === '*') continue;
                    $result[] = [
                        'database_code'  => $dbCode,
                        'schema_name'    => $schema,
                        'table_name'     => $tableName,
                        'full_reference' => "{$dbCode}.{$schema}.{$tableName}",
                    ];
                }
            }
        }

        return $result;
    }
}
