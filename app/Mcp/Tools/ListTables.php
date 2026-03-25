<?php

namespace App\Mcp\Tools;

use App\Services\ToolCallExecutor;
use Illuminate\Support\Facades\DB;
use PhpMcp\Server\Attributes\McpTool;

class ListTables
{
    /**
     * List tables in sch_mbi schema that are accessible by the current user's role.
     */
    #[McpTool(name: 'list_tables')]
    public function handle(): array
    {
        // Kembalikan hanya tabel yang diizinkan berdasarkan role user
        $executor = new ToolCallExecutor();
        return $executor->getAllowedTables();
    }
}
