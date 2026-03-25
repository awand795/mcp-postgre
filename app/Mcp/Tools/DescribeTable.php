<?php

namespace App\Mcp\Tools;

use App\Services\ToolCallExecutor;
use Illuminate\Support\Facades\DB;
use PhpMcp\Server\Attributes\McpTool;

class DescribeTable
{
    /**
     * Get the schema of a specific table in the PostgreSQL database.
     * Akses dibatasi berdasarkan role user.
     *
     * @param string $table The name of the table to describe.
     */
    #[McpTool(name: 'describe_table')]
    public function handle(string $table): array
    {
        // Validasi RBAC: pastikan tabel boleh diakses user
        $executor = new ToolCallExecutor();
        $allowed  = $executor->getAllowedTables();

        if (!in_array($table, $allowed)) {
            throw new \InvalidArgumentException("Access denied: table '{$table}' is not allowed for your role.");
        }

        return DB::connection('pgsql_mbi')->select("
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_name = ? AND table_schema = 'sch_mbi'
            ORDER BY ordinal_position
        ", [$table]);
    }
}
