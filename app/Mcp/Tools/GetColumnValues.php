<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesRbac;
use App\Services\Core\SchemaService;
use App\Services\Core\QueryService;
use PhpMcp\Server\Attributes\McpTool;

/**
 * Tool: get_column_values
 *
 * Mengambil nilai unik (DISTINCT) dari sebuah kolom tabel FISIK.
 * TIDAK mendukung VIEW — gunakan execute_query SELECT DISTINCT untuk VIEW.
 */
class GetColumnValues
{
    use ResolvesRbac;

    #[McpTool(
        name: 'get_column_values',
        description: 'Mengambil nilai unik (DISTINCT) dari sebuah kolom tabel fisik (maks 20 nilai). DILARANG digunakan untuk VIEW — gunakan execute_query SELECT DISTINCT sebagai gantinya.'
    )]
    public function handle(
        string $database_code,
        string $schema_name,
        string $table_name,
        string $column_name
    ): array {
        $qs      = new QueryService();
        $service = new SchemaService($qs);

        $result = $service->getColumnValues($database_code, $schema_name, $table_name, $column_name);

        return json_decode($result, true) ?? ['error' => 'Failed to parse result'];
    }
}
