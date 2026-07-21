<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesRbac;
use App\Services\Core\QueryService;
use PhpMcp\Server\Attributes\McpTool;

/**
 * Tool: execute_query
 *
 * Mengeksekusi SQL SELECT query pada database yang diizinkan.
 * Dilindungi 6-layer security (strip comment, SELECT-only, forbidden keywords,
 * single statement, RBAC tabel, execution).
 *
 * Mendukung multi-database & multi-driver:
 *   - PostgreSQL  → schema_name.table_name
 *   - MySQL/MariaDB → table_name atau database.table_name
 *   - SQL Server  → schema_name.table_name
 *   - SQLite      → table_name
 */
class ExecuteQueryTool
{
    use ResolvesRbac;

    #[McpTool(
        name: 'execute_query',
        description: 'Eksekusi SQL SELECT pada database yang diizinkan. Hanya SELECT yang diperbolehkan. Dukung PostgreSQL, MySQL, MariaDB, SQL Server, SQLite, ClickHouse.'
    )]
    public function handle(
        string $database_code,
        string $sql,
        string $label = '',
        array  $currency_columns = []
    ): array {
        $qs     = $this->queryService();
        $result = $qs->executeQuery($database_code, $sql, $label, $currency_columns);

        return json_decode($result, true) ?? ['error' => 'Failed to parse result'];
    }
}
