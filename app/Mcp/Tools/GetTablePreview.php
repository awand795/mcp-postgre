<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesRbac;
use App\Services\Core\SchemaService;
use App\Services\Core\QueryService;
use PhpMcp\Server\Attributes\McpTool;

/**
 * Tool: get_table_preview
 *
 * Mengambil 5 baris sampel dari tabel FISIK.
 * Otomatis diblokir untuk VIEW besar agar tidak timeout.
 */
class GetTablePreview
{
    use ResolvesRbac;

    #[McpTool(
        name: 'get_table_preview',
        description: 'Mengambil 5 baris contoh data dari tabel fisik. JANGAN gunakan untuk VIEW atau tabel sangat besar — gunakan execute_query dengan LIMIT 5 sebagai gantinya.'
    )]
    public function handle(
        string $database_code,
        string $schema_name,
        string $table_name
    ): array {
        $qs      = new QueryService();
        $service = new SchemaService($qs);

        $result = $service->getTablePreview($database_code, $schema_name, $table_name);

        return json_decode($result, true) ?? ['error' => 'Failed to parse result'];
    }
}
