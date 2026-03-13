<?php

namespace App\Mcp\Tools;

use Illuminate\Support\Facades\DB;
use PhpMcp\Server\Attributes\McpTool;

class ExecuteQuery
{
    /**
     * Execute a read-only PostgreSQL query (SELECT only).
     * 
     * @param string $query The SQL query to execute.
     */
    #[McpTool(name: 'execute_query')]
    public function handle(string $query): array
    {
        if (!preg_match('/^\s*select/i', $query)) {
            throw new \InvalidArgumentException('Only SELECT queries are allowed.');
        }

        return DB::select($query);
    }
}
