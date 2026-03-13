<?php

namespace App\Mcp\Tools;

use Illuminate\Support\Facades\DB;
use PhpMcp\Server\Attributes\McpTool;

class DescribeTable
{
    /**
     * Get the schema of a specific table in the PostgreSQL database.
     * 
     * @param string $table The name of the table to describe.
     */
    #[McpTool(name: 'describe_table')]
    public function handle(string $table): array
    {
        return DB::select("
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_name = ? AND table_schema = 'public'
            ORDER BY ordinal_position
        ", [$table]);
    }
}
