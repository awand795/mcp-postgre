<?php

namespace App\Mcp\Tools;

use App\Services\ToolCallExecutor;
use Illuminate\Support\Facades\DB;
use PhpMcp\Server\Attributes\McpTool;

class ListTables
{
    /**
     * List tables accessible by the current user's role across all databases.
     * Returns tables grouped by database_code and schema_name.
     */
    #[McpTool(name: 'list_tables')]
    public function handle(): array
    {
        $executor = new ToolCallExecutor();
        $allowed = $executor->getAllowedTables();

        // Transform to user-friendly format
        $result = [];
        foreach ($allowed as $dbCode => $schemas) {
            foreach ($schemas as $schema => $tables) {
                foreach ($tables as $table) {
                    $result[] = [
                        'database_code' => $dbCode,
                        'schema_name' => $schema,
                        'table_name' => $table,
                        'full_reference' => "{$dbCode}.{$schema}.{$table}",
                    ];
                }
            }
        }

        return $result;
    }
}
