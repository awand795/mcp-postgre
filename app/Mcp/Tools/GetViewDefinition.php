<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesRbac;
use App\Services\Core\SchemaService;
use App\Services\Core\QueryService;
use PhpMcp\Server\Attributes\McpTool;

/**
 * Tool: get_view_definition
 *
 * Menampilkan DDL / logika query di balik sebuah VIEW.
 * Berguna saat AI perlu tahu kolom asal dari tabel base VIEW tersebut.
 */
class GetViewDefinition
{
    use ResolvesRbac;

    #[McpTool(
        name: 'get_view_definition',
        description: 'Mendapatkan DDL/logika query di balik sebuah View PostgreSQL/MySQL. Gunakan ini untuk memahami dari tabel mana kolom-kolom VIEW berasal.'
    )]
    public function handle(
        string $database_code,
        string $schema_name,
        string $view_name
    ): array {
        $qs      = new QueryService();
        $service = new SchemaService($qs);

        $result = $service->getViewDefinition($database_code, $schema_name, $view_name);

        return json_decode($result, true) ?? ['error' => 'Failed to parse result'];
    }
}
