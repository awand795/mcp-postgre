<?php

namespace App\Mcp\Tools;

use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Response;
use PhpMcp\Server\Attributes\McpTool;

class ListTables
{
    /**
     * List all tables in the public schema of the PostgreSQL database.
     */
    #[McpTool(name: 'list_tables')]
    public function handle(): array
    {
        $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
        return array_map(fn($t) => $t->table_name, $tables);
    }
}
